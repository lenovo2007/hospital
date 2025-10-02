<?php

namespace App\Http\Controllers;

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
    //   "origen_hospital_id": 1,
    //   "origen_sede_id": 1,
    //   "destino_hospital_id": 2,
    //   "destino_sede_id": 2,
    //   "origen_almacen_tipo": "almacenCent",
    //   "destino_almacen_tipo": "almacenPrin",
    //   "tipo_movimiento": "despacho",
    //   "fecha_despacho": "2025-09-29",
    //   "observaciones": "Despacho de insumos médicos",
    //   "items": [ { "lote_id": 1, "cantidad": 200 }, { "lote_id": 2, "cantidad": 100 } ]
    // }
    public function salida(Request $request)
    {
        $data = $request->validate([
            'origen_hospital_id' => ['required','integer','min:1'],
            'origen_sede_id' => ['required','integer','min:1'],
            'destino_hospital_id' => ['required','integer','min:1'],
            'destino_sede_id' => ['required','integer','min:1'],
            'origen_almacen_tipo' => ['required','string','max:100'],
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

                // Calcular la suma total de cantidades
                $totalCantidad = 0;
                foreach ($data['items'] as $item) {
                    $totalCantidad += (int) $item['cantidad'];
                }

                // Crear el movimiento de stock
                MovimientoStock::create([
                    'tipo' => 'transferencia',
                    'tipo_movimiento' => $data['tipo_movimiento'],
                    'origen_hospital_id' => (int) $data['origen_hospital_id'],
                    'origen_sede_id' => (int) $data['origen_sede_id'],
                    'destino_hospital_id' => (int) $data['destino_hospital_id'],
                    'destino_sede_id' => (int) $data['destino_sede_id'],
                    'origen_almacen_tipo' => $data['origen_almacen_tipo'],
                    'origen_almacen_id' => null,
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
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error inesperado al registrar el movimiento.',
                'data' => null,
            ], 200);
        }
    }

}
