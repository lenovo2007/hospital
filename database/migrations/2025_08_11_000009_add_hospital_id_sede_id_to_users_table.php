<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('direccion');
                $table->foreign('hospital_id')->references('id')->on('hospitales')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('hospital_id');
                $table->foreign('sede_id')->references('id')->on('sedes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sede_id')) {
                $table->dropForeign(['sede_id']);
                $table->dropColumn('sede_id');
            }
            if (Schema::hasColumn('users', 'hospital_id')) {
                $table->dropForeign(['hospital_id']);
                $table->dropColumn('hospital_id');
            }
        });
    }
};
