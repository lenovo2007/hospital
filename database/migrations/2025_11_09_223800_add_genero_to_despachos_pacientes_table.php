<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos_pacientes', function (Blueprint $table) {
            if (!Schema::hasColumn('despachos_pacientes', 'genero')) {
                $table->string('genero', 1)
                    ->nullable()
                    ->after('paciente_cedula')
                    ->comment('M: Masculino, F: Femenino, O: Otro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('despachos_pacientes', function (Blueprint $table) {
            if (Schema::hasColumn('despachos_pacientes', 'genero')) {
                $table->dropColumn('genero');
            }
        });
    }
};
