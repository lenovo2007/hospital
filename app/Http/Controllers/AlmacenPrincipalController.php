<?php

namespace App\Http\Controllers;

use App\Models\AlmacenPrincipal;
use Illuminate\Http\Request;

class AlmacenPrincipalController extends Controller
{
    public function index()
    {
        $items = AlmacenPrincipal::latest()->paginate(15);
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
            'nombre' => ['required','string','max:255'],
        ]);
        $item = AlmacenPrincipal::create($data);
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
            'nombre' => ['sometimes','required','string','max:255'],
        ]);
        $almacenes_principale->update($data);
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
