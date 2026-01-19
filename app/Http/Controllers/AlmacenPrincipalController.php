<?php

namespace App\Http\Controllers;

use App\Models\AlmacenPrincipal;
use Illuminate\Http\Request;

class AlmacenPrincipalController extends Controller
{
    public function index(Request $request)
    {
        $statusParam = $request->query('status', 'true');
        $query = AlmacenPrincipal::query();
        if ($statusParam !== 'all' && $statusParam !== 'todos') {
            $statusBool = in_array(strtolower((string)$statusParam), ['true','1','activo'], true);
            $query->where('status', $statusBool);
        }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes principales.' : 'almacenes_principales no encontrado';
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

        $item = new AlmacenPrincipal();
        $item->cantidad = $data['cantidad'];
        $item->sede_id = $data['sede_id'];
        $item->lote_id = $data['lote_id'];
        $item->hospital_id = $data['hospital_id'];
        $item->status = array_key_exists('status', $data) ? (bool)$data['status'] : true;
        $item->save();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén principal creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $principal = AlmacenPrincipal::find($id);
        if (!$principal) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_principales no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén principal.',
            'data' => $principal,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenPrincipal $almacenes_principale)
    {
        $data = $request->validate([
            'cantidad' => ['sometimes','required','integer','min:0'],
            'sede_id' => ['sometimes','required','integer','exists:sedes,id'],
            'lote_id' => ['sometimes','required','integer','exists:lotes,id'],
            'hospital_id' => ['sometimes','required','integer','exists:hospitales,id'],
            'status' => ['nullable','boolean'],
        ]);
        if (array_key_exists('cantidad', $data)) { $almacenes_principale->cantidad = $data['cantidad']; }
        if (array_key_exists('sede_id', $data)) { $almacenes_principale->sede_id = $data['sede_id']; }
        if (array_key_exists('lote_id', $data)) { $almacenes_principale->lote_id = $data['lote_id']; }
        if (array_key_exists('hospital_id', $data)) { $almacenes_principale->hospital_id = $data['hospital_id']; }
        if (array_key_exists('status', $data)) { $almacenes_principale->status = (bool)$data['status']; }
        $almacenes_principale->save();
        $almacenes_principale->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén principal actualizado.',
            'data' => $almacenes_principale,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenPrincipal $almacenes_principale)
    {
        $almacenes_principale->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén principal eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
