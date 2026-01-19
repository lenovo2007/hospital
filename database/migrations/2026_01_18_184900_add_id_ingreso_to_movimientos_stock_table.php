<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_stock', 'id_ingreso')) {
                $table->unsignedBigInteger('id_ingreso')->nullable()->after('id');
                $table->index('id_ingreso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_stock', 'id_ingreso')) {
                $table->dropIndex(['id_ingreso']);
                $table->dropColumn('id_ingreso');
            }
        });
    }
};
