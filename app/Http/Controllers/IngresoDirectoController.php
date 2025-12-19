<?php

namespace App\Http\Controllers;

use App\Models\IngresoDirecto;
use App\Models\LoteGrupo;
use App\Models\Lote;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class IngresoDirectoController extends Controller
{
    /**
     * Listar ingresos directos
     * GET /api/ingresos-directos
     */
    public function index(Request $request)
    {
        $query = IngresoDirecto::with(['hospital', 'sede', 'usuario'])
            ->where('status', true)
            ->orderBy('created_at', 'desc');

        // Filtros opcionales
        if ($request->has('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->has('hospital_id')) {
            $query->where('hospital_id', $request->hospital_id);
        }

        if ($request->has('tipo_ingreso')) {
            $query->where('tipo_ingreso', $request->tipo_ingreso);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha_ingreso', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha_ingreso', '<=', $request->fecha_hasta);
        }

        $ingresos = $query->paginate(15);

        return response()->json([
            'status' => true,
            'data' => $ingresos
        ]);
    }

    /**
     * Listar ingresos por sede
     * GET /api/ingresos-directos/sede/{sede_id}
     */
    public function porSede(Request $request, $sede_id)
    {
        $query = IngresoDirecto::with(['hospital', 'sede', 'usuario'])
            ->where('status', true)
            ->where('sede_id', $sede_id)
            ->orderBy('created_at', 'desc');

        // Filtros adicionales
        if ($request->has('tipo_ingreso')) {
            $query->where('tipo_ingreso', $request->tipo_ingreso);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $ingresos = $query->paginate(15);

        return response()->json([
            'status' => true,
            'data' => $ingresos
        ]);
    }

    /**
     * Registrar nuevo ingreso directo
     * POST /api/ingresos-directos
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_ingreso' => 'required|in:donacion,compra,ajuste_inventario,devolucion,almacenado,otro',
            'fecha_ingreso' => 'required|date',
            'sede_id' => 'required|exists:sedes,id',
            'items' => 'required|array|min:1',
            'items.*.insumo_id' => 'required|exists:insumos,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.numero_lote' => 'required|string',
            'items.*.fecha_vencimiento' => 'required|date|after:today',
            'items.*.precio_unitario' => 'nullable|numeric|min:0',
            'proveedor_nombre' => 'nullable|string|max:255',
            'proveedor_rif' => 'nullable|string|max:20',
            'numero_factura' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'motivo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error de validación en los datos enviados',
                'errores' => $validator->errors(),
                'tipos_ingreso_validos' => ['donacion', 'compra', 'ajuste_inventario', 'devolucion', 'almacenado', 'otro']
            ], 400);
        }

        $data = $validator->validated();
        $userId = Auth::id();

        try {
            return DB::transaction(function () use ($data, $userId) {
                // Obtener información de la sede
                $sede = DB::table('sedes')->where('id', $data['sede_id'])->first();
                if (!$sede) {
                    throw new \InvalidArgumentException('Sede no encontrada.');
                }

                $hospital = DB::table('hospitales')->where('id', $sede->hospital_id)->first();

                // Generar códigos únicos
                $codigoIngreso = IngresoDirecto::generarCodigoIngreso();
                $codigoLotesGrupo = LoteGrupo::generarCodigo();

                // Calcular totales
                $cantidadTotalItems = array_sum(array_column($data['items'], 'cantidad'));
                $valorTotal = 0;

                // Procesar cada item del ingreso
                foreach ($data['items'] as $item) {
                    // Crear o actualizar lote
                    $lote = Lote::firstOrCreate([
                        'id_insumo' => $item['insumo_id'],
                        'numero_lote' => $item['numero_lote'],
                        'fecha_vencimiento' => $item['fecha_vencimiento'],
                        'hospital_id' => $sede->hospital_id,
                    ]);

                    // Crear registro en lotes_grupos
                    LoteGrupo::create([
                        'codigo' => $codigoLotesGrupo,
                        'lote_id' => $lote->id,
                        'cantidad_salida' => 0,
                        'cantidad_entrada' => $item['cantidad'],
                        'discrepancia' => false,
                        'status' => 'activo',
                    ]);

                    // Agregar al almacén correspondiente
                    $this->agregarAlAlmacen(
                        $this->obtenerTablaAlmacen($sede->tipo_almacen),
                        $sede->hospital_id,
                        $sede->id,
                        $lote->id,
                        $item['cantidad']
                    );

                    // Calcular valor total si se proporciona precio
                    if (isset($item['precio_unitario'])) {
                        $valorTotal += $item['precio_unitario'] * $item['cantidad'];
                    }
                }

                // Crear el registro de ingreso directo
                $ingreso = IngresoDirecto::create([
                    'codigo_ingreso' => $codigoIngreso,
                    'tipo_ingreso' => $data['tipo_ingreso'],
                    'fecha_ingreso' => $data['fecha_ingreso'],
                    'hospital_id' => $sede->hospital_id,
                    'sede_id' => $data['sede_id'],
                    'almacen_tipo' => $sede->tipo_almacen,
                    'proveedor_nombre' => $data['proveedor_nombre'] ?? null,
                    'proveedor_rif' => $data['proveedor_rif'] ?? null,
                    'numero_factura' => $data['numero_factura'] ?? null,
                    'valor_total' => $valorTotal > 0 ? $valorTotal : null,
                    'observaciones' => $data['observaciones'] ?? null,
                    'motivo' => $data['motivo'] ?? null,
                    'cantidad_total_items' => $cantidadTotalItems,
                    'estado' => 'procesado', // Se procesa automáticamente
                    'codigo_lotes_grupo' => $codigoLotesGrupo,
                    'user_id' => $userId,
                    'fecha_procesado' => now(),
                    'user_id_procesado' => $userId,
                ]);

                return response()->json([
                    'status' => true,
                    'mensaje' => 'Ingreso directo registrado exitosamente.',
                    'data' => [
                        'codigo_ingreso' => $codigoIngreso,
                        'codigo_lotes_grupo' => $codigoLotesGrupo,
                        'tipo_ingreso' => $data['tipo_ingreso'],
                        'sede' => [
                            'nombre' => $sede->nombre,
                            'hospital' => $hospital->nombre
                        ],
                        'cantidad_items' => $cantidadTotalItems,
                        'valor_total' => $valorTotal > 0 ? $valorTotal : null,
                        'fecha_ingreso' => $data['fecha_ingreso']
                    ]
                ], 201);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Error al registrar el ingreso directo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalles de un ingreso específico
     * GET /api/ingresos-directos/{id}
     */
    public function show($id)
    {
        $ingreso = IngresoDirecto::with(['hospital', 'sede', 'usuario', 'usuarioProcesado'])
            ->where('status', true)
            ->findOrFail($id);

        // Obtener los lotes asociados
        $lotesDetalle = $this->obtenerLotesIngreso($ingreso->codigo_lotes_grupo);

        return response()->json([
            'status' => true,
            'data' => [
                'ingreso' => $ingreso,
                'lotes_detalle' => $lotesDetalle
            ]
        ]);
    }

    /**
     * Agregar cantidad al almacén correspondiente
     */
    private function agregarAlAlmacen(string $tablaAlmacen, int $hospitalId, int $sedeId, int $loteId, int $cantidad)
    {
        $existente = DB::table($tablaAlmacen)
            ->where('hospital_id', $hospitalId)
            ->where('sede_id', $sedeId)
            ->where('lote_id', $loteId)
            ->where('status', true)
            ->first();

        if ($existente) {
            // Actualizar cantidad existente
            DB::table($tablaAlmacen)
                ->where('id', $existente->id)
                ->update([
                    'cantidad' => $existente->cantidad + $cantidad,
                    'updated_at' => now()
                ]);
        } else {
            // Crear nuevo registro
            DB::table($tablaAlmacen)->insert([
                'cantidad' => $cantidad,
                'hospital_id' => $hospitalId,
                'sede_id' => $sedeId,
                'lote_id' => $loteId,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Obtener los lotes asociados a un ingreso
     */
    private function obtenerLotesIngreso($codigoLotesGrupo)
    {
        return DB::table('lotes_grupos')
            ->leftJoin('lotes', 'lotes_grupos.lote_id', '=', 'lotes.id')
            ->leftJoin('insumos', 'lotes.id_insumo', '=', 'insumos.id')
            ->where('lotes_grupos.codigo', $codigoLotesGrupo)
            ->where('lotes_grupos.status', 'activo')
            ->select(
                'insumos.id as insumo_id',
                'insumos.nombre as insumo_nombre',
                'insumos.codigo as insumo_codigo',
                'insumos.presentacion as insumo_presentacion',
                'lotes.id as lote_id',
                'lotes.numero_lote',
                'lotes.fecha_vencimiento',
                'lotes_grupos.cantidad_entrada as cantidad_ingresada',
                'lotes_grupos.id as lote_grupo_id'
            )
            ->get();
    }

    /**
     * Obtiene el nombre de la tabla según el tipo de almacén
     */
    private function obtenerTablaAlmacen(string $tipoAlmacen): string
    {
        return match ($tipoAlmacen) {
            'almacenCent' => 'almacenes_centrales',
            'almacenPrin' => 'almacenes_principales',
            'almacenFarm' => 'almacenes_farmacia',
            'almacenPar' => 'almacenes_paralelo',
            'almacenServApoyo' => 'almacenes_servicios_apoyo',
            'almacenServAtenciones' => 'almacenes_servicios_atenciones',
            default => 'almacenes_centrales',
        };
    }
}
