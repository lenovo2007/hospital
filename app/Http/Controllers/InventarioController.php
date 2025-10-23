<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\AlmacenCentral;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

class InventarioController extends Controller
{
    /**
     * Registra un nuevo lote y lo asigna a un almacén específico.
     * 
     * @bodyParam insumo_id int required ID del insumo. Example: 1
     * @bodyParam lote_cod string required Código de lote. Example: "LOT-2024-001"
     * @bodyParam fecha_vencimiento string required Fecha de vencimiento (YYYY-MM-DD). Example: "2024-12-31"
     * @bodyParam almacen_tipo string required Tipo de almacén (ej: 'farmacia', 'principal'). Example: "farmacia"
     * @bodyParam almacen_id int required ID del almacén específico. Example: 1
     * @bodyParam cantidad int required Cantidad a registrar. Example: 100
     * @bodyParam hospital_id int required ID del hospital. Example: 1
     * @bodyParam sede_id int required ID de la sede. Example: 1
     * 
     * @response 200 {
     *     "status": true,
     *     "mensaje": "Inventario registrado exitosamente",
     *     "data": {
     *         "lote_id": 1,
     *         "lote_almacen_id": 1
     *     }
     * }
     */

    /**
     * Importar inventario desde archivo Excel (.xls o .xlsx) con el siguiente formato de columnas:
     * A:NOMBRE DEL INSUMO | B:PRESENTACION | C:LOTE | D:FECHA DE VENC. | E:TOTAL UNIDADES | F:FECHA (fecha ingreso)
     * Reglas:
     * - Buscar/crear el insumo por nombre (columna A).
     * - Crear/obtener el lote por (insumo_id, numero_lote, hospital_id).
     * - Registrar/Incrementar stock en almacenes_centrales (sede/hospital) sumando cantidad.
     * - Las fechas inválidas se ignoran (se guardan como NULL).
     *
     * Parámetros del request:
     * - hospital_id (opcional, por defecto: 1)
     * - sede_id (opcional, por defecto: 1)
     */
    public function importExcel(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:10240'],
            'hospital_id' => ['nullable', 'integer', 'exists:hospitales,id'],
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
        ]);

        // Valores por defecto: hospital_id=1 (almacén central), sede_id=1
        $validated['hospital_id'] = $validated['hospital_id'] ?? 1;
        $validated['sede_id'] = $validated['sede_id'] ?? 1;

        // Verificar dependencias de PhpSpreadsheet
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Dependencia faltante: phpoffice/phpspreadsheet. Ejecute: composer require phpoffice/phpspreadsheet',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            @ini_set('memory_limit', '512M');
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $path = $file->getRealPath();

            // Detectar tipo de lector automáticamente (Xls o Xlsx)
            $inputType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($path);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();

            // Columnas según especificación actualizada
            $colNombre = 'A';          // NOMBRE DEL INSUMO
            $colPresentacion = 'B';    // PRESENTACION
            $colLote = 'C';            // LOTE
            $colFechaVenc = 'D';       // FECHA DE VENC.
            $colTotalUnidades = 'E';   // TOTAL UNIDADES
            $colFechaIngreso = 'F';    // FECHA (fecha ingreso)

            $createdInsumos = 0; $updatedStock = 0; $createdLotes = 0; $skipped = []; $errores = [];
            $rowCount = (int) $sheet->getHighestRow();

            // Helper para parsear fechas desde Excel (serial o string)
            $parseFecha = function ($raw) {
                try {
                    if ($raw === null || $raw === '') { return null; }
                    
                    // Convertir a string y verificar patrones inválidos
                    $s = trim((string) $raw);
                    // Detectar fechas inválidas como 00/00/0000, 0/0/0, etc.
                    if (preg_match('/^0+[\/\-]0+[\/\-]0+$/', $s)) { return null; }
                    
                    if (is_numeric($raw)) {
                        // Validar que el número serial sea válido (mayor a 0)
                        if ($raw <= 0) { return null; }
                        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($raw);
                        if (!$dt) { return null; }
                        // Validar que el año sea razonable (entre 1900 y 2100)
                        $year = (int) $dt->format('Y');
                        if ($year < 1900 || $year > 2100) { return null; }
                        return $dt->format('Y-m-d');
                    }
                    
                    // Intentar varios formatos comunes
                    $fmts = ['Y-m-d','d/m/Y','n/j/Y','m/d/Y','d-m-Y','m-d-Y'];
                    foreach ($fmts as $fmt) {
                        $dt = \DateTime::createFromFormat($fmt, $s);
                        if ($dt && $dt->format($fmt) === $s) {
                            // Validar que el año sea razonable
                            $year = (int) $dt->format('Y');
                            if ($year < 1900 || $year > 2100) { return null; }
                            return $dt->format('Y-m-d');
                        }
                    }
                    
                    // Fallback parse
                    $ts = strtotime($s);
                    if ($ts && $ts > 0) {
                        $year = (int) date('Y', $ts);
                        if ($year >= 1900 && $year <= 2100) {
                            return date('Y-m-d', $ts);
                        }
                    }
                    return null;
                } catch (\Throwable $e) { return null; }
            };

            for ($i = 2; $i <= $rowCount; $i++) { // Asumimos encabezado en fila 1
                try {
                    $nombre = trim((string) ($sheet->getCell($colNombre.$i)->getValue() ?? ''));
                    if ($nombre === '') { $skipped[] = ['fila' => $i, 'motivo' => 'Nombre del insumo vacío']; continue; }

                    $presentacion = trim((string) ($sheet->getCell($colPresentacion.$i)->getValue() ?? ''));
                    $loteCod = trim((string) ($sheet->getCell($colLote.$i)->getValue() ?? ''));
                    if ($loteCod === '') { $skipped[] = ['fila' => $i, 'motivo' => 'Código de lote vacío']; continue; }

                    // Parsear fechas (pueden ser NULL si son inválidas)
                    $fechaVenc = $parseFecha($sheet->getCell($colFechaVenc.$i)->getValue());
                    $fechaIngreso = $parseFecha($sheet->getCell($colFechaIngreso.$i)->getValue());

                    // Cantidad (directamente de columna E)
                    $totalUnidades = $sheet->getCell($colTotalUnidades.$i)->getValue();
                    $total = is_numeric($totalUnidades) ? (int) $totalUnidades : 0;
                    if ($total <= 0) { $skipped[] = ['fila' => $i, 'motivo' => 'Cantidad total no válida']; continue; }

                    // Buscar/crear insumo por nombre exacto
                    $insumo = Insumo::where('nombre', $nombre)->first();
                    if (!$insumo) {
                        // Determinar tipo básico por presentación
                        $tipo = 'medico_quirurgico';
                        $farmas = ['SUSPENSION','SUSPENSIÓN','TABLETA','AMPOLLA','JARABE','CREMA','GOTAS','CAPSULA','CÁPSULA','FRASCO'];
                        if ($presentacion && in_array(strtoupper($presentacion), $farmas, true)) { $tipo = 'farmaceutico'; }

                        // Crear insumo sin código, se asignará después
                        $insumo = Insumo::create([
                            'codigo' => null,
                            'codigo_alterno' => null,
                            'nombre' => $nombre,
                            'tipo' => $tipo,
                            'unidad_medida' => 'unidades',
                            'cantidad_por_paquete' => 1,
                            'presentacion' => $presentacion ?: null,
                            'status' => 'activo',
                        ]);
                        
                        // Asignar codigo = id y codigo_alterno automático
                        $insumo->codigo = (string) $insumo->id;
                        $insumo->codigo_alterno = 'ALT-' . str_pad($insumo->id, 6, '0', STR_PAD_LEFT);
                        $insumo->save();
                        
                        $createdInsumos++;
                    }

                    // Buscar o crear lote (por insumo, lote y hospital)
                    $lote = Lote::where('id_insumo', $insumo->id)
                        ->where('numero_lote', $loteCod)
                        ->where('hospital_id', $validated['hospital_id'])
                        ->first();

                    if (!$lote) {
                        $lote = Lote::create([
                            'id_insumo' => $insumo->id,
                            'numero_lote' => $loteCod,
                            'fecha_vencimiento' => $fechaVenc, // NULL si es inválida
                            'fecha_ingreso' => $fechaIngreso,  // NULL si es inválida
                            'hospital_id' => $validated['hospital_id'],
                        ]);
                        $createdLotes++;
                    } else {
                        // Actualizar fechas solo si vienen válidas
                        $dirty = false;
                        if ($fechaVenc && $lote->fecha_vencimiento != $fechaVenc) { $lote->fecha_vencimiento = $fechaVenc; $dirty = true; }
                        if ($fechaIngreso && $lote->fecha_ingreso != $fechaIngreso) { $lote->fecha_ingreso = $fechaIngreso; $dirty = true; }
                        if ($dirty) { $lote->save(); }
                    }

                    // Registrar/Incrementar en almacén central (sumar si existe)
                    $res = $this->registrarEnAlmacenEspecifico([
                        'almacen_tipo' => 'almacenCent',
                        'cantidad' => $total,
                        'hospital_id' => $validated['hospital_id'],
                        'sede_id' => $validated['sede_id'],
                    ], $lote->id);

                    $updatedStock++;
                } catch (\Throwable $e) {
                    $errores[] = ['fila' => $i, 'error' => $e->getMessage()];
                }
            }

            // Liberar memoria
            if (isset($spreadsheet)) { $spreadsheet->disconnectWorksheets(); unset($spreadsheet); $reader = null; }

            return response()->json([
                'status' => true,
                'mensaje' => 'Importación de inventario procesada.',
                'data' => [
                    'insumos_creados' => $createdInsumos,
                    'lotes_creados' => $createdLotes,
                    'registros_stock_actualizados' => $updatedStock,
                    'omitidos' => $skipped,
                    'errores' => $errores,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al procesar el archivo: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }
    public function registrar(Request $request)
    {
        $validated = $request->validate([
            'insumo_id' => 'required|exists:insumos,id',
            'lote_cod' => 'required|string|max:100',
            'fecha_vencimiento' => 'required|date_format:Y-m-d',
            'fecha_ingreso' => 'nullable|date_format:Y-m-d',
            'almacen_tipo' => 'required|string|in:almacenCent,almacenPrin,almacenFarm,almacenPar,almacenServAtenciones,almacenServApoyo',
            'cantidad' => 'required|integer|min:1',
            'hospital_id' => 'required|exists:hospitales,id',
            'sede_id' => 'required|exists:sedes,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $lote = Lote::create([
                'id_insumo' => $validated['insumo_id'],
                'numero_lote' => $validated['lote_cod'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                'fecha_ingreso' => $validated['fecha_ingreso'] ?? now(),
                'hospital_id' => $validated['hospital_id'],
            ]);

            $registroAlmacen = $this->registrarEnAlmacenEspecifico($validated, $lote->id);

            return response()->json([
                'status' => true,
                'mensaje' => 'Inventario registrado exitosamente',
                'data' => [
                    'lote_id' => $lote->id,
                    'almacen_tipo' => $validated['almacen_tipo'],
                    'registro_almacen_id' => $registroAlmacen['id'],
                    'cantidad_total' => $registroAlmacen['cantidad'],
                ],
            ], 201);
        });
    }

    /**
     * Obtiene el inventario agrupado por insumo_id para una sede específica.
     * 
     * @urlParam sede_id int required ID de la sede. Example: 1
     * 
     * @response 200 {
     *     "status": true,
     *     "data": [
     *         {
     *             "insumo_id": 1,
     *             "codigo": "INS-001",
     *             "nombre": "Insumo Ejemplo",
     *             "cantidad_total": 150,
     *             "lotes": [
     *                 {
     *                     "lote_id": 1,
     *                     "numero_lote": "LOT-2024-001",
     *                     "fecha_vencimiento": "2024-12-31",
     *                     "cantidad": 100
     *                 },
     *                 {
     *                     "lote_id": 2,
     *                     "numero_lote": "LOT-2024-002",
     *                     "fecha_vencimiento": "2024-11-30",
     *                     "cantidad": 50
     *                 }
     *             ]
     *         }
     *     ]
     * }
     */
    public function listarPorSede($sedeId)
    {
        try {
            // Obtener el tipo de almacén de la sede
            $sede = DB::table('sedes')->where('id', $sedeId)->first();
            if (!$sede) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Sede no encontrada.',
                    'data' => [],
                ], 200);
            }

            // Determinar la tabla según el tipo de almacén
            $tabla = match ($sede->tipo_almacen) {
                'almacenCent' => 'almacenes_centrales',
                'almacenPrin' => 'almacenes_principales',
                'almacenFarm' => 'almacenes_farmacia',
                'almacenPar' => 'almacenes_paralelo',
                'almacenServApoyo' => 'almacenes_servicios_apoyo',
                'almacenServAtenciones' => 'almacenes_servicios_atenciones',
                default => null,
            };

            if (!$tabla || !Schema::hasTable($tabla)) {
                return response()->json([
                    'status' => false,
                    'mensaje' => "No se encontró la tabla de almacén para el tipo: {$sede->tipo_almacen}",
                    'data' => [],
                ], 200);
            }

            // Esquema objetivo: almacenes_centrales(cantidad, sede_id, lote_id, hospital_id, status)
            // Se deriva el insumo a través de lotes.id_insumo

            // 1) Totales por insumo en la sede
            $resumenInsumos = DB::table($tabla)
                ->join('lotes', "$tabla.lote_id", '=', 'lotes.id')
                ->join('insumos', 'lotes.id_insumo', '=', 'insumos.id')
                ->where("$tabla.sede_id", $sedeId)
                ->where("$tabla.status", true)
                ->select(
                    'insumos.id as insumo_id',
                    DB::raw("SUM($tabla.cantidad) as cantidad_total")
                )
                ->groupBy('insumos.id')
                ->get();

            if ($resumenInsumos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No hay registros de inventario para la sede especificada.',
                    'data' => [],
                ], 200);
            }

            // 2) Lotes por insumo en la sede
            $insumoIds = $resumenInsumos->pluck('insumo_id')->all();

            $insumos = Insumo::whereIn('id', $insumoIds)->get()->keyBy('id');

            $lotesPorInsumo = DB::table($tabla)
                ->join('lotes', "$tabla.lote_id", '=', 'lotes.id')
                ->where("$tabla.sede_id", $sedeId)
                ->where("$tabla.status", true)
                ->whereIn('lotes.id_insumo', $insumoIds)
                ->select(
                    DB::raw('lotes.id_insumo as insumo_id'),
                    'lotes.id as lote_id',
                    'lotes.numero_lote',
                    'lotes.fecha_vencimiento',
                    DB::raw("$tabla.cantidad as cantidad")
                )
                ->get()
                ->groupBy('insumo_id');

            // 3) Armar respuesta
            $data = $resumenInsumos->map(function ($row) use ($lotesPorInsumo, $insumos) {
                $detalleInsumo = $insumos->get($row->insumo_id);
                $lotes = optional($lotesPorInsumo->get($row->insumo_id))
                    ? $lotesPorInsumo->get($row->insumo_id)->map(function ($lote) {
                        return [
                            'lote_id' => $lote->lote_id,
                            'numero_lote' => $lote->numero_lote,
                            'fecha_vencimiento' => $lote->fecha_vencimiento,
                            'cantidad' => (int) $lote->cantidad,
                        ];
                    })->values()->all()
                    : [];

                return [
                    'insumo_id' => $row->insumo_id,
                    'insumo' => $detalleInsumo ? $detalleInsumo->toArray() : null,
                    'cantidad_total' => (int) $row->cantidad_total,
                    'lotes' => $lotes,
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (Throwable $e) {
            Log::error('Error en listarPorSede', [
                'exception' => $e,
                'sedeId' => $sedeId,
            ]);
            // En entorno local, incluir mensaje de error para diagnóstico
            $mensaje = 'Error al listar inventario por sede';
            if (app()->environment('local')) {
                $mensaje .= ': ' . $e->getMessage();
            }
            return response()->json([
                'status' => false,
                'mensaje' => $mensaje,
                'data' => null,
            ], 200);
        }
    }

    // Método opcional para registrar en tablas específicas de almacén
    protected function registrarEnAlmacenEspecifico(array $data, int $loteId): array
    {
        $tabla = match ($data['almacen_tipo']) {
            'almacenCent' => 'almacenes_centrales',
            'almacenPrin' => 'almacenes_principales',
            'almacenFarm' => 'almacenes_farmacia',
            'almacenPar' => 'almacenes_paralelo',
            'almacenServApoyo' => 'almacenes_servicios_apoyo',
            'almacenServAtenciones' => 'almacenes_servicios_atenciones',
            default => null,
        };

        if (!$tabla || !Schema::hasTable($tabla)) {
            throw new \RuntimeException("No se encontró la tabla destino para el tipo de almacén {$data['almacen_tipo']}");
        }

        $clave = [
            'sede_id' => $data['sede_id'],
            'lote_id' => $loteId,
            'hospital_id' => $data['hospital_id'],
        ];

        $cantidad = (int) $data['cantidad'];

        $registroExistente = DB::table($tabla)
            ->where($clave)
            ->lockForUpdate()
            ->first();

        if ($registroExistente) {
            DB::table($tabla)
                ->where('id', $registroExistente->id)
                ->increment('cantidad', $cantidad, [
                    'status' => true,
                    'updated_at' => now(),
                ]);

            $nuevoTotal = (int) $registroExistente->cantidad + $cantidad;

            return [
                'id' => $registroExistente->id,
                'cantidad' => $nuevoTotal,
            ];
        }

        $id = DB::table($tabla)->insertGetId(array_merge($clave, [
            'cantidad' => $cantidad,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return [
            'id' => $id,
            'cantidad' => $cantidad,
        ];
    }
}
