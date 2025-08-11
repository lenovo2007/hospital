<?php

namespace App\Http\Controllers;

use App\Models\MiniAlmacen;
use Illuminate\Http\Request;

class MiniAlmacenController extends Controller
{
    public function index()
    {
        $items = MiniAlmacen::latest()->paginate(15);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de mini almacenes.',
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

    public function show(MiniAlmacen $mini_almacene)
    {
        // Nota: Enlace de modelo: {mini_almacene} basado en nombre de tabla
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de mini almacén.',
            'data' => $mini_almacene,
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
