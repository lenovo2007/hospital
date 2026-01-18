<?php

namespace App\Http\Controllers;

use App\Models\FichaInsumo;
use App\Models\Hospital;
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

                // Validar estado según el tipo de almacén origen
                $estadosPermitidos = $this->obtenerEstadosPermitidosParaRecepcion($movimiento->origen_almacen_tipo);
                
                if (!in_array($movimiento->estado, $estadosPermitidos)) {
                    $estadosTexto = implode(', ', $estadosPermitidos);
                    $tipoMovimiento = $movimiento->origen_almacen_tipo === 'almacenCent' 
                        ? 'con repartidor (Central → Principal)' 
                        : 'interno (Principal → Otros almacenes)';
                    
                    throw new InvalidArgumentException("El movimiento debe estar en estado: {$estadosTexto} para poder ser recibido. Tipo de movimiento: {$tipoMovimiento}. Estado actual: {$movimiento->estado}");
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
                    $tieneDiscrepancia = $cantidadRecibida !== $cantidadEsperada;

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
                        $nuevaCantidad = (int) $registroDestino->cantidad + $cantidadRecibida;
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
                            'cantidad' => $cantidadRecibida,
                            'status' => $cantidadRecibida > 0 ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Actualizar lote grupo con cantidad_entrada y discrepancia
                    $loteGrupo->update([
                        'cantidad_entrada' => $cantidadRecibida,
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
                            'observaciones' => "Discrepancia automática: esperado {$cantidadEsperada}, recibido {$cantidadRecibida}"
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

                $totalCantidadEntrada = 0;
                foreach ($itemsRecibidos as $item) {
                    $totalCantidadEntrada += (int) $item['cantidad'];
                }

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
            'hospitales_elegibles_por_insumo' => [],
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

        if (empty($cantidadPorInsumo)) {
            $resumen['motivo'] = 'Cantidades por insumo quedaron en cero';
            return $resumen;
        }

        foreach ($cantidadPorInsumo as $insumoId => $cantidadTotal) {
            $resumen['insumos_procesados']++;
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
                $resumen['omitidos'][] = [
                    'insumo_id' => (int) $insumoId,
                    'motivo' => 'Plan de distribución vacío (porcentajes/tipos no coinciden)',
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

                [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($itemsDespacho);

                $totalCantidad = 0;
                foreach ($itemsDespacho as $it) {
                    $totalCantidad += (int) $it['cantidad'];
                }

                MovimientoStock::create([
                    'tipo' => 'transferencia',
                    'tipo_movimiento' => 'despacho',
                    'origen_hospital_id' => (int) $movimiento->destino_hospital_id,
                    'origen_sede_id' => (int) $movimiento->destino_sede_id,
                    'destino_hospital_id' => (int) $hospitalId,
                    'destino_sede_id' => (int) $sedeDestino->id,
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
        }

        if ($resumen['motivo'] === null) {
            $resumen['motivo'] = 'OK';
        }

        Log::info('DistribucionAutomaticaAUS: end', [
            'movimiento_stock_id' => (int) $movimiento->id,
            'resumen' => $resumen,
        ]);

        return $resumen;
    }

    private function calcularPlanDistribucionPorHospital(int $cantidadTotal, TipoHospitalDistribucion $porcentajes, $hospitalesElegibles): array
    {
        $hospitalesPorTipo = $hospitalesElegibles->groupBy(function ($h) {
            return (string) ($h->tipo ?? '');
        });

        $mapaPorcentaje = [
            'tipo1' => (float) $porcentajes->tipo1,
            'tipo2' => (float) $porcentajes->tipo2,
            'tipo3' => (float) $porcentajes->tipo3,
            'tipo4' => (float) $porcentajes->tipo4,
        ];

        $asignacion = [];
        $ordenHospitales = [];

        foreach ($hospitalesPorTipo as $tipo => $hospitales) {
            $tipoKey = $this->normalizarClave((string) $tipo);
            if (!array_key_exists($tipoKey, $mapaPorcentaje)) {
                continue;
            }

            $count = (int) $hospitales->count();
            if ($count <= 0) {
                continue;
            }

            $porcTipo = (float) $mapaPorcentaje[$tipoKey];
            if ($porcTipo <= 0) {
                continue;
            }

            $cantidadTipo = (int) floor($cantidadTotal * ($porcTipo / 100.0));
            if ($cantidadTipo <= 0) {
                continue;
            }

            $base = intdiv($cantidadTipo, $count);
            $resto = $cantidadTipo - ($base * $count);

            $idx = 0;
            foreach ($hospitales as $h) {
                $hid = (int) $h->id;
                $asignacion[$hid] = ($asignacion[$hid] ?? 0) + $base + ($idx < $resto ? 1 : 0);
                $ordenHospitales[] = $hid;
                $idx++;
            }
        }

        $sum = array_sum($asignacion);
        $faltante = $cantidadTotal - $sum;
        if ($faltante > 0 && !empty($ordenHospitales)) {
            $i = 0;
            $n = count($ordenHospitales);
            while ($faltante > 0 && $n > 0) {
                $hid = $ordenHospitales[$i % $n];
                $asignacion[$hid] = ($asignacion[$hid] ?? 0) + 1;
                $faltante--;
                $i++;
            }
        }

        foreach ($asignacion as $hid => $cant) {
            if ((int) $cant <= 0) {
                unset($asignacion[$hid]);
            }
        }

        return $asignacion;
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
