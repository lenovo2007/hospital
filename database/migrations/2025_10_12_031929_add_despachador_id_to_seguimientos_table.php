<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar si la columna ya existe antes de agregarla
        if (!Schema::hasColumn('seguimientos', 'despachador_id')) {
            Schema::table('seguimientos', function (Blueprint $table) {
                $table->unsignedBigInteger('despachador_id')->nullable()->after('user_id_repartidor');
            });
        }
        
        // Verificar si la clave foránea ya existe antes de agregarla
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'seguimientos' 
            AND COLUMN_NAME = 'despachador_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        if (empty($foreignKeys)) {
            Schema::table('seguimientos', function (Blueprint $table) {
                $table->foreign('despachador_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            // Verificar si la clave foránea existe antes de eliminarla
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'seguimientos' 
                AND COLUMN_NAME = 'despachador_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            if (!empty($foreignKeys)) {
                $table->dropForeign(['despachador_id']);
            }
            
            // Verificar si la columna existe antes de eliminarla
            if (Schema::hasColumn('seguimientos', 'despachador_id')) {
                $table->dropColumn('despachador_id');
            }
        });
    }
};
