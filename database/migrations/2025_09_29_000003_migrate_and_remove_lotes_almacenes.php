<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrar datos de lotes_almacenes a tablas específicas de almacén.
     * Luego eliminar la tabla lotes_almacenes y modelos relacionados.
     */
    public function up(): void
    {
        // 1. Migrar datos a almacenes_centrales
        if (Schema::hasTable('lotes_almacenes')) {
            $lotesCentrales = DB::table('lotes_almacenes')
                ->where('almacen_tipo', 'almacenCent')
                ->get();

            foreach ($lotesCentrales as $lote) {
                DB::table('almacenes_centrales')->updateOrInsert([
                    'sede_id' => $lote->sede_id,
                    'lote_id' => $lote->lote_id,
                    'hospital_id' => $lote->hospital_id,
                ], [
                    'cantidad' => $lote->cantidad,
                    'status' => $lote->cantidad > 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Migrar datos a almacenes_principales
        if (Schema::hasTable('lotes_almacenes')) {
            $lotesPrincipales = DB::table('lotes_almacenes')
                ->where('almacen_tipo', 'almacenPrin')
                ->get();

            foreach ($lotesPrincipales as $lote) {
                DB::table('almacenes_principales')->updateOrInsert([
                    'sede_id' => $lote->sede_id,
                    'lote_id' => $lote->lote_id,
                    'hospital_id' => $lote->hospital_id,
                ], [
                    'cantidad' => $lote->cantidad,
                    'status' => $lote->cantidad > 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Migrar datos a almacenes_farmacia
        if (Schema::hasTable('lotes_almacenes')) {
            $lotesFarmacia = DB::table('lotes_almacenes')
                ->where('almacen_tipo', 'almacenFarm')
                ->get();

            foreach ($lotesFarmacia as $lote) {
                DB::table('almacenes_farmacia')->updateOrInsert([
                    'sede_id' => $lote->sede_id,
                    'lote_id' => $lote->lote_id,
                    'hospital_id' => $lote->hospital_id,
                ], [
                    'cantidad' => $lote->cantidad,
                    'status' => $lote->cantidad > 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 4. Migrar datos a almacenes_paralelo
        if (Schema::hasTable('lotes_almacenes')) {
            $lotesParalelo = DB::table('lotes_almacenes')
                ->where('almacen_tipo', 'almacenPar')
                ->get();

            foreach ($lotesParalelo as $lote) {
                DB::table('almacenes_paralelo')->updateOrInsert([
                    'sede_id' => $lote->sede_id,
                    'lote_id' => $lote->lote_id,
                    'hospital_id' => $lote->hospital_id,
                ], [
                    'cantidad' => $lote->cantidad,
                    'status' => $lote->cantidad > 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 5. Migrar datos a almacenes_servicios_apoyo
        if (Schema::hasTable('lotes_almacenes')) {
            $lotesServApoyo = DB::table('lotes_almacenes')
                ->where('almacen_tipo', 'almacenServApoyo')
                ->get();

            foreach ($lotesServApoyo as $lote) {
                DB::table('almacenes_servicios_apoyo')->updateOrInsert([
                    'sede_id' => $lote->sede_id,
                    'lote_id' => $lote->lote_id,
                    'hospital_id' => $lote->hospital_id,
                ], [
                    'cantidad' => $lote->cantidad,
                    'status' => $lote->cantidad > 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 6. Migrar datos a almacenes_servicios_atenciones
        if (Schema::hasTable('lotes_almacenes')) {
            $lotesServAtenciones = DB::table('lotes_almacenes')
                ->where('almacen_tipo', 'almacenServAtenciones')
                ->get();

            foreach ($lotesServAtenciones as $lote) {
                DB::table('almacenes_servicios_atenciones')->updateOrInsert([
                    'sede_id' => $lote->sede_id,
                    'lote_id' => $lote->lote_id,
                    'hospital_id' => $lote->hospital_id,
                ], [
                    'cantidad' => $lote->cantidad,
                    'status' => $lote->cantidad > 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 7. Eliminar la tabla lotes_almacenes
        if (Schema::hasTable('lotes_almacenes')) {
            Schema::dropIfExists('lotes_almacenes');
        }

        // 8. Eliminar modelo LoteAlmacen si existe
        $loteAlmacenFile = app_path('Models/LoteAlmacen.php');
        if (file_exists($loteAlmacenFile)) {
            unlink($loteAlmacenFile);
        }

        // 9. Eliminar servicio StockService si ya no es necesario
        $stockServiceFile = app_path('Services/StockService.php');
        if (file_exists($stockServiceFile)) {
            unlink($stockServiceFile);
        }

        // 10. Actualizar modelos para eliminar referencias a LoteAlmacen
        $this->updateModelFiles();
    }

    /**
     * Revertir la migración (recrear lotes_almacenes desde las tablas específicas)
     */
    public function down(): void
    {
        // Recrear la tabla lotes_almacenes
        Schema::create('lotes_almacenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lote_id');
            $table->string('almacen_tipo', 100);
            $table->unsignedBigInteger('almacen_id');
            $table->unsignedBigInteger('sede_id')->nullable();
            $table->unsignedBigInteger('hospital_id');
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->timestamps();

            $table->foreign('lote_id')->references('id')->on('lotes');
            $table->index(['almacen_tipo', 'almacen_id']);
        });

        // Migrar datos desde almacenes_centrales a lotes_almacenes
        $centrales = DB::table('almacenes_centrales')->get();
        foreach ($centrales as $central) {
            DB::table('lotes_almacenes')->insert([
                'lote_id' => $central->lote_id,
                'almacen_tipo' => 'almacenCent',
                'almacen_id' => $central->sede_id,
                'sede_id' => $central->sede_id,
                'hospital_id' => $central->hospital_id,
                'cantidad' => $central->cantidad,
                'ultima_actualizacion' => now(),
                'created_at' => $central->created_at,
                'updated_at' => $central->updated_at,
            ]);
        }

        // Migrar datos desde almacenes_principales a lotes_almacenes
        $principales = DB::table('almacenes_principales')->get();
        foreach ($principales as $principal) {
            DB::table('lotes_almacenes')->insert([
                'lote_id' => $principal->lote_id,
                'almacen_tipo' => 'almacenPrin',
                'almacen_id' => $principal->sede_id,
                'sede_id' => $principal->sede_id,
                'hospital_id' => $principal->hospital_id,
                'cantidad' => $principal->cantidad,
                'ultima_actualizacion' => now(),
                'created_at' => $principal->created_at,
                'updated_at' => $principal->updated_at,
            ]);
        }

        // Migrar datos desde almacenes_farmacia a lotes_almacenes
        $farmacia = DB::table('almacenes_farmacia')->get();
        foreach ($farmacia as $farm) {
            DB::table('lotes_almacenes')->insert([
                'lote_id' => $farm->lote_id,
                'almacen_tipo' => 'almacenFarm',
                'almacen_id' => $farm->sede_id,
                'sede_id' => $farm->sede_id,
                'hospital_id' => $farm->hospital_id,
                'cantidad' => $farm->cantidad,
                'ultima_actualizacion' => now(),
                'created_at' => $farm->created_at,
                'updated_at' => $farm->updated_at,
            ]);
        }

        // Migrar datos desde almacenes_paralelo a lotes_almacenes
        $paralelo = DB::table('almacenes_paralelo')->get();
        foreach ($paralelo as $par) {
            DB::table('lotes_almacenes')->insert([
                'lote_id' => $par->lote_id,
                'almacen_tipo' => 'almacenPar',
                'almacen_id' => $par->sede_id,
                'sede_id' => $par->sede_id,
                'hospital_id' => $par->hospital_id,
                'cantidad' => $par->cantidad,
                'ultima_actualizacion' => now(),
                'created_at' => $par->created_at,
                'updated_at' => $par->updated_at,
            ]);
        }

        // Migrar datos desde almacenes_servicios_apoyo a lotes_almacenes
        $servApoyo = DB::table('almacenes_servicios_apoyo')->get();
        foreach ($servApoyo as $serv) {
            DB::table('lotes_almacenes')->insert([
                'lote_id' => $serv->lote_id,
                'almacen_tipo' => 'almacenServApoyo',
                'almacen_id' => $serv->sede_id,
                'sede_id' => $serv->sede_id,
                'hospital_id' => $serv->hospital_id,
                'cantidad' => $serv->cantidad,
                'ultima_actualizacion' => now(),
                'created_at' => $serv->created_at,
                'updated_at' => $serv->updated_at,
            ]);
        }

        // Migrar datos desde almacenes_servicios_atenciones a lotes_almacenes
        $servAtenciones = DB::table('almacenes_servicios_atenciones')->get();
        foreach ($servAtenciones as $serv) {
            DB::table('lotes_almacenes')->insert([
                'lote_id' => $serv->lote_id,
                'almacen_tipo' => 'almacenServAtenciones',
                'almacen_id' => $serv->sede_id,
                'sede_id' => $serv->sede_id,
                'hospital_id' => $serv->hospital_id,
                'cantidad' => $serv->cantidad,
                'ultima_actualizacion' => now(),
                'created_at' => $serv->created_at,
                'updated_at' => $serv->updated_at,
            ]);
        }

        // Recrear modelo LoteAlmacen
        $this->createLoteAlmacenModel();

        // Recrear servicio StockService
        $this->createStockService();
    }

    /**
     * Actualizar archivos de modelo para eliminar referencias a LoteAlmacen
     */
    private function updateModelFiles(): void
    {
        // Actualizar modelo Lote si tiene referencias
        $loteFile = app_path('Models/Lote.php');
        if (file_exists($loteFile)) {
            $content = file_get_contents($loteFile);
            $content = str_replace('lotes_almacenes', 'almacenes_centrales', $content);
            file_put_contents($loteFile, $content);
        }

        // Actualizar modelo Insumo si tiene referencias
        $insumoFile = app_path('Models/Insumo.php');
        if (file_exists($insumoFile)) {
            $content = file_get_contents($insumoFile);
            $content = str_replace('lotes_almacenes', 'almacenes_centrales', $content);
            file_put_contents($insumoFile, $content);
        }
    }

    /**
     * Recrear modelo LoteAlmacen para rollback
     */
    private function createLoteAlmacenModel(): void
    {
        $modelContent = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoteAlmacen extends Model
{
    protected $table = 'lotes_almacenes';

    protected $fillable = [
        'lote_id',
        'almacen_tipo',
        'tipo_almacen',
        'almacen_id',
        'sede_id',
        'cantidad',
        'ultima_actualizacion',
        'hospital_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'ultima_actualizacion' => 'datetime',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}
PHP;

        file_put_contents(app_path('Models/LoteAlmacen.php'), $modelContent);
    }

    /**
     * Recrear servicio StockService para rollback
     */
    private function createStockService(): void
    {
        $serviceContent = <<<'PHP'
<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\AlmacenPrincipal;
use App\Models\LoteAlmacen;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class StockService
{
    public function incrementar(int $loteId, string $almacenTipo, int $almacenId, int $cantidad, int $hospitalId, ?int $sedeId = null): LoteAlmacen
    {
        return DB::transaction(function () use ($loteId, $almacenTipo, $almacenId, $cantidad, $hospitalId, $sedeId) {
            $registro = LoteAlmacen::firstOrNew([
                'lote_id' => $loteId,
                'almacen_tipo' => $almacenTipo,
                'almacen_id' => $almacenId,
            ]);
            $registro->hospital_id = $hospitalId;
            if ($sedeId !== null) {
                $registro->sede_id = $sedeId;
            }
            $registro->cantidad = max(0, (int) $registro->cantidad + $cantidad);
            $registro->ultima_actualizacion = now();
            $registro->save();
            return $registro;
        });
    }

    public function disminuir(int $loteId, string $almacenTipo, int $almacenId, int $cantidad): LoteAlmacen
    {
        return DB::transaction(function () use ($loteId, $almacenTipo, $almacenId, $cantidad) {
            $registro = LoteAlmacen::where('lote_id', $loteId)
                ->where('almacen_tipo', $almacenTipo)
                ->where('almacen_id', $almacenId)
                ->lockForUpdate()
                ->first();
            if (!$registro) {
                throw new StockException('Stock no encontrado para este lote y almacén.');
            }
            if ($registro->cantidad < $cantidad) {
                throw new StockException('Stock insuficiente. Disponible: ' . $registro->cantidad);
            }
            $registro->cantidad = (int) $registro->cantidad - $cantidad;
            $registro->ultima_actualizacion = now();
            $registro->save();
            return $registro;
        });
    }

    public function transferir(int $loteId, string $origenTipo, int $origenId, string $destinoTipo, int $destinoId, int $cantidad, int $hospitalIdDestino, ?int $sedeDestinoId = null): void
    {
        DB::transaction(function () use ($loteId, $origenTipo, $origenId, $destinoTipo, $destinoId, $cantidad, $hospitalIdDestino, $sedeDestinoId) {
            $this->disminuir($loteId, $origenTipo, $origenId, $cantidad);
            $this->incrementar($loteId, $destinoTipo, $destinoId, $cantidad, $hospitalIdDestino, $sedeDestinoId);

            if ($destinoTipo === 'almacenPrin') {
                $registroPrincipal = AlmacenPrincipal::firstOrNew([
                    'sede_id' => $sedeDestinoId,
                    'lote_id' => $loteId,
                    'hospital_id' => $hospitalIdDestino,
                ]);
                $registroPrincipal->cantidad = max(0, (int) $registroPrincipal->cantidad + $cantidad);
                $registroPrincipal->status = $registroPrincipal->status ?? true;
                $registroPrincipal->save();
            }
        });
    }
}
PHP;

        file_put_contents(app_path('Services/StockService.php'), $serviceContent);
    }
};
