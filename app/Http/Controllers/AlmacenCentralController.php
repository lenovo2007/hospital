<?php

namespace App\Http\Controllers;

use App\Models\AlmacenCentral;
use Illuminate\Http\Request;

class AlmacenCentralController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = AlmacenCentral::query();
        if ($status !== 'all') { $query->where('status', $status); }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de almacenes centrales.' : 'almacenes_centrales no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }
        $item = AlmacenCentral::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén central creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $central = AlmacenCentral::find($id);
        if (!$central) {
            return response()->json([
                'status' => true,
                'mensaje' => 'almacenes_centrales no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén central.',
            'data' => $central,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, AlmacenCentral $almacenes_centrale)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        $almacenes_centrale->update($data);
        $almacenes_centrale->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén central actualizado.',
            'data' => $almacenes_centrale,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlmacenCentral $almacenes_centrale)
    {
        $almacenes_centrale->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén central eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
