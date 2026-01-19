<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            if (!Schema::hasColumn('insumos', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('id');
                $table->index('sede_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            if (Schema::hasColumn('insumos', 'sede_id')) {
                $table->dropIndex(['sede_id']);
                $table->dropColumn('sede_id');
            }
        });
    }
};
