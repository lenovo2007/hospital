<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lotes_almacenes')
            && Schema::hasColumn('lotes_almacenes', 'tipo_almacen')
            && ! Schema::hasColumn('lotes_almacenes', 'almacen_tipo')) {
            DB::statement('ALTER TABLE lotes_almacenes CHANGE `tipo_almacen` `almacen_tipo` VARCHAR(100) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lotes_almacenes')
            && Schema::hasColumn('lotes_almacenes', 'almacen_tipo')
            && ! Schema::hasColumn('lotes_almacenes', 'tipo_almacen')) {
            DB::statement('ALTER TABLE lotes_almacenes CHANGE `almacen_tipo` `tipo_almacen` VARCHAR(100) NOT NULL');
        }
    }
};
