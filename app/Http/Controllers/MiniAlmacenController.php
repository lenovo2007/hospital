<?php

namespace App\Http\Controllers;

use App\Models\MiniAlmacen;
use Illuminate\Http\Request;

class MiniAlmacenController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = MiniAlmacen::query();
        if ($status !== 'all') { $query->where('status', $status); }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de mini almacenes.' : 'mini_almacenes no encontrado';
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
        $item = MiniAlmacen::create($data);
        return response()->json([
            'status' => true,
            'mensaje' => 'Mini almacén creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $mini = MiniAlmacen::find($id);
        if (!$mini) {
            return response()->json([
                'status' => true,
                'mensaje' => 'mini_almacenes no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de mini almacén.',
            'data' => $mini,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, MiniAlmacen $mini_almacene)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        $mini_almacene->update($data);
        $mini_almacene->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Mini almacén actualizado.',
            'data' => $mini_almacene,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(MiniAlmacen $mini_almacene)
    {
        $mini_almacene->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Mini almacén eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
