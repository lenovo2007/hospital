<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\AlmacenPrincipalController;
use App\Http\Controllers\AlmacenCentralController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\AlmacenFarmaciaController;
use App\Http\Controllers\AlmacenServiciosAtencionesController;
use App\Http\Controllers\AlmacenServiciosApoyoController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\TipoHospitalDistribucionController;
use App\Http\Controllers\DistribucionCentralController;
use App\Http\Controllers\RecepcionPrincipalController;
use App\Http\Controllers\DespachoPacienteController;
use App\Http\Controllers\EstadisticasController;
use App\Http\Controllers\IngresoDirectoController;
use App\Http\Controllers\FichaInsumoController;
use App\Http\Controllers\MovimientoStockController;
use App\Http\Controllers\MovimientoDiscrepanciaController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\SeguimientoRepartidorController;
use App\Http\Controllers\LoteGrupoController;

// Autenticación con token
// Test route to check if API is working
Route::get('/test', function() {
    return response()->json([
        'status' => true,
        'message' => 'API is working!',
        'data' => [
            'version' => '1.0',
            'timestamp' => now()
        ]
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
// Recuperación de contraseña (público)
Route::post('/users/password/forgot', [UserController::class, 'forgotPassword']);
Route::post('/users/password/reset', [UserController::class, 'resetPassword']);


Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckCrudPermissions::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Rutas GET de usuarios accesibles para cualquier usuario autenticado (sin CheckCrudPermissions)
    Route::get('/users', [UserController::class, 'index'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    // Identificación por EMAIL y CÉDULA (claridad de rutas)
    Route::get('/users/email/{email}', [UserController::class, 'showByEmail'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::put('/users/email/{email}', [UserController::class, 'updateByEmail']);
    Route::put('/users/email/{email}/password', [UserController::class, 'passwordByEmail']);
    Route::get('/users/cedula/{cedula}', [UserController::class, 'showByCedula'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::put('/users/cedula/{cedula}', [UserController::class, 'updateByCedula']);
    Route::put('/users/cedula/{cedula}/password', [UserController::class, 'passwordByCedula']);
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    // POST/PUT/DELETE requieren permisos (se mantiene CheckCrudPermissions)
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Hospitales CRUD
    // Identificación por COD_SICM (claridad de rutas)
    Route::get('/hospitales/cod_sicm/{cod_sicm}', [HospitalController::class, 'showByCodSicm']);
    Route::put('/hospitales/cod_sicm/{cod_sicm}', [HospitalController::class, 'updateByCodSicm']);
    // Importación de hospitales desde Excel (.xlsx)
    Route::post('/hospitales/import', [HospitalController::class, 'importExcel'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::get('/hospitales', [HospitalController::class, 'index']);
    Route::post('/hospitales', [HospitalController::class, 'store']);
    Route::get('/hospitales/{hospital}', [HospitalController::class, 'show']);
    Route::put('/hospitales/{hospital}', [HospitalController::class, 'update']);
    Route::delete('/hospitales/{hospital}', [HospitalController::class, 'destroy']);

    // Sedes CRUD
    Route::get('/sedes', [SedeController::class, 'index']);
    Route::get('/sedes/hospital/{id}', [SedeController::class, 'byHospital']);
    Route::post('/sedes', [SedeController::class, 'store']);
    Route::get('/sedes/{sede}', [SedeController::class, 'show']);
    Route::put('/sedes/{sede}', [SedeController::class, 'update']);
    Route::delete('/sedes/{sede}', [SedeController::class, 'destroy']);

    // Almacenes Principales CRUD
    Route::get('/almacenes_principales', [AlmacenPrincipalController::class, 'index']);
    Route::post('/almacenes_principales', [AlmacenPrincipalController::class, 'store']);
    Route::get('/almacenes_principales/{almacenes_principale}', [AlmacenPrincipalController::class, 'show']);
    Route::put('/almacenes_principales/{almacenes_principale}', [AlmacenPrincipalController::class, 'update']);
    Route::delete('/almacenes_principales/{almacenes_principale}', [AlmacenPrincipalController::class, 'destroy']);

    // Almacenes Centrales CRUD
    Route::get('/almacenes_centrales', [AlmacenCentralController::class, 'index']);
    Route::post('/almacenes_centrales', [AlmacenCentralController::class, 'store']);
    Route::get('/almacenes_centrales/{almacenes_centrale}', [AlmacenCentralController::class, 'show']);
    Route::put('/almacenes_centrales/{almacenes_centrale}', [AlmacenCentralController::class, 'update']);
    Route::delete('/almacenes_centrales/{almacenes_centrale}', [AlmacenCentralController::class, 'destroy']);

    // Almacenes Farmacia CRUD
    Route::get('/almacenes_farmacia', [AlmacenFarmaciaController::class, 'index']);
    Route::post('/almacenes_farmacia', [AlmacenFarmaciaController::class, 'store']);
    Route::get('/almacenes_farmacia/{almacenes_farmacium}', [AlmacenFarmaciaController::class, 'show']);
    Route::put('/almacenes_farmacia/{almacenes_farmacium}', [AlmacenFarmaciaController::class, 'update']);
    Route::delete('/almacenes_farmacia/{almacenes_farmacium}', [AlmacenFarmaciaController::class, 'destroy']);

    // Almacenes Paralelo CRUD
    Route::get('/almacenes_paralelo', [AlmacenParaleloController::class, 'index']);
    Route::post('/almacenes_paralelo', [AlmacenParaleloController::class, 'store']);
    Route::get('/almacenes_paralelo/{almacenes_paralelo}', [AlmacenParaleloController::class, 'show']);
    Route::put('/almacenes_paralelo/{almacenes_paralelo}', [AlmacenParaleloController::class, 'update']);
    Route::delete('/almacenes_paralelo/{almacenes_paralelo}', [AlmacenParaleloController::class, 'destroy']);

    // Almacenes Servicios de Atenciones CRUD
    Route::get('/almacenes_servicios_atenciones', [AlmacenServiciosAtencionesController::class, 'index']);
    Route::post('/almacenes_servicios_atenciones', [AlmacenServiciosAtencionesController::class, 'store']);
    Route::get('/almacenes_servicios_atenciones/{almacenes_servicios_atencione}', [AlmacenServiciosAtencionesController::class, 'show']);
    Route::put('/almacenes_servicios_atenciones/{almacenes_servicios_atencione}', [AlmacenServiciosAtencionesController::class, 'update']);
    Route::delete('/almacenes_servicios_atenciones/{almacenes_servicios_atencione}', [AlmacenServiciosAtencionesController::class, 'destroy']);

    // Inventario - Consulta protegida (requiere auth, sin CheckCrudPermissions)
    Route::get('/inventario/sede/{sedeId}', [\App\Http\Controllers\InventarioController::class, 'listarPorSede'])
        ->where('sedeId', '[0-9]+')
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);

    // Movimientos de stock CRUD
    Route::get('/movimientos-stock', [MovimientoStockController::class, 'index']);
    Route::post('/movimientos-stock', [MovimientoStockController::class, 'store']);
    Route::get('/movimientos-stock/{movimientos_stock}', [MovimientoStockController::class, 'show']);
    Route::put('/movimientos-stock/{movimientos_stock}', [MovimientoStockController::class, 'update']);
    Route::delete('/movimientos-stock/{movimientos_stock}', [MovimientoStockController::class, 'destroy']);
    Route::get('/movimientos-stock/destino_sede/{destino_sede_id}', [MovimientoStockController::class, 'porDestinoSede'])
        ->where('destino_sede_id', '[0-9]+');
    Route::get('/movimientos-stock/origen_sede/{origen_sede_id}', [MovimientoStockController::class, 'porOrigenSede'])
        ->where('origen_sede_id', '[0-9]+');

    // Discrepancias de movimientos CRUD
    Route::get('/movimientos-stock/discrepancias', [MovimientoDiscrepanciaController::class, 'index']);
    Route::post('/movimientos-stock/discrepancias', [MovimientoDiscrepanciaController::class, 'store']);
    Route::get('/movimientos-stock/discrepancias/{movimientos_discrepancia}', [MovimientoDiscrepanciaController::class, 'show']);
    Route::put('/movimientos-stock/discrepancias/{movimientos_discrepancia}', [MovimientoDiscrepanciaController::class, 'update']);
    Route::delete('/movimientos-stock/discrepancias/{movimientos_discrepancia}', [MovimientoDiscrepanciaController::class, 'destroy']);

    // Ficha de insumos CRUD
    Route::get('/ficha-insumos', [FichaInsumoController::class, 'index']);
    Route::post('/ficha-insumos', [FichaInsumoController::class, 'store']);
    Route::get('/ficha-insumos/{ficha_insumo}', [FichaInsumoController::class, 'show']);
    Route::put('/ficha-insumos/{ficha_insumo}', [FichaInsumoController::class, 'update']);
    Route::delete('/ficha-insumos/{ficha_insumo}', [FichaInsumoController::class, 'destroy']);
    
    // Generación automática de fichas de insumos
    Route::post('/ficha-insumos/generar/{hospital_id}', [FichaInsumoController::class, 'generarFichasHospital'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::post('/ficha-insumos/generar-todos', [FichaInsumoController::class, 'generarFichasTodosHospitales'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::post('/ficha-insumos/sincronizar-insumo/{insumo_id}', [FichaInsumoController::class, 'sincronizarNuevoInsumo'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);

    // Inventario - Registro de lotes y almacenamiento
    Route::post('/inventario/registrar', [\App\Http\Controllers\InventarioController::class, 'registrar']);
    // Importación de inventario desde Excel (.xls/.xlsx)
    Route::post('/inventario/import', [\App\Http\Controllers\InventarioController::class, 'importExcel']);

    // Almacenes Servicios de Apoyo CRUD
    Route::get('/almacenes_servicios_apoyo', [AlmacenServiciosApoyoController::class, 'index']);
    Route::post('/almacenes_servicios_apoyo', [AlmacenServiciosApoyoController::class, 'store']);
    Route::get('/almacenes_servicios_apoyo/{almacenes_servicios_apoyo}', [AlmacenServiciosApoyoController::class, 'show']);
    Route::put('/almacenes_servicios_apoyo/{almacenes_servicios_apoyo}', [AlmacenServiciosApoyoController::class, 'update']);
    Route::delete('/almacenes_servicios_apoyo/{almacenes_servicios_apoyo}', [AlmacenServiciosApoyoController::class, 'destroy']);

    // Insumos CRUD
    // Identificación por CÓDIGO (claridad de rutas)
    Route::get('/insumos/sede/{sede_id}', [InsumoController::class, 'indexBySede']);
    Route::get('/insumos/codigo/{codigo}', [InsumoController::class, 'showByCodigo']);
    Route::put('/insumos/codigo/{codigo}', [InsumoController::class, 'updateByCodigo']);
    // Importación de insumos desde Excel (.xlsx)
    // Requiere auth pero NO pasa por CheckCrudPermissions para evitar bloqueos en importación
    Route::post('/insumos/import', [InsumoController::class, 'importExcel'])
        ->withoutMiddleware(\App\Http\Middleware\CheckCrudPermissions::class);
    Route::get('/insumos', [InsumoController::class, 'index']);
    Route::post('/insumos', [InsumoController::class, 'store']);
    Route::get('/insumos/{insumo}', [InsumoController::class, 'show']);
    Route::put('/insumos/{insumo}', [InsumoController::class, 'update']);
    Route::delete('/insumos/{insumo}', [InsumoController::class, 'destroy']);

    // Lotes CRUD y manejo de stock por almacén
    Route::get('/lotes', [LoteController::class, 'index']);
    Route::post('/lotes', [LoteController::class, 'store']);
    Route::get('/lotes/{lote}', [LoteController::class, 'show']);
    Route::put('/lotes/{lote}', [LoteController::class, 'update']);
    Route::delete('/lotes/{lote}', [LoteController::class, 'destroy']);

    // Stock por almacén de un lote específico
    Route::get('/lotes/{lote}/almacenes', [LoteController::class, 'listStocks']);
    Route::post('/lotes/{lote}/almacenes', [LoteController::class, 'upsertStock']);
    Route::delete('/lotes/{lote}/almacenes/{almacen_id}', [LoteController::class, 'deleteStock']);

    // Configuración de porcentajes por tipo de hospital (único registro)
    Route::get('/tipos_hospital_distribuciones', [TipoHospitalDistribucionController::class, 'index']);
    Route::post('/tipos_hospital_distribuciones', [TipoHospitalDistribucionController::class, 'store']);

    // Distribución desde almacén central hacia principal (hospital)
    Route::post('/movimiento/almacen/salida', [DistribucionCentralController::class, 'salida']);

    // Recepción en almacén principal de una distribución central
    Route::post('/movimiento/almacen/entrada', [RecepcionPrincipalController::class, 'recibir']);

    // Despachos a pacientes - CRUD simplificado
    Route::prefix('despachos-pacientes')->group(function () {
        Route::get('/', [DespachoPacienteController::class, 'index']);
        Route::get('/sede/{sede_id}', [DespachoPacienteController::class, 'porSede']);
        Route::get('/sede/{sede_id}/simple', [DespachoPacienteController::class, 'porSedeSimple']);
        Route::get('/test-insumos/{codigo}', [DespachoPacienteController::class, 'testInsumos']);
        Route::post('/', [DespachoPacienteController::class, 'despachar']);
        Route::get('/{id}', [DespachoPacienteController::class, 'show']);
        Route::put('/{id}', [DespachoPacienteController::class, 'update']);
        Route::delete('/{id}', [DespachoPacienteController::class, 'destroy']);
    });

    // Ingresos directos - Donaciones, compras, ajustes
    Route::prefix('ingresos-directos')->group(function () {
        Route::get('/', [IngresoDirectoController::class, 'index']);
        Route::get('/sede/{sede_id}', [IngresoDirectoController::class, 'porSede']);
        Route::post('/', [IngresoDirectoController::class, 'store']);
        Route::get('/{id}', [IngresoDirectoController::class, 'show']);
    });

    // Estadísticas - Endpoints separados
    Route::prefix('estadisticas')->group(function () {
        // Rutas generales (sin sede específica)
        Route::get('/dashboard', [EstadisticasController::class, 'dashboard']);
        Route::get('/insumos', [EstadisticasController::class, 'insumos']);
        Route::get('/movimientos-estados', [EstadisticasController::class, 'movimientosEstados']);
        Route::get('/insumos-faltantes', [EstadisticasController::class, 'insumosFaltantes']);
        Route::get('/pacientes-estados', [EstadisticasController::class, 'pacientesEstados']);
        Route::get('/flujo-inventario', [EstadisticasController::class, 'flujoInventario']);
        Route::get('/insumos-recientes', [EstadisticasController::class, 'insumosRecientes']);
        
        // Rutas específicas por sede
        Route::get('/dashboard/sede/{sede_id}', [EstadisticasController::class, 'dashboardPorSede']);
        Route::get('/insumos/sede/{sede_id}', [EstadisticasController::class, 'insumosPorSede']);
        Route::get('/movimientos-estados/sede/{sede_id}', [EstadisticasController::class, 'movimientosEstadosPorSede']);
        Route::get('/insumos-faltantes/sede/{sede_id}', [EstadisticasController::class, 'insumosFaltantesPorSede']);
        Route::get('/pacientes-estados/sede/{sede_id}', [EstadisticasController::class, 'pacientesEstadosPorSede']);
        Route::get('/flujo-inventario/sede/{sede_id}', [EstadisticasController::class, 'flujoInventarioPorSede']);
        Route::get('/insumos-recientes/sede/{sede_id}', [EstadisticasController::class, 'insumosRecientesPorSede']);
    });

    // CRUD Seguimientos (Administración)
    Route::apiResource('seguimientos', SeguimientoController::class);
    Route::get('/seguimientos/movimiento/{movimiento_stock_id}', [SeguimientoController::class, 'porMovimiento']);

    // Rutas del Repartidor
    Route::prefix('repartidor')->group(function () {
        Route::post('/seguimiento', [SeguimientoRepartidorController::class, 'actualizarSeguimiento']);
        Route::get('/seguimiento/{movimiento_stock_id}', [SeguimientoRepartidorController::class, 'obtenerSeguimiento']);
        Route::get('/movimientos', [SeguimientoRepartidorController::class, 'movimientosRepartidor']);
        Route::get('/movimientos-pendientes', [SeguimientoRepartidorController::class, 'movimientosPendientes']);
        Route::get('/movimientos-en-camino/{sede_id}', [SeguimientoRepartidorController::class, 'movimientosEnCamino']);
        Route::get('/movimientos-en-camino/origen/{sede_id}', [SeguimientoRepartidorController::class, 'movimientosEnCaminoOrigen']);
        Route::get('/movimientos-entregados/{sede_id}', [SeguimientoRepartidorController::class, 'movimientosEntregados']);
    });

    // Distribución interna desde principal hacia farmacia/paralelo/servicios
    Route::post('/distribucion/principal', [DistribucionInternaController::class, 'distribuir']);

    // Distribución automática desde central por porcentaje (varios hospitales y lotes)
    Route::post('/distribucion/automatica/central', [DistribucionAutomaticaController::class, 'distribuirPorPorcentaje']);

    // Rutas de grupos de lote
    Route::get('/lote-grupo', [LoteGrupoController::class, 'index']);
    Route::post('/lote-grupo', [LoteGrupoController::class, 'store']);
    Route::post('/lote-grupo/crear-desde-movimiento', [LoteGrupoController::class, 'crearDesdeMovimiento']);
    Route::get('/lote-grupo/{codigo}', [LoteGrupoController::class, 'show']);
    Route::put('/lote-grupo/{id}', [LoteGrupoController::class, 'update']);
    Route::delete('/lote-grupo/{id}', [LoteGrupoController::class, 'destroy']);
    Route::get('/lote-grupo/estadisticas', [LoteGrupoController::class, 'estadisticas']);

});
