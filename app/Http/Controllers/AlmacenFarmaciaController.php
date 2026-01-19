<?php

namespace App\Http\Controllers;

use App\Models\AlmacenFarmacia;
use Illuminate\Http\Request;

class AlmacenFarmaciaController extends Controller
{
    public function index(Request $request)
    {
        $statusParam = $request->query('status', 'true');
        $query = AlmacenFarmacia::query();
        if ($statusParam !== 'all' && $statusParam !== 'todos') {
            $statusBool = in_array(strtolower((string)$statusParam), ['true', '1', 'activo'], true);
            $query->where('status', $statusBool);
        }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes farmacia.' : 'almacenes_farmacia no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cantidad' => ['required','integer','min:0'],
            'sede_id' => ['required','integer','exists:sedes,id'],
            'lote_id' => ['required','integer','exists:lotes,id'],
            'hospital_id' => ['required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);

        $item = new AlmacenFarmacia();
        $item->cantidad = $data['cantidad'];
        $item->sede_id = $data['sede_id'];
        $item->lote_id = $data['lote_id'];
        $item->hospital_id = $data['hospital_id'];
        $item->status = array_key_exists('status', $data) ? (bool)$data['status'] : true;
        $item->save();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén farmacia creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $item = AlmacenFarmacia::find($id);
        if (!$item) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_farmacia no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén farmacia.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenFarmacia $almacenes_farmacium)
    {
        $data = $request->validate([
            'cantidad' => ['sometimes','required','integer','min:0'],
            'sede_id' => ['sometimes','required','integer','exists:sedes,id'],
            'lote_id' => ['sometimes','required','integer','exists:lotes,id'],
            'hospital_id' => ['sometimes','required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);
        if (array_key_exists('cantidad', $data)) { $almacenes_farmacium->cantidad = $data['cantidad']; }
        if (array_key_exists('sede_id', $data)) { $almacenes_farmacium->sede_id = $data['sede_id']; }
        if (array_key_exists('lote_id', $data)) { $almacenes_farmacium->lote_id = $data['lote_id']; }
        if (array_key_exists('hospital_id', $data)) { $almacenes_farmacium->hospital_id = $data['hospital_id']; }
        if (array_key_exists('status', $data)) { $almacenes_farmacium->status = (bool)$data['status']; }
        $almacenes_farmacium->save();
        $almacenes_farmacium->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén farmacia actualizado.',
            'data' => $almacenes_farmacium,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenFarmacia $almacenes_farmacium)
    {
        $almacenes_farmacium->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén farmacia eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
