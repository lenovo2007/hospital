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
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('despachador_id')->nullable()->after('user_id_repartidor');
        });
        
        // Agregar la clave foránea en una operación separada
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->foreign('despachador_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->dropForeign(['despachador_id']);
            $table->dropColumn('despachador_id');
        });
    }
};
