<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ficha_insumos')) {
            Schema::create('ficha_insumos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hospital_id');
                $table->unsignedBigInteger('insumo_id');
                $table->integer('cantidad')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['hospital_id', 'insumo_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_insumos');
    }
};
