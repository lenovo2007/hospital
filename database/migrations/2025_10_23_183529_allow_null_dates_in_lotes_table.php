<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->change();
            $table->date('fecha_ingreso')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable(false)->change();
            $table->date('fecha_ingreso')->nullable(false)->change();
        });
    }
};
