<?php

namespace App\Http\Controllers;

use App\Models\TipoHospitalDistribucion;
use Illuminate\Http\Request;

class TipoHospitalDistribucionController extends Controller
{
    protected function obtenerConfiguracion(): TipoHospitalDistribucion
    {
        return TipoHospitalDistribucion::firstOrCreate([], [
            'tipo1' => 0,
            'tipo2' => 0,
            'tipo3' => 0,
            'tipo4' => 0,
        ]);
    }

    // GET /api/tipos_hospital_distribuciones
    public function index(): \Illuminate\Http\JsonResponse
    {
        $registro = $this->obtenerConfiguracion();
        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución de tipos de hospitales.',
            'data' => $registro,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/tipos_hospital_distribuciones
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'tipo1' => ['required','numeric','min:0','max:100'],
            'tipo2' => ['required','numeric','min:0','max:100'],
            'tipo3' => ['required','numeric','min:0','max:100'],
            'tipo4' => ['required','numeric','min:0','max:100'],
        ]);

        $suma = $data['tipo1'] + $data['tipo2'] + $data['tipo3'] + $data['tipo4'];
        if (abs($suma - 100) > 0.0001) {
            return response()->json([
                'status' => false,
                'mensaje' => 'La suma de los porcentajes debe ser exactamente 100.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $registro = $this->obtenerConfiguracion();
        $registro->fill($data);
        $registro->save();
        $registro->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Distribución actualizada.',
            'data' => $registro,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
