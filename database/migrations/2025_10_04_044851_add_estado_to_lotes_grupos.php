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
        // No agregar el campo estado - se decidió no usarlo
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
