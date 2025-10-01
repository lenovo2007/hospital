<?php

namespace App\Http\Controllers;

use App\Models\FichaInsumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class FichaInsumoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = FichaInsumo::query()
            ->when($request->filled('hospital_id'), fn ($q) => $q->where('hospital_id', $request->hospital_id))
            ->when($request->filled('insumo_id'), fn ($q) => $q->where('insumo_id', $request->insumo_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)));

        $fichas = $query->orderByDesc('updated_at')->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de fichas de insumos.',
            'data' => $fichas,
        ]);
    }

    public function show(FichaInsumo $ficha_insumo)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de la ficha de insumo.',
            'data' => $ficha_insumo,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, true);

        try {
            $ficha = null;
            DB::transaction(function () use ($data, &$ficha) {
                $ficha = FichaInsumo::create($data);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Ficha de insumo creada.',
                'data' => $ficha,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al crear la ficha de insumo.',
                'data' => null,
            ], 500);
        }
    }

    public function update(Request $request, FichaInsumo $ficha_insumo)
    {
        $data = $this->validateData($request, false);

        try {
            DB::transaction(function () use (&$ficha_insumo, $data) {
                $ficha_insumo->update($data);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Ficha de insumo actualizada.',
                'data' => $ficha_insumo->refresh(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al actualizar la ficha de insumo.',
                'data' => null,
            ], 500);
        }
    }

    public function destroy(FichaInsumo $ficha_insumo)
    {
        try {
            DB::transaction(function () use (&$ficha_insumo) {
                $ficha_insumo->delete();
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Ficha de insumo eliminada.',
                'data' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al eliminar la ficha de insumo.',
                'data' => null,
            ], 500);
        }
    }

    private function validateData(Request $request, bool $isCreate): array
    {
        $rules = [
            'hospital_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'insumo_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'cantidad' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
