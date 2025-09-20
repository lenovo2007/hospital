<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistribucionCentralController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    // POST /api/distribucion/central
    // Body esperado:
    // {
    //   "origen_central_id": 1,
    //   "hospital_id": 10,
    //   "principal_id": 5, // almacen principal del hospital destino
    //   "items": [ { "lote_id": 123, "cantidad": 100 } ]
    // }
    public function distribuir(Request $request)
    {
        $data = $request->validate([
            'origen_central_id' => ['required','integer','min:1'],
            'hospital_id' => ['required','integer','min:1'],
            'principal_id' => ['required','integer','min:1'],
            'items' => ['required','array','min:1'],
            'items.*.lote_id' => ['required','integer','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $it) {
                // Transferencia central -> principal
                $this->stock->transferir(
                    loteId: (int) $it['lote_id'],
                    origenTipo: 'central',
                    origenId: (int) $data['origen_central_id'],
                    destinoTipo: 'principal',
                    destinoId: (int) $data['principal_id'],
                    cantidad: (int) $it['cantidad'],
                    hospitalIdDestino: (int) $data['hospital_id']
                );

                MovimientoStock::create([
                    'tipo' => 'transferencia',
                    'lote_id' => (int) $it['lote_id'],
                    'hospital_id' => (int) $data['hospital_id'],
                    'origen_almacen_tipo' => 'central',
                    'origen_almacen_id' => (int) $data['origen_central_id'],
                    'destino_almacen_tipo' => 'principal',
                    'destino_almacen_id' => (int) $data['principal_id'],
                    'cantidad' => (int) $it['cantidad'],
                    'user_id' => (int) $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución desde central aplicada.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
