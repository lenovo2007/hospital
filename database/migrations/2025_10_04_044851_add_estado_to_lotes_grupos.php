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
        Schema::table('lotes_grupos', function (Blueprint $table) {
            if (!Schema::hasColumn('lotes_grupos', 'estado')) {
                $table->enum('estado', ['pendiente', 'entregado', 'recibido'])->default('pendiente')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotes_grupos', function (Blueprint $table) {
            if (Schema::hasColumn('lotes_grupos', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};
