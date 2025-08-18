<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HospitalController extends Controller
{
    // GET /api/hospitales
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        // Mapear 'todos' a 'all' y validar
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) {
            $status = 'activo';
        }

        $query = Hospital::query();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $items = $query->latest()->paginate(15);
        $mensaje = $items->total() > 0 ? 'Listado de hospitales.' : 'hospitales no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/buscar_por_rif?rif=J-12345678-9
    public function buscarPorRif(Request $request)
    {
        $data = $request->validate([
            'rif' => ['required','string','max:255'],
        ]);

        $rif = $data['rif'];

        $hospital = Hospital::where('rif', $rif)->first();

        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital encontrado.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/actualizar_por_rif?rif=J-12345678-9
    public function actualizarPorRif(Request $request)
    {
        $validated = $request->validate([
            'rif' => ['required','string','max:255'],
        ]);

        $hospital = Hospital::where('rif', $validated['rif'])->first();

        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'email' => ['nullable','email','max:255', Rule::unique('hospitales','email')->ignore($hospital->id)],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
        ]);

        // Evitar que cambien el RIF desde este endpoint
        unset($data['rif']);

        $hospital->update($data);
        $hospital->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado por RIF.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/rif/{rif}
    public function showByRif(Request $request, string $rif)
    {
        $hospital = Hospital::where('rif', $rif)->first();
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de hospital por RIF.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/rif/{rif}
    public function updateByRif(Request $request, string $rif)
    {
        $hospital = Hospital::where('rif', $rif)->first();
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Hospital no encontrado por ese RIF.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            'email' => ['nullable','email','max:255', Rule::unique('hospitales','email')->ignore($hospital->id)],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
        ]);

        $hospital->update($data);
        $hospital->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado por RIF.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/hospitales
    public function store(Request $request)
    {
        \Log::info('HospitalController@store: request received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'user_id' => optional($request->user())->id,
        ]);
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'rif' => ['required','string','max:255','unique:hospitales,rif'],
            'email' => ['nullable','email','max:255','unique:hospitales,email'],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'rif.unique' => 'El hospital ya ha sido registrado anteriormente (RIF duplicado).',
            'email.unique' => 'El email ya está registrado para otro hospital.',
        ]);

        \Log::info('HospitalController@store: data validated', $data);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }
        $item = Hospital::create($data);

        \Log::info('HospitalController@store: hospital created', ['id' => $item->id]);
        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital creado.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/hospitales/{id}
    public function show($id)
    {
        $hospital = Hospital::find($id);
        if (!$hospital) {
            return response()->json([
                'status' => true,
                'mensaje' => 'hospitales no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de hospital.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/hospitales/{hospital}
    public function update(Request $request, Hospital $hospital)
    {
        $data = $request->validate([
            'nombre' => ['sometimes','required','string','max:255'],
            // rif no se actualiza aquí para mantener unicidad consistente por otro endpoint
            'email' => ['nullable','email','max:255', Rule::unique('hospitales','email')->ignore($hospital->id)],
            'telefono' => ['nullable','string','max:50'],
            'ubicacion' => ['nullable','array'],
            'ubicacion.lat' => ['nullable','numeric','between:-90,90'],
            'ubicacion.lng' => ['nullable','numeric','between:-180,180'],
            'direccion' => ['nullable','string','max:255'],
            'tipo' => ['sometimes','required','string','max:255'],
            'status' => ['nullable','in:activo,inactivo'],
        ], [
            'email.unique' => 'El email ya está registrado para otro hospital.',
        ]);

        $hospital->update($data);
        $hospital->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital actualizado.',
            'data' => $hospital,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/hospitals/{hospital}
    public function destroy(Hospital $hospital)
    {
        $hospital->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Hospital eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
