<?php

namespace App\Http\Controllers;

use App\Models\FichaInsumo;
use App\Models\Hospital;
use App\Models\Lote;
use App\Models\LoteGrupo;
use App\Models\MovimientoStock;
use App\Models\MovimientoDiscrepancia;
use App\Models\TipoHospitalDistribucion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class RecepcionPrincipalController extends Controller
{
    public function redistribuir(Request $request)
    {
        $data = $request->validate([
            'movimiento_stock_id' => ['required', 'integer', 'min:1'],
            'fecha_despacho' => ['nullable', 'date'],
        ]);

        $userId = (int) $request->user()->id;
        $movimientoId = (int) $data['movimiento_stock_id'];
        $fecha = isset($data['fecha_despacho']) ? (string) $data['fecha_despacho'] : (string) now()->toDateString();

        try {
            Log::info('RecepcionPrincipalController@redistribuir: request', [
                'movimiento_stock_id' => $movimientoId,
                'user_id' => $userId,
                'fecha_despacho' => $fecha,
            ]);

            $movimiento = MovimientoStock::query()->where('id', $movimientoId)->first();
            if (!$movimiento) {
                throw new InvalidArgumentException('No existe un movimiento con el ID indicado.');
            }

            if (!$movimiento->codigo_grupo) {
                throw new InvalidArgumentException('El movimiento no tiene codigo_grupo para reconstruir items.');
            }

            $itemsGrupo = LoteGrupo::query()
                ->where('codigo', (string) $movimiento->codigo_grupo)
                ->where('status', 'activo')
                ->get(['lote_id', 'cantidad_salida', 'cantidad_entrada']);

            if ($itemsGrupo->isEmpty()) {
                throw new InvalidArgumentException('No se encontraron items asociados al codigo_grupo del movimiento.');
            }

            $items = [];
            foreach ($itemsGrupo as $it) {
                $cantidad = (int) ($it->cantidad_entrada > 0 ? $it->cantidad_entrada : $it->cantidad_salida);
                if ($cantidad <= 0) {
                    continue;
                }
                $items[] = [
                    'lote_id' => (int) $it->lote_id,
                    'cantidad' => $cantidad,
                ];
            }

            if (empty($items)) {
                throw new InvalidArgumentException('Los items del grupo no tienen cantidades válidas para redistribuir.');
            }

            $resumen = $this->distribuirAutomaticoDesdeAusSiAplica($movimiento, $items, $userId, $fecha);

            return response()->json([
                'status' => true,
                'mensaje' => 'Redistribución procesada.',
                'data' => [
                    'movimiento_stock_id' => $movimientoId,
                    'codigo_grupo' => (string) $movimiento->codigo_grupo,
                    'distribucion' => $resumen,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Log::error('RecepcionPrincipalController@redistribuir: error', [
                'movimiento_stock_id' => $movimientoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al redistribuir: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function recibir(Request $request)
    {
        $data = $request->validate([
            'movimiento_stock_id' => ['required', 'integer', 'min:1'],
            'fecha_recepcion' => ['required', 'date'],
            'user_id_receptor' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.lote_id' => ['required', 'integer', 'min:1'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->user()->id;
        $resultado = [
            'estado' => null,
            'discrepancias' => [],
            'distribucion' => null,
        ];

        try {
            Log::info('RecepcionPrincipalController@recibir: request', [
                'movimiento_stock_id' => (int) $data['movimiento_stock_id'],
                'items_count' => is_array($data['items']) ? count($data['items']) : null,
                'user_id' => $userId,
            ]);

            $payloadDistribucion = null;

            DB::transaction(function () use ($data, $userId, &$resultado, &$payloadDistribucion) {
                // Buscar el movimiento por ID
                $movimiento = MovimientoStock::where('id', $data['movimiento_stock_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$movimiento) {
                    throw new InvalidArgumentException('No existe un movimiento con el ID indicado.');
                }

                // Validar estado SOLO cuando el destino sea almacén AUS.
                // Para el resto de destinos, no se bloquea por estado.
                if ((string) $movimiento->destino_almacen_tipo === 'almacenAus') {
                    if ((string) $movimiento->estado !== 'entregado') {
                        throw new InvalidArgumentException('El movimiento debe estar en estado entregado para poder ser recibido en almacenAus. Estado actual: ' . (string) $movimiento->estado);
                    }
                }

                // Buscar los lotes grupos asociados al movimiento
                $itemsEsperados = LoteGrupo::where('codigo', $movimiento->codigo_grupo)
                    ->lockForUpdate()
                    ->get();

                if ($itemsEsperados->isEmpty()) {
                    throw new InvalidArgumentException('No se encontraron items asociados al código de grupo.');
                }

                // Validar que los items estén activos (la validación de entrega se hace a nivel de movimiento)
                $itemsInactivos = $itemsEsperados->where('status', 'inactivo');
                if ($itemsInactivos->isNotEmpty()) {
                    throw new InvalidArgumentException('Hay items inactivos que no pueden ser procesados.');
                }

                $mapaEsperado = [];
                foreach ($itemsEsperados as $item) {
                    $mapaEsperado[(int) $item->lote_id] = [
                        'cantidad' => (int) $item->cantidad_salida,
                        'modelo' => $item,
                    ];
                }

                $itemsRecibidos = $data['items'];

                $mapaRecibido = [];
                foreach ($itemsRecibidos as $item) {
                    $mapaRecibido[(int) $item['lote_id']] = (int) $item['cantidad'];
                }

                $totalCantidadEntradaAplicada = 0;

                $aceptados = [];
                $discrepancias = [];

                foreach ($mapaEsperado as $loteId => $info) {
                    $cantidadEsperada = $info['cantidad'];
                    $cantidadRecibida = $mapaRecibido[$loteId] ?? null;

                    if ($cantidadRecibida === null || $cantidadRecibida !== $cantidadEsperada) {
                        $discrepancias[] = [
                            'lote_id' => $loteId,
                            'cantidad_esperada' => $cantidadEsperada,
                            'cantidad_recibida' => $cantidadRecibida ?? 0,
                        ];
                    } else {
                        $aceptados[$loteId] = $info;
                    }
                }

                foreach ($mapaRecibido as $loteId => $cantidadRecibida) {
                    if (!array_key_exists($loteId, $mapaEsperado)) {
                        $discrepancias[] = [
                            'lote_id' => $loteId,
                            'cantidad_esperada' => 0,
                            'cantidad_recibida' => $cantidadRecibida,
                        ];
                    }
                }

                // Procesar todos los items recibidos (aceptados y con discrepancia)
                foreach ($itemsRecibidos as $item) {
                    $loteId = (int) $item['lote_id'];
                    $cantidadRecibida = (int) $item['cantidad'];
                    
                    // Buscar el modelo del lote grupo
                    $loteGrupo = $itemsEsperados->firstWhere('lote_id', $loteId);
                    if (!$loteGrupo) {
                        continue; // Skip items no esperados
                    }
                    
                    $cantidadEsperada = (int) $loteGrupo->cantidad_salida;
                    $cantidadAplicada = min($cantidadRecibida, $cantidadEsperada);
                    $tieneDiscrepancia = $cantidadRecibida !== $cantidadEsperada;

                    $totalCantidadEntradaAplicada += $cantidadAplicada;

                    // Determinar tabla de destino según el tipo de almacén
                    $tablaDestino = $this->obtenerTablaAlmacen($movimiento->destino_almacen_tipo);
                    
                    // Actualizar stock en la tabla de destino correspondiente
                    $registroDestino = DB::table($tablaDestino)
                        ->where([
                            'hospital_id' => $movimiento->destino_hospital_id,
                            'sede_id' => $movimiento->destino_sede_id,
                            'lote_id' => $loteId,
                        ])
                        ->lockForUpdate()
                        ->first();

                    if ($registroDestino) {
                        $nuevaCantidad = (int) $registroDestino->cantidad + $cantidadAplicada;
                        DB::table($tablaDestino)
                            ->where('id', $registroDestino->id)
                            ->update([
                                'cantidad' => $nuevaCantidad,
                                'status' => $nuevaCantidad > 0 ? 1 : 0,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table($tablaDestino)->insert([
                            'hospital_id' => $movimiento->destino_hospital_id,
                            'sede_id' => $movimiento->destino_sede_id,
                            'lote_id' => $loteId,
                            'cantidad' => $cantidadAplicada,
                            'status' => $cantidadAplicada > 0 ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Actualizar lote grupo con cantidad_entrada y discrepancia
                    $loteGrupo->update([
                        'cantidad_entrada' => $cantidadAplicada,
                        'discrepancia' => $tieneDiscrepancia,
                    ]);

                    // Si hay discrepancia, registrarla en la tabla movimientos_discrepancias
                    if ($tieneDiscrepancia) {
                        $discrepancias[] = [
                            'lote_id' => $loteId,
                            'cantidad_esperada' => $cantidadEsperada,
                            'cantidad_recibida' => $cantidadRecibida,
                        ];
                        
                        MovimientoDiscrepancia::create([
                            'movimiento_stock_id' => $movimiento->id,
                            'codigo_lote_grupo' => $loteGrupo->codigo,
                            'cantidad_esperada' => $cantidadEsperada,
                            'cantidad_recibida' => $cantidadRecibida,
                            'observaciones' => "Discrepancia automática: esperado {$cantidadEsperada}, recibido {$cantidadRecibida}, aplicado {$cantidadAplicada}"
                        ]);
                    }
                }

                // Verificar lotes esperados que no fueron recibidos (faltantes)
                foreach ($itemsEsperados as $loteEsperado) {
                    $loteId = (int) $loteEsperado->lote_id;
                    $fueRecibido = collect($itemsRecibidos)->contains('lote_id', $loteId);
                    
                    if (!$fueRecibido) {
                        // Lote faltante - discrepancia total
                        $cantidadEsperada = (int) $loteEsperado->cantidad_salida;
                        
                        $discrepancias[] = [
                            'lote_id' => $loteId,
                            'cantidad_esperada' => $cantidadEsperada,
                            'cantidad_recibida' => 0,
                        ];
                        
                        // Actualizar lote grupo como no recibido
                        $loteEsperado->update([
                            'cantidad_entrada' => 0,
                            'discrepancia' => true,
                        ]);
                        
                        // Registrar discrepancia
                        MovimientoDiscrepancia::create([
                            'movimiento_stock_id' => $movimiento->id,
                            'codigo_lote_grupo' => $loteEsperado->codigo,
                            'cantidad_esperada' => $cantidadEsperada,
                            'cantidad_recibida' => 0,
                            'observaciones' => "Lote faltante: esperado {$cantidadEsperada}, no recibido"
                        ]);
                    }
                }

                $totalCantidadEntrada = (int) $totalCantidadEntradaAplicada;

                // Verificar si hay discrepancia total
                $totalCantidadSalida = (int) $movimiento->cantidad_salida_total;
                $hayDiscrepanciaTotal = $totalCantidadEntrada !== $totalCantidadSalida;

                // Determinar estado final: siempre será 'recibido' ya que se completó la recepción
                $hayDiscrepanciasIndividuales = !empty($discrepancias);
                $estadoFinal = 'recibido';

                $movimiento->update([
                    'estado' => $estadoFinal,
                    'fecha_recepcion' => $data['fecha_recepcion'],
                    'cantidad_entrada_total' => $totalCantidadEntrada,
                    'discrepancia_total' => $hayDiscrepanciaTotal,
                    'user_id_receptor' => $data['user_id_receptor'],
                ]);

                $payloadDistribucion = [
                    'movimiento' => $movimiento,
                    'items' => $itemsRecibidos,
                    'user_id' => $userId,
                    'fecha' => (string) $data['fecha_recepcion'],
                ];


                $resultado['estado'] = $estadoFinal;
                $resultado['discrepancias'] = $discrepancias;
            });

            if ($payloadDistribucion) {
                try {
                    $resultado['distribucion'] = $this->distribuirAutomaticoDesdeAusSiAplica(
                        $payloadDistribucion['movimiento'],
                        $payloadDistribucion['items'],
                        (int) $payloadDistribucion['user_id'],
                        (string) $payloadDistribucion['fecha']
                    );

                    Log::info('RecepcionPrincipalController@recibir: distribucion_resumen', [
                        'movimiento_stock_id' => (int) $payloadDistribucion['movimiento']->id,
                        'destino_almacen_tipo' => (string) $payloadDistribucion['movimiento']->destino_almacen_tipo,
                        'destino_hospital_id' => (int) $payloadDistribucion['movimiento']->destino_hospital_id,
                        'destino_sede_id' => (int) $payloadDistribucion['movimiento']->destino_sede_id,
                        'resumen' => $resultado['distribucion'],
                    ]);
                } catch (Throwable $e) {
                    $resultado['distribucion'] = [
                        'aplico' => false,
                        'motivo' => 'Error inesperado en distribución automática',
                        'movimientos_creados' => 0,
                        'errores' => [$e->getMessage()],
                    ];

                    Log::error('RecepcionPrincipalController@recibir: distribucion_error', [
                        'movimiento_stock_id' => (int) $payloadDistribucion['movimiento']->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'status' => true,
                'mensaje' => empty($resultado['discrepancias'])
                    ? 'Recepción confirmada y stock actualizado.'
                    : 'Recepción registrada con discrepancias. Se generó un reporte automático.',
                'data' => $resultado,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error en recepción principal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar la recepción: ' . $e->getMessage(),
                'data' => null,
            ], 200);
        }
    }

    public function registrarLotesReales(Request $request)
    {
        $data = $request->validate([
            'movimiento_stock_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.lote_id_origen' => ['required', 'integer', 'min:1'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.numero_lote' => ['nullable', 'string', 'max:100'],
            'items.*.fecha_vencimiento' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $movimientoId = (int) $data['movimiento_stock_id'];
        $userId = (int) $request->user()->id;

        try {
            $resultado = DB::transaction(function () use ($data, $movimientoId, $userId) {
                $movimiento = MovimientoStock::query()
                    ->where('id', $movimientoId)
                    ->lockForUpdate()
                    ->first();

                if (!$movimiento) {
                    throw new InvalidArgumentException('No existe un movimiento con el ID indicado.');
                }

                if ((string) $movimiento->destino_almacen_tipo !== 'almacenPrin') {
                    throw new InvalidArgumentException('Este movimiento no tiene destino almacenPrin.');
                }

                if ((string) $movimiento->estado !== 'recibido') {
                    throw new InvalidArgumentException('El movimiento debe estar en estado recibido para registrar lotes reales.');
                }

                $hospitalId = (int) $movimiento->destino_hospital_id;
                $sedeId = (int) $movimiento->destino_sede_id;
                if ($hospitalId <= 0 || $sedeId <= 0) {
                    throw new InvalidArgumentException('El movimiento no tiene destino_hospital_id o destino_sede_id válidos.');
                }

                $agregado = [];
                foreach ($data['items'] as $it) {
                    $loteOrigen = (int) $it['lote_id_origen'];
                    $numeroLote = isset($it['numero_lote']) ? trim((string) $it['numero_lote']) : '';
                    $fechaVenc = isset($it['fecha_vencimiento']) ? (string) $it['fecha_vencimiento'] : '';

                    if (($numeroLote !== '' && $fechaVenc === '') || ($numeroLote === '' && $fechaVenc !== '')) {
                        throw new InvalidArgumentException('Si envías numero_lote debes enviar fecha_vencimiento (y viceversa).');
                    }

                    $tieneDatosReales = $numeroLote !== '' && $fechaVenc !== '';
                    $key = $tieneDatosReales
                        ? ($loteOrigen . '|' . $numeroLote . '|' . $fechaVenc)
                        : ($loteOrigen . '|SIN_LOTE_REAL');

                    $agregado[$key] = [
                        'lote_id_origen' => $loteOrigen,
                        'numero_lote' => $tieneDatosReales ? $numeroLote : null,
                        'fecha_vencimiento' => $tieneDatosReales ? $fechaVenc : null,
                        'sin_lote_real' => !$tieneDatosReales,
                        'cantidad' => ((int) ($agregado[$key]['cantidad'] ?? 0)) + (int) $it['cantidad'],
                    ];
                }

                $origenIds = array_values(array_unique(array_map(fn ($r) => (int) $r['lote_id_origen'], $agregado)));
                $lotesOrigen = Lote::query()
                    ->whereIn('id', $origenIds)
                    ->get(['id', 'id_insumo']);

                $createdLotes = 0;
                $movidos = [];
                $sinCambio = [];
                $discrepancias = [];

                foreach ($agregado as $row) {
                    $loteIdOrigen = (int) $row['lote_id_origen'];
                    $cantidad = (int) $row['cantidad'];
                    if ($cantidad <= 0) {
                        continue;
                    }

                    if (($row['sin_lote_real'] ?? false) === true) {
                        $sinCambio[] = [
                            'lote_id_origen' => $loteIdOrigen,
                            'cantidad' => $cantidad,
                            'accion' => 'sin_cambio',
                        ];
                        continue;
                    }

                    $loteOrigenModel = $lotesOrigen->firstWhere('id', $loteIdOrigen);
                    if (!$loteOrigenModel) {
                        throw new InvalidArgumentException('No existe lote origen id=' . $loteIdOrigen);
                    }
                    $insumoId = (int) $loteOrigenModel->id_insumo;

                    $loteReal = Lote::query()
                        ->where('id_insumo', $insumoId)
                        ->where('numero_lote', (string) $row['numero_lote'])
                        ->where('hospital_id', $hospitalId)
                        ->first();

                    if (!$loteReal) {
                        $loteReal = Lote::create([
                            'id_insumo' => $insumoId,
                            'numero_lote' => (string) $row['numero_lote'],
                            'fecha_vencimiento' => (string) $row['fecha_vencimiento'],
                            'fecha_ingreso' => now()->toDateString(),
                            'hospital_id' => $hospitalId,
                        ]);
                        $createdLotes++;
                    }

                    if ($loteReal->fecha_vencimiento && $loteReal->fecha_vencimiento->format('Y-m-d') !== (string) $row['fecha_vencimiento']) {
                        $loteReal->fecha_vencimiento = (string) $row['fecha_vencimiento'];
                        $loteReal->save();
                    }

                    $stockOrigen = DB::table('almacenes_principales')
                        ->where([
                            'hospital_id' => $hospitalId,
                            'sede_id' => $sedeId,
                            'lote_id' => $loteIdOrigen,
                        ])
                        ->lockForUpdate()
                        ->first();

                    $disp = (int) ($stockOrigen->cantidad ?? 0);
                    $cantidadAplicada = min($cantidad, $disp);
                    if ($cantidadAplicada < $cantidad) {
                        $discrepancias[] = [
                            'lote_id_origen' => $loteIdOrigen,
                            'cantidad_esperada' => $cantidad,
                            'cantidad_recibida' => $cantidadAplicada,
                            'observaciones' => 'Stock insuficiente al registrar lote real. Disponible=' . $disp . ' solicitado=' . $cantidad,
                        ];

                        if ($movimiento->codigo_grupo) {
                            MovimientoDiscrepancia::create([
                                'movimiento_stock_id' => (int) $movimiento->id,
                                'codigo_lote_grupo' => (string) $movimiento->codigo_grupo,
                                'cantidad_esperada' => (int) $cantidad,
                                'cantidad_recibida' => (int) $cantidadAplicada,
                                'observaciones' => 'Registrar lote real: lote_id_origen=' . $loteIdOrigen . ' numero_lote=' . (string) $row['numero_lote'] . ' vence=' . (string) $row['fecha_vencimiento'] . '. Disponible=' . $disp . ' solicitado=' . $cantidad,
                            ]);
                        }
                    }

                    if ($cantidadAplicada <= 0) {
                        $movidos[] = [
                            'lote_id_origen' => $loteIdOrigen,
                            'lote_id_real' => (int) $loteReal->id,
                            'numero_lote' => (string) $row['numero_lote'],
                            'fecha_vencimiento' => (string) $row['fecha_vencimiento'],
                            'cantidad' => 0,
                        ];
                        continue;
                    }

                    DB::table('almacenes_principales')
                        ->where('id', (int) $stockOrigen->id)
                        ->update([
                            'cantidad' => $disp - $cantidadAplicada,
                            'status' => ($disp - $cantidadAplicada) > 0 ? 1 : 0,
                            'updated_at' => now(),
                        ]);

                    $stockDestino = DB::table('almacenes_principales')
                        ->where([
                            'hospital_id' => $hospitalId,
                            'sede_id' => $sedeId,
                            'lote_id' => (int) $loteReal->id,
                        ])
                        ->lockForUpdate()
                        ->first();

                    if ($stockDestino) {
                        $nueva = (int) $stockDestino->cantidad + $cantidadAplicada;
                        DB::table('almacenes_principales')
                            ->where('id', (int) $stockDestino->id)
                            ->update([
                                'cantidad' => $nueva,
                                'status' => $nueva > 0 ? 1 : 0,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('almacenes_principales')->insert([
                            'hospital_id' => $hospitalId,
                            'sede_id' => $sedeId,
                            'lote_id' => (int) $loteReal->id,
                            'cantidad' => $cantidadAplicada,
                            'status' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $movidos[] = [
                        'lote_id_origen' => $loteIdOrigen,
                        'lote_id_real' => (int) $loteReal->id,
                        'numero_lote' => (string) $row['numero_lote'],
                        'fecha_vencimiento' => (string) $row['fecha_vencimiento'],
                        'cantidad' => $cantidadAplicada,
                    ];
                }

            MovimientoStock::query()
                ->where('id', $movimientoId)
                ->update([
                    'observaciones_recepcion' => 'Lotes reales registrados por user_id=' . $userId,
                    'updated_at' => now(),
                ]);

                return [
                    'movimiento_stock_id' => $movimientoId,
                    'created_lotes' => $createdLotes,
                    'movidos' => $movidos,
                    'sin_cambio' => $sinCambio,
                    'discrepancias' => $discrepancias,
                ];
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Lotes reales registrados y stock actualizado.',
                'data' => $resultado,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Log::error('RecepcionPrincipalController@registrarLotesReales: error', [
                'movimiento_stock_id' => $movimientoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar lotes reales: ' . $e->getMessage(),
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Obtiene el nombre de la tabla según el tipo de almacén
     */
    private function obtenerTablaAlmacen(string $tipoAlmacen): string
    {
        return match ($tipoAlmacen) {
            'almacenCent' => 'almacenes_centrales',
            'almacenPrin' => 'almacenes_principales',
            'almacenFarm' => 'almacenes_farmacia',
            'almacenAus' => 'almacenes_aus',
            'almacenPar' => 'almacenes_paralelo',
            'almacenServApoyo' => 'almacenes_servicios_apoyo',
            'almacenServAtenciones' => 'almacenes_servicios_atenciones',
            default => throw new InvalidArgumentException("Tipo de almacén no soportado: {$tipoAlmacen}"),
        };
    }

    /**
     * Obtiene los estados permitidos para recepción según el tipo de almacén origen
     */
    private function obtenerEstadosPermitidosParaRecepcion(string $origenAlmacenTipo): array
    {
        return match ($origenAlmacenTipo) {
            // Central → Principal: Requiere repartidor, debe estar entregado
            'almacenCent' => ['entregado'],
            
            // Principal → Otros: Movimiento interno, puede recibirse desde despachado
            'almacenPrin' => ['despachado', 'entregado'],
            
            // Otros tipos por si acaso (mantener lógica de repartidor)
            default => ['entregado'],
        };
    }

    private function distribuirAutomaticoDesdeAusSiAplica(MovimientoStock $movimiento, array $itemsRecibidos, int $userId, string $fechaDespacho): array
    {
        $resumen = [
            'aplico' => false,
            'motivo' => null,
            'movimientos_creados' => 0,
            'insumos_procesados' => 0,
            'hospitales_estado_total' => 0,
            'tipos_hospital_detectados' => [],
            'hospitales_elegibles_por_insumo' => [],
            'sobrante_por_insumo' => [],
            'sobrante_total' => 0,
            'omitidos' => [],
            'errores' => [],
        ];

        Log::info('DistribucionAutomaticaAUS: start', [
            'movimiento_stock_id' => (int) $movimiento->id,
            'destino_almacen_tipo' => (string) $movimiento->destino_almacen_tipo,
            'destino_hospital_id' => (int) $movimiento->destino_hospital_id,
            'destino_sede_id' => (int) $movimiento->destino_sede_id,
            'items_count' => is_array($itemsRecibidos) ? count($itemsRecibidos) : null,
        ]);

        if ($movimiento->destino_almacen_tipo !== 'almacenAus') {
            $resumen['motivo'] = 'No aplica: destino_almacen_tipo != almacenAus';
            return $resumen;
        }

        $resumen['aplico'] = true;

        $ausHospital = Hospital::query()->where('id', $movimiento->destino_hospital_id)->first();
        if (!$ausHospital) {
            $resumen['aplico'] = false;
            $resumen['motivo'] = 'No se encontró el hospital AUS destino para la distribución automática';
            $resumen['errores'][] = 'destino_hospital_id=' . (string) $movimiento->destino_hospital_id;
            return $resumen;
        }

        $estado = (string) ($ausHospital->estado ?? '');
        if (trim($estado) === '') {
            $resumen['aplico'] = false;
            $resumen['motivo'] = 'El hospital AUS destino no tiene estado configurado';
            return $resumen;
        }

        $porcentajes = TipoHospitalDistribucion::query()->first();
        if (!$porcentajes) {
            $resumen['aplico'] = false;
            $resumen['motivo'] = 'No se encontró configuración de porcentajes (tipos_hospital_distribuciones)';
            return $resumen;
        }

        $hospitalesEstado = Hospital::query()
            ->where('status', true)
            ->where('estado', $estado)
            ->where('id', '!=', (int) $ausHospital->id)
            ->get(['id', 'nombre', 'tipo']);

        $resumen['hospitales_estado_total'] = (int) $hospitalesEstado->count();
        $resumen['tipos_hospital_detectados'] = $hospitalesEstado
            ->pluck('tipo')
            ->map(fn ($t) => is_null($t) ? null : (string) $t)
            ->unique()
            ->values()
            ->all();

        if ($hospitalesEstado->isEmpty()) {
            $resumen['motivo'] = 'No hay hospitales activos en el estado para distribuir (excluyendo AUS)';
            return $resumen;
        }

        $loteIds = array_values(array_unique(array_map(fn ($i) => (int) $i['lote_id'], $itemsRecibidos)));
        if (empty($loteIds)) {
            $resumen['motivo'] = 'No hay lote_id en items recibidos';
            return $resumen;
        }

        $lotes = DB::table('lotes')
            ->whereIn('id', $loteIds)
            ->get(['id', 'id_insumo', 'fecha_vencimiento']);

        $insumoIds = $lotes->pluck('id_insumo')->unique()->values()->all();
        if (empty($insumoIds)) {
            $resumen['motivo'] = 'No se pudieron obtener insumos desde lotes';
            return $resumen;
        }

        $fichas = FichaInsumo::query()
            ->whereIn('hospital_id', $hospitalesEstado->pluck('id')->all())
            ->whereIn('insumo_id', $insumoIds)
            ->get(['hospital_id', 'insumo_id', 'status']);

        $fichaMap = [];
        foreach ($fichas as $f) {
            $fichaMap[(int) $f->hospital_id][(int) $f->insumo_id] = (bool) $f->status;
        }

        $cantidadPorInsumo = [];

        // Si el movimiento AUS viene de un despacho por estado con referencia al ingreso global,
        // usar los totales globales (sumatoria de todos los estados) para calcular porcentajes.
        if (!empty($movimiento->id_ingreso)) {
            $movIngresoGlobal = MovimientoStock::query()
                ->where('id', (int) $movimiento->id_ingreso)
                ->first();

            if ($movIngresoGlobal && !empty($movIngresoGlobal->codigo_grupo)) {
                $itemsIngreso = LoteGrupo::query()
                    ->where('codigo', (string) $movIngresoGlobal->codigo_grupo)
                    ->where('status', 'activo')
                    ->get(['lote_id', 'cantidad_salida', 'cantidad_entrada']);

                if ($itemsIngreso->isNotEmpty()) {
                    $loteIdsIngreso = $itemsIngreso->pluck('lote_id')->filter()->unique()->values()->all();
                    $lotesIngreso = DB::table('lotes')
                        ->whereIn('id', $loteIdsIngreso)
                        ->get(['id', 'id_insumo']);

                    foreach ($itemsIngreso as $it) {
                        $cantidad = (int) ($it->cantidad_entrada > 0 ? $it->cantidad_entrada : $it->cantidad_salida);
                        if ($cantidad <= 0) {
                            continue;
                        }

                        $lote = $lotesIngreso->firstWhere('id', (int) $it->lote_id);
                        if (!$lote) {
                            continue;
                        }

                        $insumoId = (int) $lote->id_insumo;
                        if ($insumoId <= 0) {
                            continue;
                        }

                        $cantidadPorInsumo[$insumoId] = ($cantidadPorInsumo[$insumoId] ?? 0) + $cantidad;
                    }
                }
            }
        }

        if (empty($cantidadPorInsumo)) {
        foreach ($itemsRecibidos as $item) {
            $loteId = (int) $item['lote_id'];
            $cantidad = (int) $item['cantidad'];
            if ($cantidad <= 0) {
                continue;
            }
            $lote = $lotes->firstWhere('id', $loteId);
            if (!$lote) {
                continue;
            }
            $insumoId = (int) $lote->id_insumo;
            $cantidadPorInsumo[$insumoId] = ($cantidadPorInsumo[$insumoId] ?? 0) + $cantidad;
        }

        }

        if (empty($cantidadPorInsumo)) {
            $resumen['motivo'] = 'Cantidades por insumo quedaron en cero';
            return $resumen;
        }

        // Modo B: acumular todos los items por hospital y crear un solo movimiento por hospital
        // $acumuladoPorHospital[hospital_id] = ['sede_id' => int, 'items' => [lote_id => cantidad]]
        $acumuladoPorHospital = [];

        foreach ($cantidadPorInsumo as $insumoId => $cantidadTotal) {
            $resumen['insumos_procesados']++;
            $distribuidoEsteInsumo = 0;

            $hospitalesElegibles = $hospitalesEstado->filter(function ($h) use ($fichaMap, $insumoId) {
                $hid = (int) $h->id;
                return ($fichaMap[$hid][$insumoId] ?? false) === true;
            });

            $resumen['hospitales_elegibles_por_insumo'][(int) $insumoId] = (int) $hospitalesElegibles->count();

            if ($hospitalesElegibles->isEmpty()) {
                $resumen['omitidos'][] = [
                    'insumo_id' => (int) $insumoId,
                    'motivo' => 'Sin hospitales elegibles por ficha_insumos',
                ];
                continue;
            }

            $plan = $this->calcularPlanDistribucionPorHospital($cantidadTotal, $porcentajes, $hospitalesElegibles);
            if (empty($plan)) {
                $resumen['sobrante_por_insumo'][(int) $insumoId] = (int) $cantidadTotal;
                $resumen['sobrante_total'] += (int) $cantidadTotal;
                $resumen['omitidos'][] = [
                    'insumo_id' => (int) $insumoId,
                    'motivo' => 'Plan de distribución vacío (porcentajes/tipos no coinciden)',
                    'tipos_detectados' => $hospitalesElegibles
                        ->pluck('tipo')
                        ->map(fn ($t) => is_null($t) ? null : (string) $t)
                        ->unique()
                        ->values()
                        ->all(),
                    'porcentajes' => [
                        'tipo1' => (float) $porcentajes->tipo1,
                        'tipo2' => (float) $porcentajes->tipo2,
                        'tipo3' => (float) $porcentajes->tipo3,
                        'tipo4' => (float) $porcentajes->tipo4,
                    ],
                ];
                continue;
            }

            foreach ($plan as $hospitalId => $cantidadHospital) {
                $cantidadHospital = (int) $cantidadHospital;
                if ($cantidadHospital <= 0) {
                    continue;
                }

                $sedeDestino = DB::table('sedes')
                    ->where('hospital_id', (int) $hospitalId)
                    ->where('tipo_almacen', 'almacenPrin')
                    ->where(function ($q) {
                        $q->where('status', 'activo')
                            ->orWhere('status', 1)
                            ->orWhere('status', true);
                    })
                    ->first();

                if (!$sedeDestino) {
                    $resumen['omitidos'][] = [
                        'hospital_id' => (int) $hospitalId,
                        'insumo_id' => (int) $insumoId,
                        'motivo' => 'Hospital sin sede almacenPrin',
                    ];
                    continue;
                }

                try {
                    $itemsDespacho = DB::transaction(function () use ($movimiento, $insumoId, $cantidadHospital) {
                        return $this->tomarStockAusPorInsumoFIFO(
                            (int) $movimiento->destino_hospital_id,
                            (int) $movimiento->destino_sede_id,
                            (int) $insumoId,
                            (int) $cantidadHospital
                        );
                    });
                } catch (Throwable $e) {
                    $resumen['errores'][] = 'Error stock AUS insumo ' . (int) $insumoId . ' hospital ' . (int) $hospitalId . ': ' . $e->getMessage();
                    continue;
                }

                if (empty($itemsDespacho)) {
                    continue;
                }

                $distribuidoEsteInsumo += (int) $cantidadHospital;

                $hid = (int) $hospitalId;
                if (!isset($acumuladoPorHospital[$hid])) {
                    $acumuladoPorHospital[$hid] = [
                        'sede_id' => (int) $sedeDestino->id,
                        'items' => [],
                    ];
                }

                foreach ($itemsDespacho as $it) {
                    $loteId = (int) ($it['lote_id'] ?? 0);
                    $cant = (int) ($it['cantidad'] ?? 0);
                    if ($loteId <= 0 || $cant <= 0) {
                        continue;
                    }
                    $acumuladoPorHospital[$hid]['items'][$loteId] = ($acumuladoPorHospital[$hid]['items'][$loteId] ?? 0) + $cant;
                }
            }

            $sobrante = (int) $cantidadTotal - (int) $distribuidoEsteInsumo;
            if ($sobrante > 0) {
                $resumen['sobrante_por_insumo'][(int) $insumoId] = $sobrante;
                $resumen['sobrante_total'] += $sobrante;
            } else {
                $resumen['sobrante_por_insumo'][(int) $insumoId] = 0;
            }
        }

        foreach ($acumuladoPorHospital as $hospitalId => $dataHospital) {
            $sedeId = (int) ($dataHospital['sede_id'] ?? 0);
            $itemsMap = $dataHospital['items'] ?? [];

            if ($sedeId <= 0 || empty($itemsMap)) {
                continue;
            }

            $itemsDespachoConsolidados = [];
            $totalCantidad = 0;
            foreach ($itemsMap as $loteId => $cantidad) {
                $cantidad = (int) $cantidad;
                $loteId = (int) $loteId;
                if ($loteId <= 0 || $cantidad <= 0) {
                    continue;
                }
                $itemsDespachoConsolidados[] = [
                    'lote_id' => $loteId,
                    'cantidad' => $cantidad,
                ];
                $totalCantidad += $cantidad;
            }

            if (empty($itemsDespachoConsolidados) || $totalCantidad <= 0) {
                continue;
            }

            [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($itemsDespachoConsolidados);

            MovimientoStock::create([
                'tipo' => 'transferencia',
                'tipo_movimiento' => 'despacho',
                'origen_hospital_id' => (int) $movimiento->destino_hospital_id,
                'origen_sede_id' => (int) $movimiento->destino_sede_id,
                'destino_hospital_id' => (int) $hospitalId,
                'destino_sede_id' => (int) $sedeId,
                'origen_almacen_tipo' => 'almacenAus',
                'origen_almacen_id' => null,
                'destino_almacen_tipo' => 'almacenPrin',
                'destino_almacen_id' => null,
                'cantidad_salida_total' => $totalCantidad,
                'cantidad_entrada_total' => 0,
                'discrepancia_total' => false,
                'fecha_despacho' => $fechaDespacho,
                'observaciones' => 'Distribución automática desde AUS (' . $estado . ')',
                'estado' => 'pendiente',
                'codigo_grupo' => $codigoGrupo,
                'user_id' => $userId,
                'user_id_receptor' => null,
            ]);

            $resumen['movimientos_creados']++;
        }

        if ($resumen['motivo'] === null) {
            $resumen['motivo'] = $resumen['movimientos_creados'] > 0
                ? 'OK'
                : 'No se generaron movimientos (revisar tipos de hospital, porcentajes, sedes almacenPrin y stock AUS)';
        }

        Log::info('DistribucionAutomaticaAUS: end', [
            'movimiento_stock_id' => (int) $movimiento->id,
            'resumen' => $resumen,
        ]);

        return $resumen;
    }

    private function calcularPlanDistribucionPorHospital(int $cantidadTotal, TipoHospitalDistribucion $porcentajes, $hospitalesElegibles): array
    {
        $mapaPorcentaje = [
            'tipo1' => (float) $porcentajes->tipo1,
            'tipo2' => (float) $porcentajes->tipo2,
            'tipo3' => (float) $porcentajes->tipo3,
            'tipo4' => (float) $porcentajes->tipo4,
        ];

        $asignacion = [];
        $hospitalIds = [];
        $tipoNumericoPorHospital = [];
        $sumaAsignada = 0;

        foreach ($hospitalesElegibles as $h) {
            $hid = (int) $h->id;
            $tipoKey = $this->mapearTipoHospitalAClavePorcentaje((string) ($h->tipo ?? ''));
            $pct = (float) ($mapaPorcentaje[$tipoKey] ?? 0);
            if (preg_match('/^tipo([1-4])$/', (string) $tipoKey, $m)) {
                $tipoNumericoPorHospital[$hid] = (int) $m[1];
            }
            if ($hid <= 0 || $pct <= 0) {
                continue;
            }

            $cantidadHospital = (int) floor($cantidadTotal * ($pct / 100.0));
            if ($cantidadHospital <= 0) {
                continue;
            }

            $asignacion[$hid] = $cantidadHospital;
            $hospitalIds[] = $hid;
            $sumaAsignada += $cantidadHospital;
        }

        if (empty($asignacion) || $sumaAsignada <= 0) {
            return [];
        }

        // Evitar asignar más que el total por redondeos o configuración.
        if ($sumaAsignada > $cantidadTotal) {
            $factor = $cantidadTotal / (float) $sumaAsignada;
            $nuevo = [];
            $nuevoSuma = 0;

            sort($hospitalIds);
            foreach ($hospitalIds as $hid) {
                $cant = (int) floor(((int) $asignacion[$hid]) * $factor);
                if ($cant > 0) {
                    $nuevo[$hid] = $cant;
                    $nuevoSuma += $cant;
                }
            }

            $resto = $cantidadTotal - $nuevoSuma;
            $i = 0;
            $keys = array_keys($nuevo);
            $count = count($keys);
            while ($resto > 0 && $count > 0) {
                $hid = $keys[$i % $count];
                $nuevo[$hid] = ((int) $nuevo[$hid]) + 1;
                $resto--;
                $i++;
            }

            $asignacion = $nuevo;
        }

        // Si quedó sobrante por redondeo (floor), repartirlo equitativamente entre hospitales del mayor tipo.
        $resto = (int) $cantidadTotal - (int) array_sum($asignacion);
        if ($resto > 0 && !empty($tipoNumericoPorHospital)) {
            $maxTipo = max($tipoNumericoPorHospital);
            $candidatos = array_keys(array_filter(
                $tipoNumericoPorHospital,
                fn ($t) => (int) $t === (int) $maxTipo
            ));

            if (!empty($candidatos)) {
                sort($candidatos);
                foreach ($candidatos as $hid) {
                    if (!isset($asignacion[(int) $hid])) {
                        $asignacion[(int) $hid] = 0;
                    }
                }

                $i = 0;
                $count = count($candidatos);
                while ($resto > 0 && $count > 0) {
                    $hid = (int) $candidatos[$i % $count];
                    $asignacion[$hid] = ((int) ($asignacion[$hid] ?? 0)) + 1;
                    $resto--;
                    $i++;
                }
            }
        }

        return $asignacion;
    }

    private function mapearTipoHospitalAClavePorcentaje(string $tipo): string
    {
        $t = $this->normalizarClave($tipo);

        // Soportar formatos numéricos en BD (1,2,3,4)
        if (in_array($t, ['1', '2', '3', '4'], true)) {
            return 'tipo' . $t;
        }

        // Soportar formatos como "tipo 1", "Tipo 1", "tipo1"
        if (preg_match('/^tipo([1-4])$/', $t, $m)) {
            return 'tipo' . $m[1];
        }

        // Soportar formatos como "hospital_tipo1", "hospitaltipo1"
        if (preg_match('/^hospitaltipo([1-4])$/', $t, $m)) {
            return 'tipo' . $m[1];
        }

        return $t;
    }

    private function tomarStockAusPorInsumoFIFO(int $ausHospitalId, int $ausSedeId, int $insumoId, int $cantidadNecesaria): array
    {
        $items = [];
        $restante = $cantidadNecesaria;

        $lotesDisponibles = DB::table('almacenes_aus')
            ->join('lotes', 'almacenes_aus.lote_id', '=', 'lotes.id')
            ->where('almacenes_aus.hospital_id', $ausHospitalId)
            ->where('almacenes_aus.sede_id', $ausSedeId)
            ->where('lotes.id_insumo', $insumoId)
            ->where('almacenes_aus.status', true)
            ->where('almacenes_aus.cantidad', '>', 0)
            ->select('almacenes_aus.id as almacen_id', 'almacenes_aus.lote_id', 'almacenes_aus.cantidad', 'lotes.fecha_vencimiento')
            ->orderBy('lotes.fecha_vencimiento', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($lotesDisponibles as $row) {
            if ($restante <= 0) {
                break;
            }

            $disp = (int) $row->cantidad;
            if ($disp <= 0) {
                continue;
            }

            $tomar = min($restante, $disp);
            $items[] = [
                'lote_id' => (int) $row->lote_id,
                'cantidad' => (int) $tomar,
            ];

            $nuevaCantidad = $disp - $tomar;
            DB::table('almacenes_aus')
                ->where('id', (int) $row->almacen_id)
                ->update([
                    'cantidad' => $nuevaCantidad,
                    'status' => $nuevaCantidad > 0 ? 1 : 0,
                    'updated_at' => now(),
                ]);

            $restante -= $tomar;
        }

        if ($restante > 0) {
            throw new InvalidArgumentException('Stock insuficiente en almacén AUS para distribuir el insumo ID ' . $insumoId . '. Faltante: ' . $restante);
        }

        return $items;
    }

    private function normalizarClave(string $valor): string
    {
        $v = Str::lower(Str::ascii(trim($valor)));
        $v = str_replace([' ', '-', '__'], ['', '', ''], $v);
        $v = str_replace('_', '', $v);
        return $v;
    }
}
