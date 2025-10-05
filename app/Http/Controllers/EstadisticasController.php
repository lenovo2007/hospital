<?php

namespace App\Http\Controllers;

use App\Models\DespachoPaciente;
use App\Models\MovimientoStock;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EstadisticasController extends Controller
{
    /**
     * Estadísticas generales de insumos con comparación mensual
     * GET /api/estadisticas/insumos
     */
    public function insumos(Request $request)
    {
        $mesActual = Carbon::now();
        $mesAnterior = Carbon::now()->subMonth();

        // Total de insumos activos este mes
        $totalActual = Insumo::where('status', true)
            ->whereMonth('created_at', $mesActual->month)
            ->whereYear('created_at', $mesActual->year)
            ->count();

        // Total de insumos activos mes anterior
        $totalAnterior = Insumo::where('status', true)
            ->whereMonth('created_at', $mesAnterior->month)
            ->whereYear('created_at', $mesAnterior->year)
            ->count();

        // Calcular porcentaje de cambio
        $porcentajeCambio = 0;
        $tendencia = 'igual';
        
        if ($totalAnterior > 0) {
            $porcentajeCambio = round((($totalActual - $totalAnterior) / $totalAnterior) * 100, 1);
            $tendencia = $porcentajeCambio > 0 ? 'aumento' : ($porcentajeCambio < 0 ? 'disminucion' : 'igual');
        } elseif ($totalActual > 0) {
            $porcentajeCambio = 100;
            $tendencia = 'aumento';
        }

        // Total general de insumos
        $totalGeneral = Insumo::where('status', true)->count();

        // Insumos por sede
        $insumosPorSede = DB::table('insumos')
            ->join('sedes', 'insumos.sede_id', '=', 'sedes.id')
            ->where('insumos.status', true)
            ->select('sedes.nombre as sede', DB::raw('COUNT(*) as total'))
            ->groupBy('sedes.id', 'sedes.nombre')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'total_general' => $totalGeneral,
                'nuevos_este_mes' => $totalActual,
                'nuevos_mes_anterior' => $totalAnterior,
                'porcentaje_cambio' => abs($porcentajeCambio),
                'tendencia' => $tendencia,
                'mensaje_comparacion' => $this->generarMensajeComparacion($porcentajeCambio, $tendencia),
                'insumos_por_sede' => $insumosPorSede,
            ]
        ]);
    }

    /**
     * Estadísticas de estados de movimientos de stock
     * GET /api/estadisticas/movimientos-estados
     */
    public function movimientosEstados(Request $request)
    {
        // Filtros opcionales
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');

        $query = MovimientoStock::query();

        if ($fechaDesde) {
            $query->whereDate('created_at', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('created_at', '<=', $fechaHasta);
        }

        // Contar por estados
        $estadisticas = $query->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        // Asegurar que todos los estados estén presentes
        $estados = ['pendiente', 'despachado', 'entregado', 'recibido', 'cancelado'];
        $resultado = [];

        foreach ($estados as $estado) {
            $resultado[$estado] = $estadisticas->get($estado)?->total ?? 0;
        }

        // Total de movimientos
        $total = array_sum($resultado);

        // Calcular porcentajes
        $porcentajes = [];
        foreach ($resultado as $estado => $cantidad) {
            $porcentajes[$estado] = $total > 0 ? round(($cantidad / $total) * 100, 1) : 0;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'total_movimientos' => $total,
                'por_estado' => $resultado,
                'porcentajes' => $porcentajes,
                'periodo' => [
                    'desde' => $fechaDesde ?? 'inicio',
                    'hasta' => $fechaHasta ?? 'hoy'
                ]
            ]
        ]);
    }

    /**
     * Insumos en falta en almacén central
     * GET /api/estadisticas/insumos-faltantes
     */
    public function insumosFaltantes(Request $request)
    {
        // Insumos que existen en la tabla insumos pero no tienen stock en almacén central
        $insumosSinStock = DB::table('insumos')
            ->leftJoin('lotes', 'insumos.id', '=', 'lotes.id_insumo')
            ->leftJoin('almacenes_centrales', function($join) {
                $join->on('lotes.id', '=', 'almacenes_centrales.lote_id')
                     ->where('almacenes_centrales.status', true)
                     ->where('almacenes_centrales.cantidad', '>', 0);
            })
            ->where('insumos.status', true)
            ->whereNull('almacenes_centrales.id')
            ->select(
                'insumos.id',
                'insumos.nombre',
                'insumos.codigo',
                'insumos.codigo_alterno',
                'insumos.presentacion'
            )
            ->distinct()
            ->get();

        // Insumos con stock bajo (menos de 10 unidades)
        $insumosStockBajo = DB::table('insumos')
            ->join('lotes', 'insumos.id', '=', 'lotes.id_insumo')
            ->join('almacenes_centrales', 'lotes.id', '=', 'almacenes_centrales.lote_id')
            ->where('insumos.status', true)
            ->where('almacenes_centrales.status', true)
            ->where('almacenes_centrales.cantidad', '>', 0)
            ->where('almacenes_centrales.cantidad', '<=', 10)
            ->select(
                'insumos.id',
                'insumos.nombre',
                'insumos.codigo',
                'insumos.codigo_alterno',
                'insumos.presentacion',
                DB::raw('SUM(almacenes_centrales.cantidad) as stock_actual')
            )
            ->groupBy('insumos.id', 'insumos.nombre', 'insumos.codigo', 'insumos.codigo_alterno', 'insumos.presentacion')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'insumos_sin_stock' => [
                    'total' => $insumosSinStock->count(),
                    'listado' => $insumosSinStock
                ],
                'insumos_stock_bajo' => [
                    'total' => $insumosStockBajo->count(),
                    'listado' => $insumosStockBajo
                ],
                'resumen' => [
                    'total_problemas' => $insumosSinStock->count() + $insumosStockBajo->count(),
                    'sin_stock' => $insumosSinStock->count(),
                    'stock_bajo' => $insumosStockBajo->count()
                ]
            ]
        ]);
    }

    /**
     * Estadísticas de estados de despachos a pacientes
     * GET /api/estadisticas/pacientes-estados
     */
    public function pacientesEstados(Request $request)
    {
        // Filtros opcionales
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $hospitalId = $request->get('hospital_id');
        $sedeId = $request->get('sede_id');

        $query = DespachoPaciente::where('status', true);

        if ($fechaDesde) {
            $query->whereDate('fecha_despacho', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('fecha_despacho', '<=', $fechaHasta);
        }

        if ($hospitalId) {
            $query->where('hospital_id', $hospitalId);
        }

        if ($sedeId) {
            $query->where('sede_id', $sedeId);
        }

        // Contar por estados
        $estadisticas = $query->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        // Asegurar que todos los estados estén presentes
        $estados = ['pendiente', 'despachado', 'entregado', 'cancelado'];
        $resultado = [];

        foreach ($estados as $estado) {
            $resultado[$estado] = $estadisticas->get($estado)?->total ?? 0;
        }

        // Total de despachos
        $total = array_sum($resultado);

        // Calcular porcentajes
        $porcentajes = [];
        foreach ($resultado as $estado => $cantidad) {
            $porcentajes[$estado] = $total > 0 ? round(($cantidad / $total) * 100, 1) : 0;
        }

        // Estadísticas adicionales
        $totalPacientesUnicos = $query->distinct('paciente_cedula')->count('paciente_cedula');
        $totalItemsDespachados = $query->sum('cantidad_total_items');

        return response()->json([
            'status' => true,
            'data' => [
                'total_despachos' => $total,
                'total_pacientes_unicos' => $totalPacientesUnicos,
                'total_items_despachados' => $totalItemsDespachados,
                'por_estado' => $resultado,
                'porcentajes' => $porcentajes,
                'filtros_aplicados' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'hospital_id' => $hospitalId,
                    'sede_id' => $sedeId
                ]
            ]
        ]);
    }

    /**
     * Dashboard general con resumen de todas las estadísticas
     * GET /api/estadisticas/dashboard
     */
    public function dashboard(Request $request)
    {
        // Obtener estadísticas básicas de cada endpoint
        $insumos = $this->insumos($request)->getData()->data;
        $movimientos = $this->movimientosEstados($request)->getData()->data;
        $faltantes = $this->insumosFaltantes($request)->getData()->data;
        $pacientes = $this->pacientesEstados($request)->getData()->data;

        return response()->json([
            'status' => true,
            'data' => [
                'resumen_insumos' => [
                    'total' => $insumos->total_general,
                    'tendencia' => $insumos->tendencia,
                    'mensaje' => $insumos->mensaje_comparacion
                ],
                'resumen_movimientos' => [
                    'total' => $movimientos->total_movimientos,
                    'recibidos' => $movimientos->por_estado->recibido ?? 0,
                    'pendientes' => $movimientos->por_estado->pendiente ?? 0
                ],
                'resumen_faltantes' => [
                    'total_problemas' => $faltantes->resumen->total_problemas,
                    'sin_stock' => $faltantes->resumen->sin_stock,
                    'stock_bajo' => $faltantes->resumen->stock_bajo
                ],
                'resumen_pacientes' => [
                    'total_despachos' => $pacientes->total_despachos,
                    'pacientes_unicos' => $pacientes->total_pacientes_unicos,
                    'entregados' => $pacientes->por_estado->entregado ?? 0
                ],
                'fecha_actualizacion' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Generar mensaje de comparación mensual
     */
    private function generarMensajeComparacion($porcentaje, $tendencia): string
    {
        $porcentajeAbs = abs($porcentaje);
        
        switch ($tendencia) {
            case 'aumento':
                return "{$porcentajeAbs}% más que el mes pasado";
            case 'disminucion':
                return "{$porcentajeAbs}% menos que el mes pasado";
            default:
                return "Sin cambios respecto al mes pasado";
        }
    }
}
