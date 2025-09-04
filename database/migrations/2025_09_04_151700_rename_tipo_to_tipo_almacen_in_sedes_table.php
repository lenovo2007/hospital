<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sedes') && Schema::hasColumn('sedes', 'tipo')) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->renameColumn('tipo', 'tipo_almacen');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sedes') && Schema::hasColumn('sedes', 'tipo_almacen')) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->renameColumn('tipo_almacen', 'tipo');
            });
        }
    }
};
