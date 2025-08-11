<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    // GET /api/almacenes
    public function index()
    {
        $items = Almacen::latest()->paginate(15);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de almacenes.',
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/almacenes
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
        ]);

        $item = Almacen::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/almacenes/{almacen}
    public function show(Almacen $almacen)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de almacén.',
            'data' => $almacen,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/almacenes/{almacen}
    public function update(Request $request, Almacen $almacen)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
        ]);

        $almacen->update($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén actualizado.',
            'data' => $almacen,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/almacenes/{almacen}
    public function destroy(Almacen $almacen)
    {
        $almacen->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Almacén eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
