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
            $table->boolean('discrepancia')->default(false)->after('cantidad_entrada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotes_grupos', function (Blueprint $table) {
            $table->dropColumn('discrepancia');
        });
    }
};
