<?php

namespace App\Http\Controllers;

use App\Models\MovimientoDiscrepancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MovimientoDiscrepanciaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;

        $query = MovimientoDiscrepancia::with([
                'movimientoStock:id,codigo_grupo,estado,fecha_despacho,fecha_recepcion',
                'movimientoStock.origenHospital:id,nombre',
                'movimientoStock.origenSede:id,nombre', 
                'movimientoStock.destinoHospital:id,nombre',
                'movimientoStock.destinoSede:id,nombre',
                'loteGrupo:codigo,lote_id,cantidad_salida,cantidad_entrada,discrepancia,status',
                'loteGrupo.lote:id,codigo,nombre,fecha_vencimiento',
                'loteGrupo.lote.insumo:id,nombre,descripcion'
            ])
            ->when($request->filled('movimiento_stock_id'), fn ($q) => $q->where('movimiento_stock_id', $request->movimiento_stock_id))
            ->when($request->filled('codigo_lote_grupo'), fn ($q) => $q->where('codigo_lote_grupo', $request->codigo_lote_grupo))
            ->orderByDesc('created_at');

        $discrepancias = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de discrepancias de movimiento.',
            'data' => $discrepancias,
        ]);
    }

    public function show(MovimientoDiscrepancia $movimientos_discrepancia)
    {
        $movimientos_discrepancia->load([
            'movimientoStock:id,codigo_grupo,estado,fecha_despacho,fecha_recepcion',
            'movimientoStock.origenHospital:id,nombre',
            'movimientoStock.origenSede:id,nombre', 
            'movimientoStock.destinoHospital:id,nombre',
            'movimientoStock.destinoSede:id,nombre',
            'loteGrupo:codigo,lote_id,cantidad_salida,cantidad_entrada,discrepancia,status',
            'loteGrupo.lote:id,codigo,nombre,fecha_vencimiento',
            'loteGrupo.lote.insumo:id,nombre,descripcion'
        ]);

        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de la discrepancia.',
            'data' => $movimientos_discrepancia,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, true);

        try {
            $discrepancia = null;
            DB::transaction(function () use ($data, &$discrepancia) {
                $discrepancia = MovimientoDiscrepancia::create($data);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Discrepancia registrada.',
                'data' => $discrepancia,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al registrar la discrepancia.',
                'data' => null,
            ], 200);
        }
    }

    public function update(Request $request, MovimientoDiscrepancia $movimientos_discrepancia)
    {
        $data = $this->validateData($request, false);

        try {
            DB::transaction(function () use (&$movimientos_discrepancia, $data) {
                $movimientos_discrepancia->update($data);
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Discrepancia actualizada.',
                'data' => $movimientos_discrepancia->refresh(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al actualizar la discrepancia.',
                'data' => null,
            ], 200);
        }
    }

    public function destroy(MovimientoDiscrepancia $movimientos_discrepancia)
    {
        try {
            DB::transaction(function () use (&$movimientos_discrepancia) {
                $movimientos_discrepancia->delete();
            });

            return response()->json([
                'status' => true,
                'mensaje' => 'Discrepancia eliminada.',
                'data' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'mensaje' => 'Error al eliminar la discrepancia.',
                'data' => null,
            ], 200);
        }
    }

    private function validateData(Request $request, bool $isCreate): array
    {
        $rules = [
            'movimiento_stock_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'codigo_lote_grupo' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:50'],
            'cantidad_esperada' => ['sometimes', 'integer', 'min:0'],
            'cantidad_recibida' => ['sometimes', 'integer', 'min:0'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];

        return $request->validate($rules);
    }
}
