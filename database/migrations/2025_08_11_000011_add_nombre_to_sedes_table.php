<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sedes') && !Schema::hasColumn('sedes', 'nombre')) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->string('nombre')->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sedes') && Schema::hasColumn('sedes', 'nombre')) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->dropColumn('nombre');
            });
        }
    }
};
