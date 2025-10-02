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
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoStock::query()
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('hospital_id'), fn ($q) => $q->where('hospital_id', $request->hospital_id))
            ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
            ->when($request->filled('codigo_grupo'), fn ($q) => $q->where('codigo_grupo', $request->codigo_grupo))
            ->orderByDesc('created_at');

        $movimientos = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de movimientos de stock.',
            'data' => $movimientos,
        ]);
    }

    public function porSede(int $sedeId, Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoStock::with(['hospital', 'sede', 'usuario', 'usuarioReceptor'])
            ->where('sede_id', $sedeId)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('hospital_id'), fn ($q) => $q->where('hospital_id', $request->hospital_id))
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
            $movimiento->origen_almacen_detalle = $almacenOrigen['detalle'];
            $movimiento->destino_almacen_nombre = $almacenDestino['nombre'] ?? null;
            $movimiento->destino_almacen_detalle = $almacenDestino['detalle'];

            return $movimiento;
        });

        return response()->json([
            'status' => true,
            'mensaje' => 'Movimientos de stock por sede.',
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
            'hospital_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'sede_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'origen_almacen_tipo' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'origen_almacen_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'destino_almacen_tipo' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'destino_almacen_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'cantidad' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'fecha_despacho' => ['sometimes', 'date'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fecha_recepcion' => ['sometimes', 'nullable', 'date'],
            'observaciones_recepcion' => ['sometimes', 'nullable', 'string', 'max:500'],
            'estado' => ['sometimes', 'string', 'in:pendiente,completado,inconsistente,cancelado'],
            'codigo_grupo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'user_id_receptor' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];

        return $request->validate($rules);
    }
}
