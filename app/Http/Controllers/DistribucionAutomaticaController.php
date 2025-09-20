<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\TipoHospitalDistribucion;
use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistribucionAutomaticaController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    // POST /api/distribucion/automatica/central
    // Body esperado:
    // {
    //   "origen_central_id": 1,
    //   "hospital_ids": [10, 11, 12], // opcional, si vacío usa todos activos
    //   "lote_ids": [123, 124], // lotes a distribuir
    //   "estrategia": "porcentaje" // por ahora solo porcentaje
    // }
    public function distribuirPorPorcentaje(Request $request)
    {
        $data = $request->validate([
            'origen_central_id' => ['required','integer','min:1'],
            'hospital_ids' => ['nullable','array'],
            'hospital_ids.*' => ['integer','min:1'],
            'lote_ids' => ['required','array','min:1'],
            'lote_ids.*' => ['integer','min:1'],
            'estrategia' => ['required','in:porcentaje'],
        ]);

        // Obtener hospitales objetivo
        $hospitales = Hospital::where('status', 'activo')
            ->when(!empty($data['hospital_ids']), fn($q) => $q->whereIn('id', $data['hospital_ids']))
            ->with('principalAlmacen') // asumir relación
            ->get();

        $resultados = [];
        foreach ($data['lote_ids'] as $loteId) {
            $lote = Lote::find($loteId);
            if (!$lote) continue;

            // Calcular stock disponible en central
            $stockCentral = \App\Models\LoteAlmacen::where('lote_id', $loteId)
                ->where('almacen_tipo', 'central')
                ->where('almacen_id', $data['origen_central_id'])
                ->first();
            $disponible = $stockCentral ? $stockCentral->cantidad : 0;

            if ($disponible <= 0) continue;

            // Distribuir por porcentaje de tipo de hospital
            foreach ($hospitales as $hospital) {
                $tipoDist = TipoHospitalDistribucion::where('tipo', $hospital->tipo)->first();
                if (!$tipoDist) continue;

                $porcentaje = $tipoDist->porcentaje / 100; // de decimal a porcentaje
                $cantidadAsignada = (int) ($disponible * $porcentaje);

                if ($cantidadAsignada <= 0) continue;

                // Encontrar almacén principal del hospital
                $principalAlmacen = $hospital->principalAlmacen;
                if (!$principalAlmacen) continue;

                // Transferir
                try {
                    $this->stock->transferir(
                        loteId: $loteId,
                        origenTipo: 'central',
                        origenId: (int) $data['origen_central_id'],
                        destinoTipo: 'principal',
                        destinoId: $principalAlmacen->id,
                        cantidad: $cantidadAsignada,
                        hospitalIdDestino: $hospital->id
                    );

                    // Registrar movimiento
                    MovimientoStock::create([
                        'tipo' => 'transferencia',
                        'lote_id' => $loteId,
                        'hospital_id' => $hospital->id,
                        'origen_almacen_tipo' => 'central',
                        'origen_almacen_id' => (int) $data['origen_central_id'],
                        'destino_almacen_tipo' => 'principal',
                        'destino_almacen_id' => $principalAlmacen->id,
                        'cantidad' => $cantidadAsignada,
                        'user_id' => (int) $request->user()->id,
                    ]);

                    $resultados[] = [
                        'hospital_id' => $hospital->id,
                        'lote_id' => $loteId,
                        'cantidad' => $cantidadAsignada,
                        'porcentaje' => $tipoDist->porcentaje,
                    ];
                } catch (\Exception $e) {
                    $resultados[] = [
                        'hospital_id' => $hospital->id,
                        'lote_id' => $loteId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución automática aplicada por porcentaje.',
            'data' => $resultados,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
