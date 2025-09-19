<?php

namespace App\Http\Controllers;

use App\Models\TipoHospitalDistribucion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipoHospitalDistribucionController extends Controller
{
    // GET /api/tipos_hospital_distribuciones
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $items = TipoHospitalDistribucion::orderBy('tipo')->paginate($perPage);
        return response()->json([
            'status' => true,
            'mensaje' => $items->total() > 0 ? 'Listado de distribución de tipos de hospitales.' : 'No hay registros.',
            'data' => $items,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/tipos_hospital_distribuciones
    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required','string','max:100','unique:tipos_hospital_distribuciones,tipo'],
            'porcentaje' => ['required','numeric','min:0','max:100'],
        ]);

        $item = TipoHospitalDistribucion::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución creada.',
            'data' => $item,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/tipos_hospital_distribuciones/{id}
    public function show(TipoHospitalDistribucion $tipos_hospital_distribucione)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de distribución.',
            'data' => $tipos_hospital_distribucione,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/tipos_hospital_distribuciones/{id}
    public function update(Request $request, TipoHospitalDistribucion $tipos_hospital_distribucione)
    {
        $data = $request->validate([
            'tipo' => ['sometimes','required','string','max:100', Rule::unique('tipos_hospital_distribuciones','tipo')->ignore($tipos_hospital_distribucione->id)],
            'porcentaje' => ['sometimes','required','numeric','min:0','max:100'],
        ]);

        $tipos_hospital_distribucione->update($data);
        $tipos_hospital_distribucione->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución actualizada.',
            'data' => $tipos_hospital_distribucione,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // DELETE /api/tipos_hospital_distribuciones/{id}
    public function destroy(TipoHospitalDistribucion $tipos_hospital_distribucione)
    {
        $tipos_hospital_distribucione->delete();

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución eliminada.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
