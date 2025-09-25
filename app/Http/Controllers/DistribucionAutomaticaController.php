<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Models\LoteAlmacen;
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

        // Obtener hospitales objetivo (desde tabla 'hospitales')
        $hospitales = DB::table('hospitales')
            ->when(!empty($data['hospital_ids']), fn($q) => $q->whereIn('id', $data['hospital_ids']))
            ->where('status', 'activo')
            ->select('id', 'tipo')
            ->get();

        $resultados = [];
        foreach ($data['lote_ids'] as $loteId) {
            // Stock disponible en central para este lote
            $stockCentral = LoteAlmacen::where('lote_id', $loteId)
                ->where('almacen_tipo', 'central')
                ->where('almacen_id', $data['origen_central_id'])
                ->first();
            $disponible = $stockCentral?->cantidad ?? 0;
            if ($disponible <= 0) { continue; }

            // Construir mapa de porcentajes por tipo existente en BD
            $tipos = DB::table('tipos_hospital_distribuciones')->pluck('porcentaje', 'tipo');
            if ($tipos->isEmpty()) { continue; }

            // Suma total de porcentajes relevantes para hospitales seleccionados
            $sumaPct = 0.0;
            $targets = [];
            foreach ($hospitales as $h) {
                $pct = (float) ($tipos[$h->tipo] ?? 0);
                if ($pct > 0) {
                    $targets[] = ['hospital_id' => $h->id, 'tipo' => $h->tipo, 'pct' => $pct];
                    $sumaPct += $pct;
                }
            }
            if ($sumaPct <= 0) { continue; }

            // Distribución proporcional al disponible según porcentaje relativo
            foreach ($targets as $t) {
                $rel = $t['pct'] / $sumaPct; // normalizado
                $cantidadAsignada = (int) floor($disponible * $rel);
                if ($cantidadAsignada <= 0) { continue; }

                // Buscar almacén principal del hospital destino
                $principal = DB::table('almacenes_principales')
                    ->where('hospital_id', $t['hospital_id'])
                    ->select('id')
                    ->first();
                if (!$principal) { continue; }

                try {
                    $this->stock->transferir(
                        loteId: (int) $loteId,
                        origenTipo: 'central',
                        origenId: (int) $data['origen_central_id'],
                        destinoTipo: 'principal',
                        destinoId: (int) $principal->id,
                        cantidad: $cantidadAsignada,
                        hospitalIdDestino: (int) $t['hospital_id']
                    );

                    MovimientoStock::create([
                        'tipo' => 'transferencia',
                        'lote_id' => (int) $loteId,
                        'hospital_id' => (int) $t['hospital_id'],
                        'origen_almacen_tipo' => 'central',
                        'origen_almacen_id' => (int) $data['origen_central_id'],
                        'destino_almacen_tipo' => 'principal',
                        'destino_almacen_id' => (int) $principal->id,
                        'cantidad' => $cantidadAsignada,
                        'user_id' => (int) $request->user()->id,
                    ]);

                    $resultados[] = [
                        'hospital_id' => (int) $t['hospital_id'],
                        'lote_id' => (int) $loteId,
                        'cantidad' => $cantidadAsignada,
                        'porcentaje_relativo' => $t['pct'],
                    ];
                } catch (\Exception $e) {
                    $resultados[] = [
                        'hospital_id' => (int) $t['hospital_id'],
                        'lote_id' => (int) $loteId,
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
