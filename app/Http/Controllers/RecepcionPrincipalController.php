<?php

namespace App\Http\Controllers;

use App\Models\LoteGrupo;
use App\Models\MovimientoDiscrepancia;
use App\Models\MovimientoStock;
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
                    ->where('estado', 'pendiente')
                    ->lockForUpdate()
                    ->first();

                if (!$movimiento) {
                    throw new InvalidArgumentException('No existe un movimiento pendiente con el ID indicado.');
                }

                // Buscar los lotes grupos asociados al movimiento
                $itemsEsperados = LoteGrupo::where('codigo', $movimiento->codigo_grupo)
                    ->lockForUpdate()
                    ->get();

                if ($itemsEsperados->isEmpty()) {
                    throw new InvalidArgumentException('No se encontraron items asociados al código de grupo.');
                }

                // Validar que los items estén en status 'entregado'
                $itemsPendientes = $itemsEsperados->where('status', 'activo');
                if ($itemsPendientes->isNotEmpty()) {
                    throw new InvalidArgumentException('Los items deben estar en estado "entregado" antes de poder ser recibidos. Estado actual: activo (pendiente).');
                }

                $itemsEntregados = $itemsEsperados->where('status', 'entregado');
                if ($itemsEntregados->isEmpty()) {
                    throw new InvalidArgumentException('No se encontraron items en estado "entregado" para recibir.');
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

                    // Actualizar stock en almacenes_principales
                    $registroDestino = DB::table('almacenes_principales')
                        ->where([
                            'hospital_id' => $movimiento->destino_hospital_id,
                            'sede_id' => $movimiento->destino_sede_id,
                            'lote_id' => $loteId,
                        ])
                        ->lockForUpdate()
                        ->first();

                    if ($registroDestino) {
                        $nuevaCantidad = (int) $registroDestino->cantidad + $cantidadRecibida;
                        DB::table('almacenes_principales')
                            ->where('id', $registroDestino->id)
                            ->update([
                                'cantidad' => $nuevaCantidad,
                                'status' => $nuevaCantidad > 0 ? 1 : 0,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('almacenes_principales')->insert([
                            'hospital_id' => $movimiento->destino_hospital_id,
                            'sede_id' => $movimiento->destino_sede_id,
                            'lote_id' => $loteId,
                            'cantidad' => $cantidadRecibida,
                            'status' => $cantidadRecibida > 0 ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Actualizar lote grupo con cantidad_entrada, discrepancia y status
                    $loteGrupo->update([
                        'cantidad_entrada' => $cantidadRecibida,
                        'discrepancia' => $tieneDiscrepancia,
                        'status' => 'recibido'
                    ]);
                }

                // Calcular cantidad total de entrada para el movimiento
                $totalCantidadEntrada = 0;
                foreach ($itemsRecibidos as $item) {
                    $totalCantidadEntrada += (int) $item['cantidad'];
                }

                $estadoFinal = empty($discrepancias) ? 'completado' : 'inconsistente';

                foreach ($discrepancias as $detalle) {
                    MovimientoDiscrepancia::create([
                        'movimiento_stock_id' => $movimiento->id,
                        'lote_id' => $detalle['lote_id'] ?: null,
                        'cantidad_esperada' => $detalle['cantidad_esperada'],
                        'cantidad_recibida' => $detalle['cantidad_recibida'],
                        'observaciones' => null,
                    ]);
                }

                $movimiento->update([
                    'estado' => $estadoFinal,
                    'fecha_recepcion' => $data['fecha_recepcion'],
                    'cantidad_entrada' => $totalCantidadEntrada,
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
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar la recepción.',
                'data' => null,
            ], 500);
        }
    }
}
