<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\LoteAlmacen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoteController extends Controller
{
    // GET /api/lotes
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Lote::with(['insumo', 'hospital'])
            ->when($request->filled('id_insumo'), fn($q) => $q->where('id_insumo', $request->id_insumo))
            ->when($request->filled('hospital_id'), fn($q) => $q->where('hospital_id', $request->hospital_id))
            ->when($request->filled('numero_lote'), fn($q) => $q->where('numero_lote', 'like', "%{$request->numero_lote}%"))
            ->when($request->filled('vence_hasta'), fn($q) => $q->whereDate('fecha_vencimiento', '<=', $request->vence_hasta))
            ->when($request->filled('vence_desde'), fn($q) => $q->whereDate('fecha_vencimiento', '>=', $request->vence_desde));

        $items = $query->latest('fecha_vencimiento')->paginate($perPage);
        $mensaje = $items->total() > 0 ? 'Listado de lotes.' : 'lotes no encontrado';

        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/lotes
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_insumo' => ['required', 'exists:insumos,id'],
            'numero_lote' => ['required', 'string', 'max:100'],
            'fecha_vencimiento' => ['required', 'date', 'after:today'],
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today'],
            'hospital_id' => ['required', 'exists:hospitales,id'],
        ]);

        // Evitar duplicados por (id_insumo, numero_lote, hospital_id)
        $exists = Lote::where('id_insumo', $data['id_insumo'])
            ->where('numero_lote', $data['numero_lote'])
            ->where('hospital_id', $data['hospital_id'])
            ->exists();
        if ($exists) {
            return response()->json([
                'status' => false,
                'mensaje' => 'El lote ya existe para este insumo y hospital.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $lote = Lote::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Lote creado.',
            'data' => $lote,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/lotes/{lote}
    public function show(Lote $lote)
    {
        $lote->load(['insumo', 'hospital', 'stocks.almacen']);
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de lote.',
            'data' => $lote,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/lotes/{lote}
    public function update(Request $request, Lote $lote)
    {
        $data = $request->validate([
            'id_insumo' => ['sometimes','required', 'exists:insumos,id'],
            'numero_lote' => ['sometimes','required', 'string', 'max:100'],
            'fecha_vencimiento' => ['sometimes','required', 'date', 'after:today'],
            'fecha_ingreso' => ['sometimes','required', 'date', 'before_or_equal:today'],
            'hospital_id' => ['sometimes','required', 'exists:hospitales,id'],
        ]);

        if (isset($data['id_insumo']) || isset($data['numero_lote']) || isset($data['hospital_id'])) {
            $insumo = $data['id_insumo'] ?? $lote->id_insumo;
            $numero = $data['numero_lote'] ?? $lote->numero_lote;
            $hospital = $data['hospital_id'] ?? $lote->hospital_id;
            $dup = Lote::where('id_insumo', $insumo)
                ->where('numero_lote', $numero)
                ->where('hospital_id', $hospital)
                ->where('id', '!=', $lote->id)
                ->exists();
            if ($dup) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'Ya existe otro lote con esos datos (insumo, número y hospital).',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }
        }

        $lote->update($data);
        $lote->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Lote actualizado.',
            'data' => $lote,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/lotes/{lote}
    public function destroy(Lote $lote)
    {
        $lote->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Lote eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/lotes/{lote}/almacenes
    public function listStocks(Lote $lote)
    {
        $stocks = $lote->stocks()->with('almacen')->orderBy('ultima_actualizacion', 'desc')->paginate(50);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de stock por almacén para el lote.',
            'data' => $stocks,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/lotes/{lote}/almacenes
    public function upsertStock(Request $request, Lote $lote)
    {
        $data = $request->validate([
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'cantidad' => ['required', 'integer', 'min:0'],
            'hospital_id' => ['required', 'exists:hospitales,id'],
        ]);

        $stock = LoteAlmacen::updateOrCreate(
            [
                'lote_id' => $lote->id,
                'almacen_id' => $data['almacen_id'],
            ],
            [
                'cantidad' => $data['cantidad'],
                'ultima_actualizacion' => now(),
                'hospital_id' => $data['hospital_id'],
            ]
        );

        return response()->json([
            'status' => true,
            'mensaje' => 'Stock actualizado para el lote y almacén.',
            'data' => $stock,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/lotes/{lote}/almacenes/{almacen_id}
    public function deleteStock(Lote $lote, int $almacen_id)
    {
        $stock = LoteAlmacen::where('lote_id', $lote->id)->where('almacen_id', $almacen_id)->first();
        if (!$stock) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Stock no encontrado para ese almacén.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        $stock->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Stock eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
