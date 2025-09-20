<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Models\LoteAlmacen;
use Illuminate\Http\Request;

class ReportesController extends Controller
{
    // GET /api/reportes/kardex/lote/{lote_id}
    // Muestra movimientos de stock por lote específico
    public function kardexPorLote(int $loteId, Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $query = MovimientoStock::where('lote_id', $loteId)
            ->with(['lote.insumo', 'hospital'])
            ->orderBy('created_at', 'desc');
        $movimientos = $query->paginate($perPage);

        // Calcular stock actual por almacén
        $stockActual = LoteAlmacen::where('lote_id', $loteId)
            ->with(['lote.insumo'])
            ->get()
            ->groupBy(['almacen_tipo', 'almacen_id']);

        return response()->json([
            'status' => true,
            'mensaje' => 'Kardex de movimientos por lote.',
            'data' => [
                'lote_id' => $loteId,
                'movimientos' => $movimientos,
                'stock_actual' => $stockActual,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/reportes/distribucion/hospital/{hospital_id}
    // Reporte de distribuciones recibidas por hospital
    public function distribucionPorHospital(int $hospitalId, Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $query = MovimientoStock::where('hospital_id', $hospitalId)
            ->where('tipo', 'transferencia')
            ->where('destino_almacen_tipo', 'principal')
            ->with(['lote.insumo'])
            ->orderBy('created_at', 'desc');
        $distribuciones = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribuciones recibidas por hospital.',
            'data' => [
                'hospital_id' => $hospitalId,
                'distribuciones' => $distribuciones,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/reportes/stock/almacen
    // Stock actual por almacén con filtros
    public function stockPorAlmacen(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $query = LoteAlmacen::query()
            ->when($request->filled('hospital_id'), fn($q) => $q->where('hospital_id', $request->hospital_id))
            ->when($request->filled('almacen_tipo'), fn($q) => $q->where('almacen_tipo', $request->almacen_tipo))
            ->when($request->filled('almacen_id'), fn($q) => $q->where('almacen_id', $request->almacen_id))
            ->when($request->filled('lote_id'), fn($q) => $q->where('lote_id', $request->lote_id))
            ->when($request->filled('vence_antes'), fn($q) => $q->whereHas('lote', fn($q2) => $q2->whereDate('fecha_vencimiento', '<=', $request->vence_antes)))
            ->with(['lote.insumo', 'hospital'])
            ->having('cantidad', '>', 0)
            ->orderBy('lote_id')
            ->orderBy('hospital_id')
            ->orderBy('almacen_tipo');
        $stock = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Stock actual por almacén.',
            'data' => $stock,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
