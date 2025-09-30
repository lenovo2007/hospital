<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\LoteGrupo;
use App\Models\MovimientoStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DistribucionCentralController extends Controller
{
    // POST /api/movimiento/central
    // {
    //   "origen_central_id": 1,
    //   "hospital_id": 1,
    //   "sede_id": 2,
    //   "items": [ { "lote_id": 123, "cantidad": 100 } ]
    // }
    public function distribuir(Request $request)
    {
        $data = $request->validate([
            'origen_central_id' => ['required','integer','min:1'],
            'hospital_id' => ['required','integer','min:1'],
            'sede_id' => ['required','integer','min:1'],
            'tipo_movimiento' => ['required','string','max:50'],
            'fecha_despacho' => ['required','date'],
            'observaciones' => ['nullable','string','max:500'],
            'items' => ['required','array','min:1'],
            'items.*.lote_id' => ['required','integer','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
        ]);

        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($data, $userId, &$codigoGrupo) {
                // Crear grupo de lote para los items del movimiento
                [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($data['items']);

                foreach ($data['items'] as $it) {
                    $loteId = (int) $it['lote_id'];
                    $cantidad = (int) $it['cantidad'];

                    $this->transferirDesdeCentral(
                        origenId: (int) $data['origen_central_id'],
                        destinoSedeId: (int) $data['sede_id'],
                        hospitalId: (int) $data['hospital_id'],
                        loteId: $loteId,
                        cantidad: $cantidad
                    );

                    MovimientoStock::create([
                        'tipo' => 'transferencia',
                        'tipo_movimiento' => $data['tipo_movimiento'],
                        'hospital_id' => (int) $data['hospital_id'],
                        'origen_almacen_tipo' => 'almacenCent',
                        'origen_almacen_id' => (int) $data['origen_central_id'],
                        'destino_almacen_tipo' => 'almacenPrin',
                        'destino_almacen_id' => (int) $data['sede_id'],
                        'cantidad' => $cantidad,
                        'fecha_despacho' => $data['fecha_despacho'],
                        'estado' => 'pendiente',
                        'codigo_grupo' => $codigoGrupo,
                        'user_id' => $userId,
                    ]);
                }
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento desde central aplicado.',
                'data' => [
                    'codigo_grupo' => $codigoGrupo,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (StockException $e) {
        } catch (StockException $e) {
            Log::warning('Movimiento central falló por StockException', [
                'mensaje' => $e->getMessage(),
                'payload' => $data,
            ]);
            return response()->json([
                'status' => false,
                'mensaje' => $e->getMessage(),
                'data' => null,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar el movimiento.',
                'data' => null,
            ], 200);
        }
    }

    private function transferirDesdeCentral(int $origenId, int $destinoSedeId, int $hospitalId, int $loteId, int $cantidad): int
    {
        $central = DB::table('almacenes_centrales')
            ->where('id', $origenId)
            ->lockForUpdate()
            ->first();

        if (!$central) {
            throw new StockException('No se encontró el registro en el almacén central.');
        }

        if ((int) $central->lote_id !== $loteId) {
            throw new StockException('El lote indicado no corresponde al registro del almacén central.');
        }

        if ((int) $central->cantidad < $cantidad) {
            throw new StockException('Stock insuficiente en el almacén central. Disponible: ' . $central->cantidad);
        }

        $nuevaCantidadCentral = (int) $central->cantidad - $cantidad;

        DB::table('almacenes_centrales')
            ->where('id', $central->id)
            ->update([
                'cantidad' => $nuevaCantidadCentral,
                'status' => $nuevaCantidadCentral > 0,
                'updated_at' => now(),
            ]);

        $destinoClave = [
            'sede_id' => $destinoSedeId,
            'lote_id' => $loteId,
            'hospital_id' => $hospitalId,
        ];

        $destino = DB::table('almacenes_principales')
            ->where($destinoClave)
            ->lockForUpdate()
            ->first();

        if ($destino) {
            $nuevaCantidadDestino = (int) $destino->cantidad + $cantidad;
            DB::table('almacenes_principales')
                ->where('id', $destino->id)
                ->update([
                    'cantidad' => $nuevaCantidadDestino,
                    'status' => true,
                    'updated_at' => now(),
                ]);

            return (int) $destino->id;
        }

        return DB::table('almacenes_principales')->insertGetId(array_merge($destinoClave, [
            'cantidad' => $cantidad,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
}
