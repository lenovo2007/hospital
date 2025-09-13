<?php

namespace App\Http\Controllers;

use App\Models\AlmacenPrincipal;
use Illuminate\Http\Request;

class AlmacenPrincipalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = AlmacenPrincipal::query();
        if ($status !== 'all') { $query->where('status', $status); }
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
            'nombre' => ['required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }
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
            'status' => ['nullable','in:activo,inactivo'],
        ]);
        $almacenes_principale->update($data);
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
