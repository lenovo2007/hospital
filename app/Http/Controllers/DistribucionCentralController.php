<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\MovimientoStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

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
            'tipo_movimiento' => ['required','string','max:50'],
            'fecha_despacho' => ['required','date'],
            'observaciones' => ['nullable','string','max:500'],
            'items' => ['required','array','min:1'],
            'items.*.lote_id' => ['required','integer','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
        ]);

        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($data, $userId) {
                foreach ($data['items'] as $it) {
                    $loteId = (int) $it['lote_id'];
                    $cantidad = (int) $it['cantidad'];

                    // Transferencia central -> principal
                    $this->stock->transferir(
                        loteId: $loteId,
                        origenTipo: 'almacenCent',
                        origenId: (int) $data['origen_central_id'],
                        destinoTipo: 'almacenPrin',
                        destinoId: (int) $data['principal_id'],
                        cantidad: $cantidad,
                        hospitalIdDestino: (int) $data['hospital_id']
                    );

                    MovimientoStock::create([
                        'tipo' => 'transferencia',
                        'tipo_movimiento' => $data['tipo_movimiento'],
                        'lote_id' => $loteId,
                        'hospital_id' => (int) $data['hospital_id'],
                        'origen_almacen_tipo' => 'almacenCent',
                        'origen_almacen_id' => (int) $data['origen_central_id'],
                        'destino_almacen_tipo' => 'almacenPrin',
                        'destino_almacen_id' => (int) $data['principal_id'],
                        'cantidad' => $cantidad,
                        'fecha_despacho' => $data['fecha_despacho'],
                        'user_id' => $userId,
                    ]);
                }
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento desde central aplicado.',
                'data' => null,
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
        } catch (QueryException $e) {
            Log::error('Movimiento central falló por QueryException', [
                'mensaje' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
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
}
