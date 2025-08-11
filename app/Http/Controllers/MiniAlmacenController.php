<?php

namespace App\Http\Controllers;

use App\Models\MiniAlmacen;
use Illuminate\Http\Request;

class MiniAlmacenController extends Controller
{
    public function index()
    {
        $items = MiniAlmacen::latest()->paginate(15);
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
        ]);
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
        ]);
        $mini_almacene->update($data);
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
