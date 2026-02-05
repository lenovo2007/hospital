<?php

namespace App\Http\Controllers;

use App\Models\AlmacenCentral;
use App\Models\AlmacenFarmacia;
use App\Models\AlmacenParalelo;
use App\Models\AlmacenPrincipal;
use App\Models\AlmacenServiciosApoyo;
use App\Models\AlmacenServiciosAtenciones;
use App\Models\Lote;
use App\Models\Hospital;
use App\Models\LoteGrupo;
use App\Models\MovimientoStock;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MovimientoStockController extends Controller
{
    public function ingresos(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoStock::query()
            ->where('tipo', 'entrada')
            ->when($request->filled('tipo_movimiento'), fn ($q) => $q->where('tipo_movimiento', $request->tipo_movimiento))
            ->when($request->filled('destino_hospital_id'), fn ($q) => $q->where('destino_hospital_id', $request->destino_hospital_id))
            ->when($request->filled('destino_sede_id'), fn ($q) => $q->where('destino_sede_id', $request->destino_sede_id))
            ->orderByDesc('created_at');

        $movimientos = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de movimientos de ingreso (tipo=entrada).',
            'data' => $movimientos,
        ]);
    }

    public function historialPorOrigenSede(int $origenSedeId, Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $detallado = (bool) $request->query('detallado', false);

        $with = $detallado
            ? ['destinoHospital', 'destinoSede', 'origenHospital', 'origenSede', 'usuario', 'usuarioReceptor']
            : ['destinoHospital:id,nombre', 'destinoSede:id,nombre', 'origenHospital:id,nombre', 'origenSede:id,nombre'];

        $query = MovimientoStock::with($with)
            ->where(function ($q) use ($origenSedeId) {
                $q->where('origen_sede_id', $origenSedeId)
                    ->orWhere(function ($q2) use ($origenSedeId) {
                        $q2->where('destino_sede_id', $origenSedeId)
                            ->where('tipo', 'entrada');
                    });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('tipo_movimiento'), fn ($q) => $q->where('tipo_movimiento', $request->tipo_movimiento))
            ->when($request->filled('codigo_grupo'), fn ($q) => $q->where('codigo_grupo', $request->codigo_grupo))
            ->orderByDesc('created_at');

        $movimientos = $query->paginate($perPage);

        if (!$detallado) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Historial de movimientos por sede (origen/ingresos).',
                'data' => $movimientos,
            ]);
        }

        $codigos = $movimientos->getCollection()
            ->pluck('codigo_grupo')
            ->filter()
            ->unique()
            ->values();

        $lotesPorCodigo = $codigos->isNotEmpty()
            ? LoteGrupo::whereIn('codigo', $codigos)->where('status', 'activo')->get()->groupBy('codigo')
            : collect();

        $loteIds = $lotesPorCodigo->isNotEmpty()
            ? $lotesPorCodigo->flatten(1)->pluck('lote_id')->filter()->unique()
            : collect();

        $lotesPorId = $loteIds->isNotEmpty()
            ? Lote::with('insumo')->whereIn('id', $loteIds)->get()->keyBy('id')
            : collect();

        $insumoIdFiltro = $request->filled('insumo_id') ? (int) $request->query('insumo_id') : null;

        $movimientos->getCollection()->transform(function (MovimientoStock $movimiento) use ($lotesPorCodigo, $lotesPorId, $insumoIdFiltro) {
            $codigo = $movimiento->codigo_grupo;

            $movimiento->lotes_grupos = $codigo
                ? ($lotesPorCodigo->get($codigo)?->values() ?? collect())
                : collect();

            $totalesPorInsumo = [];
            $total = 0;

            $movimiento->lotes_grupos = $movimiento->lotes_grupos->map(function (LoteGrupo $grupo) use ($lotesPorId, &$totalesPorInsumo, &$total, $insumoIdFiltro) {
                $grupo->lote = $lotesPorId->get($grupo->lote_id);

                $cantidad = (int) ($grupo->cantidad_entrada > 0 ? $grupo->cantidad_entrada : $grupo->cantidad_salida);
                $insumoId = (int) ($grupo->lote?->id_insumo ?? 0);
                if ($cantidad > 0 && $insumoId > 0 && ($insumoIdFiltro === null || $insumoIdFiltro === $insumoId)) {
                    $totalesPorInsumo[$insumoId] = ($totalesPorInsumo[$insumoId] ?? 0) + $cantidad;
                    $total += $cantidad;
                }

                return $grupo;
            });

            $almacenOrigen = $this->resolveAlmacenInfo($movimiento->origen_almacen_tipo, $movimiento->origen_almacen_id);
            $almacenDestino = $this->resolveAlmacenInfo($movimiento->destino_almacen_tipo, $movimiento->destino_almacen_id);

            $movimiento->origen_almacen_nombre = $almacenOrigen['nombre'] ?? null;
            $movimiento->destino_almacen_nombre = $almacenDestino['nombre'] ?? null;

            $movimiento->destino_hospital = $movimiento->destinoHospital;
            $movimiento->destino_sede = $movimiento->destinoSede;
            $movimiento->origen_hospital = $movimiento->origenHospital;
            $movimiento->origen_sede = $movimiento->origenSede;

            $movimiento->totales_por_insumo = $totalesPorInsumo;
            $movimiento->cantidad_total_items = $total;

            return $movimiento;
        });

        return response()->json([
            'status' => true,
            'mensaje' => 'Historial de movimientos por sede (origen/ingresos).',
            'data' => $movimientos,
        ]);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoStock::query()
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('destino_hospital_id'), fn ($q) => $q->where('destino_hospital_id', $request->destino_hospital_id))
            ->when($request->filled('destino_sede_id'), fn ($q) => $q->where('destino_sede_id', $request->destino_sede_id))
            ->when($request->filled('origen_hospital_id'), fn ($q) => $q->where('origen_hospital_id', $request->origen_hospital_id))
            ->when($request->filled('origen_sede_id'), fn ($q) => $q->where('origen_sede_id', $request->origen_sede_id))
            ->when($request->filled('codigo_grupo'), fn ($q) => $q->where('codigo_grupo', $request->codigo_grupo))
            ->orderByDesc('created_at');

        $movimientos = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de movimientos de stock.',
            'data' => $movimientos,
        ]);
    }

    public function porDestinoSede(int $destinoSedeId, Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoStock::with(['destinoHospital', 'destinoSede', 'origenHospital', 'origenSede', 'usuario', 'usuarioReceptor'])
            ->where('destino_sede_id', $destinoSedeId)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('destino_hospital_id'), fn ($q) => $q->where('destino_hospital_id', $request->destino_hospital_id))
            ->orderByDesc('created_at');

        $movimientos = $query->paginate($perPage);

        $codigos = $movimientos->getCollection()
            ->pluck('codigo_grupo')
            ->filter()
            ->unique()
            ->values();

        $lotesPorCodigo = $codigos->isNotEmpty()
            ? LoteGrupo::whereIn('codigo', $codigos)->get()->groupBy('codigo')
            : collect();

        $loteIds = $lotesPorCodigo->isNotEmpty()
            ? $lotesPorCodigo->flatten(1)->pluck('lote_id')->filter()->unique()
            : collect();

        $lotesPorId = $loteIds->isNotEmpty()
            ? Lote::with('insumo')->whereIn('id', $loteIds)->get()->keyBy('id')
            : collect();

        $movimientos->getCollection()->transform(function (MovimientoStock $movimiento) use ($lotesPorCodigo, $lotesPorId) {
            $codigo = $movimiento->codigo_grupo;
            $movimiento->lotes_grupos = $codigo
                ? ($lotesPorCodigo->get($codigo)?->values() ?? collect())
                : collect();

            $movimiento->lotes_grupos = $movimiento->lotes_grupos->map(function (LoteGrupo $grupo) use ($lotesPorId) {
                $grupo->lote = $lotesPorId->get($grupo->lote_id);
                return $grupo;
            });

            $almacenOrigen = $this->resolveAlmacenInfo($movimiento->origen_almacen_tipo, $movimiento->origen_almacen_id);
            $almacenDestino = $this->resolveAlmacenInfo($movimiento->destino_almacen_tipo, $movimiento->destino_almacen_id);

            $movimiento->origen_almacen_nombre = $almacenOrigen['nombre'] ?? null;
            $movimiento->destino_almacen_nombre = $almacenDestino['nombre'] ?? null;

            $movimiento->destino_hospital = $movimiento->destinoHospital;
            $movimiento->destino_sede = $movimiento->destinoSede;
            $movimiento->origen_hospital = $movimiento->origenHospital;
            $movimiento->origen_sede = $movimiento->origenSede;

            return $movimiento;
        });

        return response()->json([
            'status' => true,
            'mensaje' => 'Movimientos de stock por sede destino.',
            'data' => $movimientos,
        ]);
    }

    public function porOrigenSede(int $origenSedeId, Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoStock::with(['destinoHospital', 'destinoSede', 'origenHospital', 'origenSede', 'usuario', 'usuarioReceptor'])
            ->where('origen_sede_id', $origenSedeId)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('origen_hospital_id'), fn ($q) => $q->where('origen_hospital_id', $request->origen_hospital_id))
            ->orderByDesc('created_at');

        $movimientos = $query->paginate($perPage);

        $codigos = $movimientos->getCollection()
            ->pluck('codigo_grupo')
            ->filter()
            ->unique()
            ->values();

        $lotesPorCodigo = $codigos->isNotEmpty()
            ? LoteGrupo::whereIn('codigo', $codigos)->get()->groupBy('codigo')
            : collect();

        $loteIds = $lotesPorCodigo->isNotEmpty()
            ? $lotesPorCodigo->flatten(1)->pluck('lote_id')->filter()->unique()
            : collect();

        $lotesPorId = $loteIds->isNotEmpty()
            ? Lote::with('insumo')->whereIn('id', $loteIds)->get()->keyBy('id')
            : collect();

        $movimientos->getCollection()->transform(function (MovimientoStock $movimiento) use ($lotesPorCodigo, $lotesPorId) {
            $codigo = $movimiento->codigo_grupo;
            $movimiento->lotes_grupos = $codigo
                ? ($lotesPorCodigo->get($codigo)?->values() ?? collect())
                : collect();

            $movimiento->lotes_grupos = $movimiento->lotes_grupos->map(function (LoteGrupo $grupo) use ($lotesPorId) {
                $grupo->lote = $lotesPorId->get($grupo->lote_id);
                return $grupo;
            });

            $almacenOrigen = $this->resolveAlmacenInfo($movimiento->origen_almacen_tipo, $movimiento->origen_almacen_id);
            $almacenDestino = $this->resolveAlmacenInfo($movimiento->destino_almacen_tipo, $movimiento->destino_almacen_id);

            $movimiento->origen_almacen_nombre = $almacenOrigen['nombre'] ?? null;
            $movimiento->destino_almacen_nombre = $almacenDestino['nombre'] ?? null;

            $movimiento->destino_hospital = $movimiento->destinoHospital;
            $movimiento->destino_sede = $movimiento->destinoSede;
            $movimiento->origen_hospital = $movimiento->origenHospital;
            $movimiento->origen_sede = $movimiento->origenSede;

            return $movimiento;
        });

        return response()->json([
            'status' => true,
            'mensaje' => 'Movimientos de stock por sede origen.',
            'data' => $movimientos,
        ]);
    }

    private function resolveAlmacenInfo(?string $tipo, $id): array
    {
        if (!$tipo || !$id) {
            return [
                'nombre' => $tipo ? $this->mapAlmacenNombre($tipo) : null,
                'detalle' => null,
            ];
        }

        $map = [
            'almacenCent' => [AlmacenCentral::class, 'Almacén Central'],
            'almacenPrin' => [AlmacenPrincipal::class, 'Almacén Principal'],
            'almacenFarm' => [AlmacenFarmacia::class, 'Almacén Farmacia'],
            'almacenPar' => [AlmacenParalelo::class, 'Almacén Paralelo'],
            'almacenServApoyo' => [AlmacenServiciosApoyo::class, 'Almacén Servicios de Apoyo'],
            'almacenServAtencion' => [AlmacenServiciosAtenciones::class, 'Almacén Servicios de Atención'],
        ];

        if (!array_key_exists($tipo, $map)) {
            return [
                'nombre' => ucfirst(str_replace('_', ' ', $tipo)),
                'detalle' => null,
            ];
        }

        [$model, $nombre] = $map[$tipo];
        $registro = $model::query()->find($id);

        return [
            'nombre' => $nombre,
            'detalle' => $registro,
        ];
    }

    private function mapAlmacenNombre(string $tipo): ?string
    {
        return match ($tipo) {
            'almacenCent' => 'Almacén Central',
            'almacenPrin' => 'Almacén Principal',
            'almacenFarm' => 'Almacén Farmacia',
            'almacenPar' => 'Almacén Paralelo',
            'almacenServApoyo' => 'Almacén Servicios de Apoyo',
            'almacenServAtencion' => 'Almacén Servicios de Atención',
            default => null,
        };
    }

    public function estadisticasPorHospital(int $hospitalId, Request $request)
    {
        try {
            // MOVIMIENTOS RECIBIDOS: Donde origen y destino tienen hospital_id diferente
            // (Movimientos que vienen de fuera del hospital, ej: desde almacén central)
            $movimientosRecibidos = MovimientoStock::with([
                'destinoHospital', 
                'destinoSede', 
                'origenHospital', 
                'origenSede', 
                'usuario', 
                'usuarioReceptor'
            ])
                ->where('destino_hospital_id', $hospitalId)
                ->whereColumn('origen_hospital_id', '!=', 'destino_hospital_id')
                ->orderByDesc('created_at')
                ->get();

            // Cargar lotes_grupos y relaciones para movimientos recibidos
            $movimientosRecibidos = $this->cargarLotesYRelaciones($movimientosRecibidos);

            // MOVIMIENTOS DESPACHADOS: Donde origen y destino tienen el mismo hospital_id
            // (Movimientos internos dentro del hospital, entre sedes/almacenes)
            $movimientosDespachados = MovimientoStock::with([
                'destinoHospital', 
                'destinoSede', 
                'origenHospital', 
                'origenSede', 
                'usuario', 
                'usuarioReceptor'
            ])
                ->where('origen_hospital_id', $hospitalId)
                ->where('destino_hospital_id', $hospitalId)
                ->whereColumn('origen_hospital_id', '=', 'destino_hospital_id')
                ->orderByDesc('created_at')
                ->get();

            // Cargar lotes_grupos y relaciones para movimientos despachados
            $movimientosDespachados = $this->cargarLotesYRelaciones($movimientosDespachados);

            // Agrupar movimientos despachados por tipo de almacén destino
            $movimientosDespachadosPorAlmacen = $movimientosDespachados->groupBy('destino_almacen_tipo')->map(function ($movimientos, $tipoAlmacen) {
                $nombreAlmacen = $this->mapAlmacenNombre($tipoAlmacen);
                return [
                    'tipo_almacen' => $tipoAlmacen,
                    'nombre_almacen' => $nombreAlmacen,
                    'total_movimientos' => $movimientos->count(),
                    'movimientos' => $movimientos->values(),
                ];
            })->values();

            return response()->json([
                'status' => true,
                'mensaje' => 'Estadísticas de movimientos por hospital.',
                'data' => [
                    'hospital_id' => $hospitalId,
                    'movimientos_recibidos' => [
                        'descripcion' => 'Movimientos que vienen de fuera del hospital (desde almacén central u otros hospitales)',
                        'total' => $movimientosRecibidos->count(),
                        'movimientos' => $movimientosRecibidos,
                    ],
                    'movimientos_despachados_por_almacen' => [
                        'descripcion' => 'Movimientos internos dentro del hospital entre diferentes sedes/almacenes',
                        'total' => $movimientosDespachados->count(),
                        'agrupados_por_tipo_almacen' => $movimientosDespachadosPorAlmacen,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al obtener estadísticas: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    private function cargarLotesYRelaciones($movimientos)
    {
        $codigos = $movimientos->pluck('codigo_grupo')->filter()->unique()->values();

        $lotesPorCodigo = $codigos->isNotEmpty()
            ? LoteGrupo::whereIn('codigo', $codigos)->get()->groupBy('codigo')
            : collect();

        $loteIds = $lotesPorCodigo->isNotEmpty()
            ? $lotesPorCodigo->flatten(1)->pluck('lote_id')->filter()->unique()
            : collect();

        $lotesPorId = $loteIds->isNotEmpty()
            ? Lote::with('insumo')->whereIn('id', $loteIds)->get()->keyBy('id')
            : collect();

        return $movimientos->map(function (MovimientoStock $movimiento) use ($lotesPorCodigo, $lotesPorId) {
            $codigo = $movimiento->codigo_grupo;
            $movimiento->lotes_grupos = $codigo
                ? ($lotesPorCodigo->get($codigo)?->values() ?? collect())
                : collect();

            $movimiento->lotes_grupos = $movimiento->lotes_grupos->map(function (LoteGrupo $grupo) use ($lotesPorId) {
                $grupo->lote = $lotesPorId->get($grupo->lote_id);
                return $grupo;
            });

            $almacenOrigen = $this->resolveAlmacenInfo($movimiento->origen_almacen_tipo, $movimiento->origen_almacen_id);
            $almacenDestino = $this->resolveAlmacenInfo($movimiento->destino_almacen_tipo, $movimiento->destino_almacen_id);

            $movimiento->origen_almacen_nombre = $almacenOrigen['nombre'] ?? null;
            $movimiento->origen_almacen_detalle = $almacenOrigen['detalle'];
            $movimiento->destino_almacen_nombre = $almacenDestino['nombre'] ?? null;
            $movimiento->destino_almacen_detalle = $almacenDestino['detalle'];

            $movimiento->destino_hospital = $movimiento->destinoHospital;
            $movimiento->destino_sede = $movimiento->destinoSede;
            $movimiento->origen_hospital = $movimiento->origenHospital;
            $movimiento->origen_sede = $movimiento->origenSede;

            $movimiento->hospital = $movimiento->destinoHospital;
            $movimiento->sede = $movimiento->destinoSede;

            return $movimiento;
        });
    }

    public function show(MovimientoStock $movimientos_stock)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle del movimiento de stock.',
            'data' => $movimientos_stock,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, true);

        try {
            $movimiento = null;
            DB::transaction(function () use ($data, &$movimiento, $request) {
                if (!isset($data['user_id'])) {
                    $data['user_id'] = (int) $request->user()->id;
                }

                $movimiento = MovimientoStock::create($data);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento de stock creado.',
                'data' => $movimiento,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al crear el movimiento de stock.',
                'data' => null,
            ], 500);
        }
    }

    public function update(Request $request, MovimientoStock $movimientos_stock)
    {
        $data = $this->validateData($request, false);

        try {
            DB::transaction(function () use (&$movimientos_stock, $data) {
                $movimientos_stock->update($data);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento de stock actualizado.',
                'data' => $movimientos_stock->refresh(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al actualizar el movimiento de stock.',
                'data' => null,
            ], 500);
        }
    }

    public function destroy(MovimientoStock $movimientos_stock)
    {
        try {
            DB::transaction(function () use (&$movimientos_stock) {
                $movimientos_stock->delete();
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Movimiento de stock eliminado.',
                'data' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al eliminar el movimiento de stock.',
                'data' => null,
            ], 500);
        }
    }

    private function validateData(Request $request, bool $isCreate): array
    {
        $rules = [
            'tipo' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:50'],
            'tipo_movimiento' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:50'],
            'origen_hospital_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'origen_sede_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'destino_hospital_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'destino_sede_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'origen_almacen_tipo' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'origen_almacen_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'destino_almacen_tipo' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'destino_almacen_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'cantidad' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'fecha_despacho' => ['sometimes', 'date'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fecha_recepcion' => ['sometimes', 'nullable', 'date'],
            'observaciones_recepcion' => ['sometimes', 'nullable', 'string', 'max:500'],
            'estado' => ['sometimes', 'string', 'in:pendiente,despachado,entregado,recibido,cancelado'],
            'codigo_grupo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'user_id_receptor' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];

        return $request->validate($rules);
    }
}
