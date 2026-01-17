<?php

namespace App\Http\Controllers;

use App\Models\AlmacenCentral;
use App\Models\Insumo;
use App\Models\Lote;
use App\Models\LoteGrupo;
use App\Models\MovimientoStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class MovimientoEstadosImportController extends Controller
{
    private const CENTRAL_HOSPITAL_ID = 1;
    private const CENTRAL_SEDE_ID = 1;

    /**
     * POST /api/movimiento/estados/import
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Tipo de archivo no permitido. Solo se aceptan archivos .xls y .xlsx',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if (!class_exists(IOFactory::class)) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Dependencia faltante: phpoffice/phpspreadsheet. Ejecute composer require phpoffice/phpspreadsheet.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            @ini_set('memory_limit', '512M');
            $reader = IOFactory::createReader(IOFactory::identify($file->getRealPath()));
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = (int) $sheet->getHighestRow();
            if ($highestRow < 2) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'El archivo no contiene datos para procesar.',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $headers = $this->parseHeaders($sheet);
            if (!$headers['codigo'] || empty($headers['estados'])) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se pudieron determinar las columnas de código o cantidades por estado en el Excel.',
                    'data' => $headers,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $totalesPorInsumo = [];
            $totalesPorEstado = [];
            $omitidos = [];
            $errores = [];
            $insumosSinCoincidencia = [];
            $insumosInfo = [];
            $lotesPorInsumo = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $codigo = $this->getCellString($sheet, $headers['codigo'], $row);
                    $nombre = $headers['nombre'] ? $this->getCellString($sheet, $headers['nombre'], $row) : null;

                    if ($codigo === '' && $nombre === null) {
                        $omitidos[] = ['fila' => $row, 'motivo' => 'Fila vacía'];
                        continue;
                    }

                    $insumo = $this->buscarInsumoPorCodigo($codigo);
                    if (!$insumo) {
                        $insumosSinCoincidencia[] = [
                            'fila' => $row,
                            'codigo' => $codigo,
                            'nombre' => $nombre,
                            'motivo' => 'Insumo no encontrado por código ni código alterno',
                        ];
                        continue;
                    }

                    $insumosInfo[$insumo->id] = [
                        'codigo' => $insumo->codigo,
                        'nombre' => $insumo->nombre,
                    ];

                    $totalInsumo = 0;
                    foreach ($headers['estados'] as $estado => $columnLetter) {
                        $cantidad = $this->getCellFloat($sheet, $columnLetter, $row);
                        if ($cantidad <= 0) {
                            continue;
                        }

                        $totalInsumo += $cantidad;
                        $totalesPorEstado[$estado][$insumo->id] = ($totalesPorEstado[$estado][$insumo->id] ?? 0) + $cantidad;
                    }

                    if ($totalInsumo <= 0) {
                        $omitidos[] = [
                            'fila' => $row,
                            'codigo' => $codigo,
                            'motivo' => 'Cantidades en cero para todos los estados',
                        ];
                        continue;
                    }

                    $totalesPorInsumo[$insumo->id] = ($totalesPorInsumo[$insumo->id] ?? 0) + $totalInsumo;
                } catch (Throwable $rowError) {
                    $errores[] = [
                        'fila' => $row,
                        'error' => $rowError->getMessage(),
                    ];
                    Log::warning('Importación movimientos estados - error en fila', [
                        'fila' => $row,
                        'exception' => $rowError->getMessage(),
                    ]);
                }
            }

            if (empty($totalesPorInsumo)) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No se registraron cantidades válidas en el archivo.',
                    'data' => [
                        'omitidos' => $omitidos,
                        'errores' => $errores,
                        'insumos_no_encontrados' => $insumosSinCoincidencia,
                    ],
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $userId = optional($request->user())->id;
            $totalGlobal = array_sum($totalesPorInsumo);
            $codigoMovimiento = 'IMP-' . Str::upper(Str::random(10));
            $fecha = Carbon::now();
            $movimientosDespacho = [];

            DB::transaction(function () use (
                $totalesPorInsumo,
                $totalesPorEstado,
                $userId,
                $totalGlobal,
                $codigoMovimiento,
                $fecha,
                &$movimientosDespacho,
                $insumosInfo,
                &$lotesPorInsumo
            ) {
                foreach ($totalesPorInsumo as $insumoId => $cantidad) {
                    $cantidadEntera = (int) round($cantidad);

                    $lote = $this->obtenerLoteCentral($insumoId, $fecha);
                    $lotesPorInsumo[$insumoId] = $lote;

                    $registro = AlmacenCentral::query()
                        ->lockForUpdate()
                        ->where('insumo_id', $insumoId)
                        ->where('hospital_id', self::CENTRAL_HOSPITAL_ID)
                        ->where('sede_id', self::CENTRAL_SEDE_ID)
                        ->where('lote_id', $lote->id)
                        ->first();

                    if ($registro) {
                        $registro->cantidad = (int) $registro->cantidad + $cantidadEntera;
                        $registro->status = true;
                        $registro->estado = 'completado';
                        $registro->save();
                    } else {
                        AlmacenCentral::create([
                            'insumo_id' => $insumoId,
                            'hospital_id' => self::CENTRAL_HOSPITAL_ID,
                            'sede_id' => self::CENTRAL_SEDE_ID,
                            'lote_id' => $lote->id,
                            'cantidad' => $cantidadEntera,
                            'status' => true,
                            'estado' => 'completado',
                        ]);
                    }
                }

                MovimientoStock::create([
                    'tipo' => 'entrada',
                    'tipo_movimiento' => 'ingreso_estados',
                    'origen_hospital_id' => null,
                    'origen_sede_id' => null,
                    'destino_hospital_id' => self::CENTRAL_HOSPITAL_ID,
                    'destino_sede_id' => self::CENTRAL_SEDE_ID,
                    'origen_almacen_tipo' => 'externo',
                    'origen_almacen_id' => null,
                    'destino_almacen_tipo' => 'almacenCent',
                    'destino_almacen_id' => null,
                    'cantidad_salida_total' => 0,
                    'cantidad_entrada_total' => (int) round($totalGlobal),
                    'discrepancia_total' => false,
                    'fecha_despacho' => $fecha,
                    'observaciones' => 'Ingreso de totales de insumos por estados desde importación Excel',
                    'estado' => 'recibido',
                    'codigo_grupo' => $codigoMovimiento,
                    'user_id' => $userId,
                    'user_id_receptor' => null,
                ]);

                foreach ($totalesPorEstado as $estado => $insumos) {
                    $totalEstado = (int) round(array_sum($insumos));
                    if ($totalEstado <= 0) {
                        continue;
                    }

                    $itemsLotes = [];
                    $detalleInsumos = [];
                    foreach ($insumos as $insumoId => $cantidad) {
                        $cantidadEntera = (int) round($cantidad);
                        if ($cantidadEntera <= 0) {
                            continue;
                        }

                        $lote = $lotesPorInsumo[$insumoId] ?? $this->obtenerLoteCentral($insumoId, $fecha);
                        $lotesPorInsumo[$insumoId] = $lote;

                        $itemsLotes[] = [
                            'lote_id' => $lote->id,
                            'cantidad' => $cantidadEntera,
                        ];

                        $detalleInsumos[] = [
                            'insumo_id' => $insumoId,
                            'codigo' => $insumosInfo[$insumoId]['codigo'] ?? null,
                            'nombre' => $insumosInfo[$insumoId]['nombre'] ?? null,
                            'cantidad' => $cantidadEntera,
                            'lote_id' => $lote->id,
                        ];
                    }

                    if (empty($itemsLotes)) {
                        continue;
                    }

                    [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($itemsLotes);

                    $codigoDespacho = 'DESP-EST-' . Str::upper(Str::random(8));

                    $movimiento = MovimientoStock::create([
                        'tipo' => 'transferencia',
                        'tipo_movimiento' => 'despacho_estados',
                        'origen_hospital_id' => self::CENTRAL_HOSPITAL_ID,
                        'origen_sede_id' => self::CENTRAL_SEDE_ID,
                        'destino_hospital_id' => null,
                        'destino_sede_id' => null,
                        'origen_almacen_tipo' => 'almacenCent',
                        'origen_almacen_id' => null,
                        'destino_almacen_tipo' => 'almacenAus',
                        'destino_almacen_id' => null,
                        'cantidad_salida_total' => $totalEstado,
                        'cantidad_entrada_total' => 0,
                        'discrepancia_total' => false,
                        'fecha_despacho' => $fecha,
                        'observaciones' => 'Despacho planificado hacia estado ' . $estado,
                        'estado' => 'pendiente',
                        'codigo_grupo' => $codigoGrupo,
                        'user_id' => $userId,
                        'user_id_receptor' => null,
                    ]);

                    $movimientosDespacho[] = [
                        'estado' => $estado,
                        'movimiento_id' => $movimiento->id,
                        'codigo' => $codigoGrupo,
                        'cantidad_total' => $totalEstado,
                        'detalle_insumos' => $detalleInsumos,
                        'items_lotes' => $grupoItems->map(function (LoteGrupo $grupo) {
                            return [
                                'lote_id' => $grupo->lote_id,
                                'cantidad_salida' => $grupo->cantidad_salida,
                            ];
                        })->toArray(),
                    ];
                }
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Importación de movimientos por estado procesada correctamente.',
                'data' => [
                    'insumos_totalizados' => count($totalesPorInsumo),
                    'cantidad_total' => (int) round($totalGlobal),
                    'movimiento_codigo' => $codigoMovimiento,
                    'movimientos_despacho' => $movimientosDespacho,
                    'por_estado' => array_map(function ($insumos) {
                        return array_sum(array_map('intval', $insumos));
                    }, $totalesPorEstado),
                    'omitidos' => $omitidos,
                    'errores' => $errores,
                    'insumos_no_encontrados' => $insumosSinCoincidencia,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al procesar el archivo: ' . $e->getMessage(),
                'data' => null,
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    private function parseHeaders($sheet): array
    {
        $headerRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, true, true)[1] ?? [];
        $headers = [
            'codigo' => null,
            'nombre' => null,
            'estados' => [],
        ];

        foreach ($headerRow as $columnLetter => $value) {
            $normalized = trim(mb_strtolower((string) $value));

            if ($normalized === 'codigo') {
                $headers['codigo'] = $columnLetter;
                continue;
            }

            if (in_array($normalized, ['nombre', 'descripcion', 'descripción del material', 'descripcion del material'], true)) {
                $headers['nombre'] = $columnLetter;
                continue;
            }

            if (str_starts_with($normalized, 'cantidad ')) {
                $estado = trim(mb_substr($normalized, strlen('cantidad ')));
                $estado = $estado !== '' ? Str::title($estado) : $columnLetter;
                $headers['estados'][$estado] = $columnLetter;
            }
        }

        return $headers;
    }

    private function getCellString($sheet, ?string $columnLetter, int $row): string
    {
        if (!$columnLetter) {
            return '';
        }
        $value = $sheet->getCell($columnLetter . $row)->getCalculatedValue();
        if ($value === null) {
            return '';
        }
        return trim((string) $value);
    }

    private function getCellFloat($sheet, ?string $columnLetter, int $row): float
    {
        if (!$columnLetter) {
            return 0.0;
        }
        $value = $sheet->getCell($columnLetter . $row)->getCalculatedValue();
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,\.]+/', '', (string) $value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function buscarInsumoPorCodigo(string $codigo): ?Insumo
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $insumo = Insumo::where('codigo', $codigo)->first();
        if ($insumo) {
            return $insumo;
        }

        return Insumo::where('codigo_alterno', $codigo)->first();
    }

    private function obtenerLoteCentral(int $insumoId, Carbon $fecha): Lote
    {
        $numeroLote = 'ESTADOS-' . str_pad((string) $insumoId, 6, '0', STR_PAD_LEFT);

        return Lote::firstOrCreate(
            [
                'id_insumo' => $insumoId,
                'numero_lote' => $numeroLote,
                'hospital_id' => self::CENTRAL_HOSPITAL_ID,
            ],
            [
                'fecha_vencimiento' => null,
                'fecha_ingreso' => $fecha->copy()->startOfDay()->toDateString(),
            ]
        );
    }
}
