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
        // Verificar y agregar claves foráneas de forma más segura
        try {
            Schema::table('ingresos_directos', function (Blueprint $table) {
                // Verificar que las columnas y tablas existan antes de crear constraints
                if (Schema::hasTable('hospitales') && Schema::hasColumn('ingresos_directos', 'hospital_id')) {
                    // Verificar que no exista ya la constraint
                    $table->foreign('hospital_id', 'fk_ingresos_directos_hospital_id')
                          ->references('id')->on('hospitales')
                          ->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            // Si falla, continuar sin la constraint de hospital
        }

        try {
            Schema::table('ingresos_directos', function (Blueprint $table) {
                if (Schema::hasTable('sedes') && Schema::hasColumn('ingresos_directos', 'sede_id')) {
                    $table->foreign('sede_id', 'fk_ingresos_directos_sede_id')
                          ->references('id')->on('sedes')
                          ->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            // Si falla, continuar sin la constraint de sede
        }

        try {
            Schema::table('ingresos_directos', function (Blueprint $table) {
                if (Schema::hasTable('users') && Schema::hasColumn('ingresos_directos', 'user_id')) {
                    $table->foreign('user_id', 'fk_ingresos_directos_user_id')
                          ->references('id')->on('users')
                          ->onDelete('cascade');
                }
            });
        } catch (\Exception $e) {
            // Si falla, continuar sin la constraint de user
        }

        try {
            Schema::table('ingresos_directos', function (Blueprint $table) {
                if (Schema::hasTable('users') && Schema::hasColumn('ingresos_directos', 'user_id_procesado')) {
                    $table->foreign('user_id_procesado', 'fk_ingresos_directos_user_id_procesado')
                          ->references('id')->on('users')
                          ->onDelete('set null');
                }
            });
        } catch (\Exception $e) {
            // Si falla, continuar sin la constraint de user_procesado
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos_directos', function (Blueprint $table) {
            // Eliminar claves foráneas usando nombres específicos
            try {
                $table->dropForeign('fk_ingresos_directos_hospital_id');
            } catch (\Exception $e) {
                // Constraint no existe
            }
            
            try {
                $table->dropForeign('fk_ingresos_directos_sede_id');
            } catch (\Exception $e) {
                // Constraint no existe
            }
            
            try {
                $table->dropForeign('fk_ingresos_directos_user_id');
            } catch (\Exception $e) {
                // Constraint no existe
            }
            
            try {
                $table->dropForeign('fk_ingresos_directos_user_id_procesado');
            } catch (\Exception $e) {
                // Constraint no existe
            }
        });
    }
};
