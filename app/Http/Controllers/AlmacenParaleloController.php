<?php

namespace App\Http\Controllers;

use App\Models\AlmacenParalelo;
use Illuminate\Http\Request;

class AlmacenParaleloController extends Controller
{
    public function index(Request $request)
    {
        $statusParam = $request->query('status', 'true');
        $query = AlmacenParalelo::query();
        if ($statusParam !== 'all' && $statusParam !== 'todos') {
            $statusBool = in_array(strtolower((string)$statusParam), ['true','1','activo'], true);
            $query->where('status', $statusBool);
        }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes paralelo.' : 'almacenes_paralelo no encontrado';
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

        $item = new AlmacenParalelo();
        $item->cantidad = $data['cantidad'];
        $item->sede_id = $data['sede_id'];
        $item->lote_id = $data['lote_id'];
        $item->hospital_id = $data['hospital_id'];
        $item->status = array_key_exists('status', $data) ? (bool)$data['status'] : true;
        $item->save();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén paralelo creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $item = AlmacenParalelo::find($id);
        if (!$item) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_paralelo no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén paralelo.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenParalelo $almacenes_paralelo)
    {
        $data = $request->validate([
            'cantidad' => ['sometimes','required','integer','min:0'],
            'sede_id' => ['sometimes','required','integer','exists:sedes,id'],
            'lote_id' => ['sometimes','required','integer','exists:lotes,id'],
            'hospital_id' => ['sometimes','required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);
        if (array_key_exists('cantidad', $data)) { $almacenes_paralelo->cantidad = $data['cantidad']; }
        if (array_key_exists('sede_id', $data)) { $almacenes_paralelo->sede_id = $data['sede_id']; }
        if (array_key_exists('lote_id', $data)) { $almacenes_paralelo->lote_id = $data['lote_id']; }
        if (array_key_exists('hospital_id', $data)) { $almacenes_paralelo->hospital_id = $data['hospital_id']; }
        if (array_key_exists('status', $data)) { $almacenes_paralelo->status = (bool)$data['status']; }
        $almacenes_paralelo->save();
        $almacenes_paralelo->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén paralelo actualizado.',
            'data' => $almacenes_paralelo,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenParalelo $almacenes_paralelo)
    {
        $almacenes_paralelo->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén paralelo eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
