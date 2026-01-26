<?php

namespace App\Http\Controllers;

use App\Models\FichaInsumo;
use App\Models\Hospital;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

    /**
     * Actualizar ficha identificándola por hospital e insumo.
     * PUT /api/ficha-insumos/hospital/{hospital_id}
     */
    public function updateByHospital(Request $request, int $hospital_id)
    {
        $payload = $request->json()->all();
        $wrapped = (is_array($payload) && !Arr::isAssoc($payload))
            ? ['insumos' => $payload]
            : $payload;

        $validator = Validator::make($wrapped ?? [], [
            'insumos' => ['required', 'array', 'min:1'],
            'insumos.*.id' => ['sometimes', 'integer', 'min:1'],
            'insumos.*.insumo_id' => ['required_without:insumos.*.id', 'integer', 'min:1'],
            'insumos.*.cantidad' => ['sometimes', 'integer', 'min:0'],
            'insumos.*.status' => ['sometimes', 'boolean'],
            'insumos.*.crear_si_no_existe' => ['sometimes', 'boolean'],
        ]);

        $data = $validator->validate();

        $items = collect($data['insumos'])->map(function (array $item) {
            return [
                'id' => $item['id'] ?? null,
                'insumo_id' => $item['insumo_id'] ?? null,
                'cantidad' => $item['cantidad'] ?? null,
                'status' => $item['status'] ?? null,
                'crear_si_no_existe' => $item['crear_si_no_existe'] ?? false,
            ];
        })->all();

        try {
            $resultado = $this->procesarActualizacionesFicha($hospital_id, $items, true);

            $mensaje = match (true) {
                count($resultado['creadas']) > 0 && count($resultado['actualizadas']) > 0 => 'Fichas creadas y actualizadas según disponibilidad.',
                count($resultado['creadas']) > 0 => 'Fichas creadas para insumos que no existían.',
                count($resultado['actualizadas']) > 0 => 'Fichas de insumos actualizadas.',
                default => 'Solicitud procesada sin cambios.',
            };

            return response()->json([
                'status' => true,
                'mensaje' => $mensaje,
                'data' => $resultado,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al actualizar la ficha de insumo por hospital: ' . $e->getMessage(),
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

    public function updateMany(Request $request)
    {
        $payload = $request->json()->all();

        if (is_array($payload) && !Arr::isAssoc($payload)) {
            $itemsInput = $payload;
        } elseif (is_array($payload) && Arr::isAssoc($payload)) {
            $itemsInput = $payload['insumos'] ?? null;
        } else {
            $itemsInput = null;
        }

        $validator = Validator::make(['insumos' => $itemsInput], [
            'insumos' => ['required', 'array', 'min:1'],
            'insumos.*.id' => ['required', 'integer', 'min:1'],
            'insumos.*.insumo_id' => ['sometimes', 'integer', 'min:1'],
            'insumos.*.cantidad' => ['sometimes', 'integer', 'min:0'],
            'insumos.*.status' => ['sometimes', 'boolean'],
        ]);

        $data = $validator->validate();

        $items = collect($data['insumos'])->map(function (array $item) {
            return [
                'id' => $item['id'],
                'insumo_id' => $item['insumo_id'] ?? null,
                'cantidad' => $item['cantidad'] ?? null,
                'status' => $item['status'] ?? null,
            ];
        })->all();

        try {
            $resultado = $this->procesarActualizacionesFichaPorId($items);

            $mensaje = count($resultado['actualizadas']) > 0
                ? 'Ficha(s) de insumos actualizadas.'
                : 'Solicitud procesada sin cambios.';

            return response()->json([
                'status' => true,
                'mensaje' => $mensaje,
                'data' => $resultado,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al actualizar fichas de insumos.',
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

    private function procesarActualizacionesFicha(int $hospitalId, array $items, bool $permitirCreacion = false): array
    {
        $resultado = [
            'creadas' => [],
            'actualizadas' => [],
            'sin_cambios' => [],
            'no_encontradas' => [],
            'errores' => [],
        ];

        DB::transaction(function () use (&$resultado, $items, $hospitalId, $permitirCreacion) {
            foreach ($items as $item) {
                $id = $item['id'] ?? null;
                $insumoId = $item['insumo_id'] ?? null;

                if (!$id && !$insumoId) {
                    $resultado['errores'][] = [
                        'mensaje' => 'Debe indicar al menos id o insumo_id para actualizar.',
                        'item' => $item,
                    ];
                    continue;
                }

                $ficha = FichaInsumo::query()
                    ->where('hospital_id', $hospitalId)
                    ->when($id, fn ($q) => $q->where('id', $id))
                    ->when(!$id && $insumoId, fn ($q) => $q->where('insumo_id', $insumoId))
                    ->first();

                if (!$ficha) {
                    if ($permitirCreacion && $insumoId) {
                        $payloadCreacion = [
                            'hospital_id' => $hospitalId,
                            'insumo_id' => $insumoId,
                            'cantidad' => isset($item['cantidad']) ? (int) $item['cantidad'] : 0,
                            'status' => isset($item['status'])
                                ? (bool) $item['status']
                                : true,
                        ];

                        $ficha = FichaInsumo::create($payloadCreacion);
                        $resultado['creadas'][] = [
                            'id' => $ficha->id,
                            'hospital_id' => $ficha->hospital_id,
                            'insumo_id' => $ficha->insumo_id,
                            'cantidad' => (int) $ficha->cantidad,
                            'status' => (bool) $ficha->status,
                        ];
                        continue;
                    }

                    $resultado['no_encontradas'][] = [
                        'id' => $id,
                        'insumo_id' => $insumoId,
                    ];
                    continue;
                }

                $payload = [];

                if (array_key_exists('cantidad', $item) && $item['cantidad'] !== null) {
                    $payload['cantidad'] = (int) $item['cantidad'];
                }

                if (array_key_exists('status', $item)) {
                    $valor = $item['status'];
                    $payload['status'] = is_bool($valor)
                        ? $valor
                        : (bool) filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                if (empty($payload)) {
                    $resultado['sin_cambios'][] = [
                        'id' => $ficha->id,
                        'insumo_id' => $ficha->insumo_id,
                    ];
                    continue;
                }

                $ficha->update($payload);
                $ficha->refresh();

                $resultado['actualizadas'][] = [
                    'id' => $ficha->id,
                    'hospital_id' => $ficha->hospital_id,
                    'insumo_id' => $ficha->insumo_id,
                    'cantidad' => (int) $ficha->cantidad,
                    'status' => (bool) $ficha->status,
                ];
            }
        });

        return $resultado;
    }

    private function procesarActualizacionesFichaPorId(array $items): array
    {
        $resultado = [
            'creadas' => [],
            'actualizadas' => [],
            'sin_cambios' => [],
            'no_encontradas' => [],
            'errores' => [],
        ];

        DB::transaction(function () use (&$resultado, $items) {
            foreach ($items as $item) {
                $ficha = FichaInsumo::find($item['id']);

                if (!$ficha) {
                    $resultado['no_encontradas'][] = [
                        'id' => $item['id'],
                        'insumo_id' => $item['insumo_id'] ?? null,
                    ];
                    continue;
                }

                if (isset($item['insumo_id']) && (int) $item['insumo_id'] !== (int) $ficha->insumo_id) {
                    $resultado['errores'][] = [
                        'mensaje' => 'El insumo_id no coincide con la ficha indicada.',
                        'item' => $item,
                    ];
                    continue;
                }

                $payload = [];

                if (array_key_exists('cantidad', $item) && $item['cantidad'] !== null) {
                    $payload['cantidad'] = (int) $item['cantidad'];
                }

                if (array_key_exists('status', $item)) {
                    $valor = $item['status'];
                    $payload['status'] = is_bool($valor)
                        ? $valor
                        : (bool) filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                if (empty($payload)) {
                    $resultado['sin_cambios'][] = [
                        'id' => $ficha->id,
                        'insumo_id' => $ficha->insumo_id,
                    ];
                    continue;
                }

                $ficha->update($payload);
                $ficha->refresh();

                $resultado['actualizadas'][] = [
                    'id' => $ficha->id,
                    'hospital_id' => $ficha->hospital_id,
                    'insumo_id' => $ficha->insumo_id,
                    'cantidad' => (int) $ficha->cantidad,
                    'status' => (bool) $ficha->status,
                ];
            }
        });

        return $resultado;
    }
}
