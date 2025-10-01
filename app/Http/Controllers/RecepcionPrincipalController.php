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
            'codigo_grupo' => ['required', 'string', 'max:50'],
            'hospital_id' => ['required', 'integer', 'min:1'],
            'sede_id' => ['required', 'integer', 'min:1'],
            'observaciones_recepcion' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.lote_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.cantidad' => ['required_with:items', 'integer', 'min:0'],
        ]);

        $userId = (int) $request->user()->id;
        $resultado = [
            'estado' => null,
            'discrepancias' => [],
        ];

        try {
            DB::transaction(function () use ($data, $userId, &$resultado) {
                $movimiento = MovimientoStock::where('codigo_grupo', $data['codigo_grupo'])
                    ->where('estado', 'pendiente')
                    ->lockForUpdate()
                    ->first();

                if (!$movimiento) {
                    throw new InvalidArgumentException('No existe un movimiento pendiente con el código indicado.');
                }

                if ((int) $movimiento->hospital_id !== (int) $data['hospital_id'] || (int) $movimiento->sede_id !== (int) $data['sede_id']) {
                    throw new InvalidArgumentException('El movimiento no corresponde al hospital o sede proporcionados.');
                }

                $itemsEsperados = LoteGrupo::where('codigo', $movimiento->codigo_grupo)
                    ->where('status', 'activo')
                    ->lockForUpdate()
                    ->get();

                if ($itemsEsperados->isEmpty()) {
                    throw new InvalidArgumentException('No se encontraron items pendientes asociados al código de grupo.');
                }

                $mapaEsperado = [];
                foreach ($itemsEsperados as $item) {
                    $mapaEsperado[(int) $item->lote_id] = [
                        'cantidad' => (int) $item->cantidad,
                        'modelo' => $item,
                    ];
                }

                $itemsRecibidos = $data['items'] ?? [];
                if (empty($itemsRecibidos)) {
                    foreach ($mapaEsperado as $loteId => $info) {
                        $itemsRecibidos[] = [
                            'lote_id' => $loteId,
                            'cantidad' => $info['cantidad'],
                        ];
                    }
                }

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

                foreach ($aceptados as $loteId => $info) {
                    $cantidad = $info['cantidad'];

                    $registroDestino = DB::table('almacenes_principales')
                        ->where([
                            'hospital_id' => (int) $data['hospital_id'],
                            'sede_id' => (int) $data['sede_id'],
                            'lote_id' => $loteId,
                        ])
                        ->lockForUpdate()
                        ->first();

                    if ($registroDestino) {
                        $nuevaCantidad = (int) $registroDestino->cantidad + $cantidad;
                        DB::table('almacenes_principales')
                            ->where('id', $registroDestino->id)
                            ->update([
                                'cantidad' => $nuevaCantidad,
                                'status' => $nuevaCantidad > 0 ? 1 : 0,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('almacenes_principales')->insert([
                            'hospital_id' => (int) $data['hospital_id'],
                            'sede_id' => (int) $data['sede_id'],
                            'lote_id' => $loteId,
                            'cantidad' => $cantidad,
                            'status' => $cantidad > 0 ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $info['modelo']->update(['status' => 'inactivo']);
                }

                $estadoFinal = empty($discrepancias) ? 'completado' : 'inconsistente';

                foreach ($discrepancias as $detalle) {
                    MovimientoDiscrepancia::create([
                        'movimiento_stock_id' => $movimiento->id,
                        'lote_id' => $detalle['lote_id'] ?: null,
                        'cantidad_esperada' => $detalle['cantidad_esperada'],
                        'cantidad_recibida' => $detalle['cantidad_recibida'],
                        'observaciones' => $data['observaciones_recepcion'] ?? null,
                    ]);
                }

                $movimiento->update([
                    'estado' => $estadoFinal,
                    'fecha_recepcion' => now(),
                    'observaciones_recepcion' => $data['observaciones_recepcion'] ?? null,
                    'user_id_receptor' => $userId,
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
