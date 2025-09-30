<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistribucionInternaController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    // POST /api/distribucion/principal
    // Body esperado:
    // {
    //   "hospital_id": 10,
    //   "principal_id": 5,
    //   "items": [ { "lote_id": 123, "destino_tipo": "farmacia", "destino_id": 7, "cantidad": 30 } ]
    // }
    public function distribuir(Request $request)
    {
        $data = $request->validate([
            'hospital_id' => ['required','integer','min:1'],
            'principal_id' => ['required','integer','min:1'],
            'items' => ['required','array','min:1'],
            'items.*.lote_id' => ['required','integer','min:1'],
            'items.*.destino_tipo' => ['required','string','max:100'],
            'items.*.destino_id' => ['required','integer','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $it) {
                $this->stock->transferir(
                    loteId: (int) $it['lote_id'],
                    origenTipo: 'principal',
                    origenId: (int) $data['principal_id'],
                    destinoTipo: (string) $it['destino_tipo'],
                    destinoId: (int) $it['destino_id'],
                    cantidad: (int) $it['cantidad'],
                    hospitalIdDestino: (int) $data['hospital_id']
                );

                MovimientoStock::create([
                    'tipo' => 'transferencia',
                    'lote_id' => (int) $it['lote_id'],
                    'hospital_id' => (int) $data['hospital_id'],
                    'origen_almacen_tipo' => 'principal',
                    'origen_almacen_id' => (int) $data['principal_id'],
                    'destino_almacen_tipo' => (string) $it['destino_tipo'],
                    'destino_almacen_id' => (int) $it['destino_id'],
                    'cantidad' => (int) $it['cantidad'],
                    'estado' => 'pendiente',
                    'user_id' => (int) $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución interna aplicada.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
