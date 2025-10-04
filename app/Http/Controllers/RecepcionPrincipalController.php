<?php

namespace App\Http\Controllers;

use App\Models\LoteGrupo;
use App\Models\MovimientoStock;
use App\Models\MovimientoDiscrepancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        ];

        try {
            DB::transaction(function () use ($data, $userId, &$resultado) {
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


                $resultado['estado'] = $estadoFinal;
                $resultado['discrepancias'] = $discrepancias;
            });

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
}
