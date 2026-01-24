<?php

namespace App\Http\Controllers;

use App\Models\FichaInsumo;
use App\Models\Hospital;
use App\Models\Insumo;
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
        ], 200);
    }

    /**
     * Listar fichas de insumos filtradas por hospital
     * GET /api/ficha-insumos/hospital/{hospital_id}
     */
    public function indexByHospital(Request $request, $hospital_id)
    {
        try {
            $query = FichaInsumo::query()
                ->where('hospital_id', $hospital_id)
                ->when($request->filled('insumo_id'), fn ($q) => $q->where('insumo_id', $request->insumo_id))
                ->when($request->filled('status'), fn ($q) => $q->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)))
                ->with(['hospital', 'insumo']);

            $fichas = $query->orderByDesc('updated_at')->get();

            $hospital = Hospital::find($hospital_id);

            return response()->json([
                'status' => true,
                'mensaje' => 'Listado de fichas de insumos para el hospital.',
                'data' => $fichas,
                'hospital' => $hospital ? [
                    'id' => $hospital->id,
                    'nombre' => $hospital->nombre,
                    'cod_sicm' => $hospital->cod_sicm,
                ] : null,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al obtener fichas de insumos por hospital.',
                'data' => null,
            ], 500);
        }
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

    /**
     * Generar fichas de insumos para un hospital específico
     * POST /api/ficha-insumos/generar/{hospital_id}
     */
    public function generarFichasHospital($hospital_id)
    {
        try {
            $hospital = Hospital::findOrFail($hospital_id);
            $insumos = Insumo::where('status', true)->get();

            if ($insumos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No hay insumos registrados en el sistema.',
                    'data' => null,
                ], 404);
            }

            $creadas = 0;
            $existentes = 0;

            DB::transaction(function () use ($hospital_id, $insumos, &$creadas, &$existentes) {
                foreach ($insumos as $insumo) {
                    $existe = FichaInsumo::where('hospital_id', $hospital_id)
                        ->where('insumo_id', $insumo->id)
                        ->exists();

                    if (!$existe) {
                        FichaInsumo::create([
                            'hospital_id' => $hospital_id,
                            'insumo_id' => $insumo->id,
                            'cantidad' => 0,
                            'status' => true,
                        ]);
                        $creadas++;
                    } else {
                        $existentes++;
                    }
                }
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Fichas de insumos generadas para el hospital.',
                'data' => [
                    'hospital_id' => $hospital_id,
                    'hospital_nombre' => $hospital->nombre,
                    'total_insumos' => $insumos->count(),
                    'fichas_creadas' => $creadas,
                    'fichas_existentes' => $existentes,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Hospital no encontrado.',
                'data' => null,
            ], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al generar fichas de insumos: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Generar fichas de insumos para TODOS los hospitales
     * POST /api/ficha-insumos/generar-todos
     */
    public function generarFichasTodosHospitales()
    {
        try {
            $hospitales = Hospital::where('status', 'activo')->get();
            $insumos = Insumo::where('status', true)->get();

            if ($hospitales->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No hay hospitales activos en el sistema.',
                    'data' => null,
                ], 404);
            }

            if ($insumos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No hay insumos registrados en el sistema.',
                    'data' => null,
                ], 404);
            }

            $totalCreadas = 0;
            $totalExistentes = 0;
            $hospitalesProcesados = 0;

            DB::transaction(function () use ($hospitales, $insumos, &$totalCreadas, &$totalExistentes, &$hospitalesProcesados) {
                foreach ($hospitales as $hospital) {
                    foreach ($insumos as $insumo) {
                        $existe = FichaInsumo::where('hospital_id', $hospital->id)
                            ->where('insumo_id', $insumo->id)
                            ->exists();

                        if (!$existe) {
                            FichaInsumo::create([
                                'hospital_id' => $hospital->id,
                                'insumo_id' => $insumo->id,
                                'cantidad' => 0,
                                'status' => true,
                            ]);
                            $totalCreadas++;
                        } else {
                            $totalExistentes++;
                        }
                    }
                    $hospitalesProcesados++;
                }
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Fichas de insumos generadas para todos los hospitales.',
                'data' => [
                    'hospitales_procesados' => $hospitalesProcesados,
                    'total_insumos' => $insumos->count(),
                    'fichas_creadas' => $totalCreadas,
                    'fichas_existentes' => $totalExistentes,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al generar fichas de insumos: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Sincronizar fichas cuando se crea un nuevo insumo
     * POST /api/ficha-insumos/sincronizar-insumo/{insumo_id}
     */
    public function sincronizarNuevoInsumo($insumo_id)
    {
        try {
            $insumo = Insumo::findOrFail($insumo_id);
            $hospitales = Hospital::where('status', 'activo')->get();

            if ($hospitales->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'mensaje' => 'No hay hospitales activos en el sistema.',
                    'data' => null,
                ], 404);
            }

            $creadas = 0;
            $existentes = 0;

            DB::transaction(function () use ($insumo_id, $hospitales, &$creadas, &$existentes) {
                foreach ($hospitales as $hospital) {
                    $existe = FichaInsumo::where('hospital_id', $hospital->id)
                        ->where('insumo_id', $insumo_id)
                        ->exists();

                    if (!$existe) {
                        FichaInsumo::create([
                            'hospital_id' => $hospital->id,
                            'insumo_id' => $insumo_id,
                            'cantidad' => 0,
                            'status' => true,
                        ]);
                        $creadas++;
                    } else {
                        $existentes++;
                    }
                }
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Insumo sincronizado en todos los hospitales.',
                'data' => [
                    'insumo_id' => $insumo_id,
                    'insumo_nombre' => $insumo->nombre,
                    'hospitales_procesados' => $hospitales->count(),
                    'fichas_creadas' => $creadas,
                    'fichas_existentes' => $existentes,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Insumo no encontrado.',
                'data' => null,
            ], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al sincronizar insumo: ' . $e->getMessage(),
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
