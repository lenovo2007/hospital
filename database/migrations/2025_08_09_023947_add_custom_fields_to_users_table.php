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
        Schema::table('users', function (Blueprint $table) {
            // Nuevos campos solicitados
            $table->string('tipo')->after('id');
            $table->string('rol')->after('tipo');
            $table->string('nombre')->after('rol');
            $table->string('apellido')->after('nombre');
            $table->string('cedula')->unique()->after('apellido');
            $table->string('telefono')->nullable()->after('cedula');
            $table->string('direccion')->nullable()->after('telefono');

            // Eliminar el campo original 'name'
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restaurar campo 'name'
            $table->string('name')->after('id');

            // Eliminar campos personalizados
            if (Schema::hasColumn('users', 'direccion')) {
                $table->dropColumn('direccion');
            }
            if (Schema::hasColumn('users', 'telefono')) {
                $table->dropColumn('telefono');
            }
            if (Schema::hasColumn('users', 'cedula')) {
                // Al tener índice único, se elimina junto con la columna
                $table->dropColumn('cedula');
            }
            if (Schema::hasColumn('users', 'apellido')) {
                $table->dropColumn('apellido');
            }
            if (Schema::hasColumn('users', 'nombre')) {
                $table->dropColumn('nombre');
            }
            if (Schema::hasColumn('users', 'rol')) {
                $table->dropColumn('rol');
            }
            if (Schema::hasColumn('users', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
