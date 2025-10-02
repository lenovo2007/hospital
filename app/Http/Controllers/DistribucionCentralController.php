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
    // POST /api/movimiento/central/salida
    // {
    //   "origen_almacen_id": 1,
    //   "origen_hospital_id": 1,
    //   "origen_sede_id": 1,
    //   "destino_hospital_id": 1,
    //   "destino_sede_id": 2,
    //   "destino_almacen_tipo": "almacenPrin",
    //   "tipo_movimiento": "despacho",
    //   "fecha_despacho": "2025-09-29",
    //   "items": [ { "lote_id": 123, "cantidad": 100 } ]
    // }
    public function salida(Request $request)
    {
        $data = $request->validate([
            'origen_almacen_id' => ['required','integer','min:1'],
            'origen_hospital_id' => ['required','integer','min:1'],
            'origen_sede_id' => ['required','integer','min:1'],
            'destino_hospital_id' => ['required','integer','min:1'],
            'destino_sede_id' => ['required','integer','min:1'],
            'destino_almacen_tipo' => ['required','string','max:100'],
            'tipo_movimiento' => ['required','string','max:50'],
            'fecha_despacho' => ['required','date'],
            'observaciones' => ['nullable','string','max:500'],
            'items' => ['required','array','min:1'],
            'items.*.lote_id' => ['required','integer','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
        ]);

        $userId = (int) $request->user()->id;

        $codigoGrupo = null;

        try {
            DB::transaction(function () use ($data, $userId, &$codigoGrupo) {
                // Crear grupo de lote para los items del movimiento
                [$codigoGrupo, $grupoItems] = LoteGrupo::crearGrupo($data['items']);

                $totalCantidad = 0;
                $origenDetectadoIds = [];

                foreach ($data['items'] as $it) {
                    $loteId = (int) $it['lote_id'];
                    $cantidad = (int) $it['cantidad'];

                    $transferResult = $this->transferirDesdeCentral(
                        (int) $data['origen_almacen_id'],
                        (int) $data['destino_sede_id'],
                        (int) $data['destino_hospital_id'],
                        $loteId,
                        $cantidad
                    );

                    $totalCantidad += $cantidad;
                    if (isset($transferResult['origen_id'])) {
                        $origenDetectadoIds[] = $transferResult['origen_id'];
                    }
                }

                $origenAlmacenId = !empty($origenDetectadoIds)
                    ? (count(array_unique($origenDetectadoIds)) === 1 ? $origenDetectadoIds[0] : (int) $data['origen_almacen_id'])
                    : (int) $data['origen_almacen_id'];

                MovimientoStock::create([
                    'tipo' => 'transferencia',
                    'tipo_movimiento' => $data['tipo_movimiento'],
                    'origen_hospital_id' => (int) $data['origen_hospital_id'],
                    'origen_sede_id' => (int) $data['origen_sede_id'],
                    'destino_hospital_id' => (int) $data['destino_hospital_id'],
                    'destino_sede_id' => (int) $data['destino_sede_id'],
                    'origen_almacen_tipo' => 'almacenCent',
                    'origen_almacen_id' => $origenAlmacenId,
                    'destino_almacen_tipo' => $data['destino_almacen_tipo'],
                    'destino_almacen_id' => null,
                    'cantidad' => $totalCantidad,
                    'fecha_despacho' => $data['fecha_despacho'],
                    'observaciones' => $data['observaciones'] ?? null,
                    'estado' => 'pendiente',
                    'codigo_grupo' => $codigoGrupo,
                    'user_id' => $userId,
                ]);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento desde central aplicado.',
                'data' => [
                    'codigo_grupo' => $codigoGrupo,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
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

    private function transferirDesdeCentral(int $origenId, int $destinoSedeId, int $hospitalId, int $loteId, int $cantidad): array
    {
        $central = DB::table('almacenes_centrales')
            ->where('id', $origenId)
            ->where('lote_id', $loteId)
            ->lockForUpdate()
            ->first();

        if (!$central) {
            $central = DB::table('almacenes_centrales')
                ->where('hospital_id', $hospitalId)
                ->where('sede_id', $destinoSedeId)
                ->where('lote_id', $loteId)
                ->lockForUpdate()
                ->first();
        }

        if (!$central) {
            throw new StockException('No se encontró el registro en el almacén central para el lote especificado.');
        }

        if ((int) $central->cantidad < $cantidad) {
            throw new StockException('Stock insuficiente en el almacén central. Disponible: ' . $central->cantidad);
        }

        $nuevaCantidadCentral = (int) $central->cantidad - $cantidad;

        DB::table('almacenes_centrales')
            ->where('id', $central->id)
            ->update([
                'cantidad' => $nuevaCantidadCentral,
                'status' => $nuevaCantidadCentral > 0 ? 'activo' : 'inactivo',
                'updated_at' => now(),
            ]);

        return [
            'origen_id' => (int) $central->id,
            'destino_id' => null,
        ];
    }
}
