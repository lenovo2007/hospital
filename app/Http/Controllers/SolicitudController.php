<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;

class SolicitudController extends Controller
{
    /**
     * Display a listing of all solicitudes.
     */
    public function index()
    {
        $solicitudes = Solicitud::with('hospital', 'sede', 'insumo', 'user')->latest()->paginate(15);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de solicitudes.',
            'data' => $solicitudes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Store a newly created solicitud.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'unique:solicitudes,codigo'],
            'hospital_id' => ['required', 'integer', 'exists:hospitales,id'],
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'insumo_id' => ['required', 'integer', 'exists:insumos,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'estado' => ['nullable', 'in:pendiente,aprobada,rechazada,entregada'],
            'descripcion' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'fecha_solicitud' => ['nullable', 'date'],
        ]);

        if (!isset($data['estado'])) {
            $data['estado'] = 'pendiente';
        }
        if (!isset($data['fecha_solicitud'])) {
            $data['fecha_solicitud'] = now();
        }

        $solicitud = Solicitud::create($data);
        $solicitud->load('hospital', 'sede', 'insumo', 'user');

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud creada exitosamente.',
            'data' => $solicitud,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Display the specified solicitud.
     */
    public function show(string $id)
    {
        $solicitud = Solicitud::with('hospital', 'sede', 'insumo', 'user')->find($id);
        if (!$solicitud) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Solicitud no encontrada.',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de solicitud.',
            'data' => $solicitud,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Update the specified solicitud.
     */
    public function update(Request $request, string $id)
    {
        $solicitud = Solicitud::find($id);
        if (!$solicitud) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Solicitud no encontrada.',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'codigo' => ['sometimes', 'required', 'string', 'unique:solicitudes,codigo,' . $id],
            'hospital_id' => ['sometimes', 'required', 'integer', 'exists:hospitales,id'],
            'sede_id' => ['sometimes', 'required', 'integer', 'exists:sedes,id'],
            'insumo_id' => ['sometimes', 'required', 'integer', 'exists:insumos,id'],
            'cantidad' => ['sometimes', 'required', 'integer', 'min:1'],
            'estado' => ['sometimes', 'in:pendiente,aprobada,rechazada,entregada'],
            'descripcion' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'fecha_aprobacion' => ['nullable', 'date'],
            'fecha_entrega' => ['nullable', 'date'],
        ]);

        $solicitud->update($data);
        $solicitud->load('hospital', 'sede', 'insumo', 'user');

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud actualizada exitosamente.',
            'data' => $solicitud,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Remove the specified solicitud.
     */
    public function destroy(string $id)
    {
        $solicitud = Solicitud::find($id);
        if (!$solicitud) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Solicitud no encontrada.',
                'data' => null,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $solicitud->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitud eliminada exitosamente.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get solicitudes by sede_id.
     */
    public function bySede(string $sede_id)
    {
        $solicitudes = Solicitud::with('hospital', 'sede', 'insumo', 'user')
            ->where('sede_id', $sede_id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitudes por sede.',
            'data' => $solicitudes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get solicitudes by hospital_id.
     */
    public function byHospital(string $hospital_id)
    {
        $solicitudes = Solicitud::with('hospital', 'sede', 'insumo', 'user')
            ->where('hospital_id', $hospital_id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => true,
            'mensaje' => 'Solicitudes por hospital.',
            'data' => $solicitudes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
