<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    // GET /api/sedes
    public function index()
    {
        $items = Sede::latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de sedes.' : 'sedes no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/sedes
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'tipo' => ['required','string','max:255'],
            'hospital_id' => ['nullable','integer','exists:hospitales,id'],
        ]);

        $item = Sede::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Sede creada.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/sedes/{id}
    public function show($id)
    {
        $sede = Sede::find($id);
        if (!$sede) {
            return response()->json([
                'status' => true,
                'mensaje' => 'sedes no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de sede.',
            'data' => $sede,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/sedes/{sede}
    public function update(Request $request, Sede $sede)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'hospital_id' => ['nullable','integer','exists:hospitales,id'],
        ]);

        $sede->update($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Sede actualizada.',
            'data' => $sede,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/sedes/{sede}
    public function destroy(Sede $sede)
    {
        $sede->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Sede eliminada.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
