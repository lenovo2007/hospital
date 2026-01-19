<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->string('cod_sicm', 100)->nullable()->unique()->after('rif');
            $table->string('nombre_contacto')->nullable()->after('nombre');
            $table->string('nombre_completo')->nullable()->after('nombre_contacto');
            $table->string('email_contacto')->nullable()->after('email');
            // telefono ya existe; no se crea nuevamente
        });
    }

    public function down(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            if (Schema::hasColumn('hospitales', 'cod_sicm')) {
                $table->dropUnique(['cod_sicm']);
                $table->dropColumn('cod_sicm');
            }
            if (Schema::hasColumn('hospitales', 'nombre_contacto')) {
                $table->dropColumn('nombre_contacto');
            }
            if (Schema::hasColumn('hospitales', 'nombre_completo')) {
                $table->dropColumn('nombre_completo');
            }
            if (Schema::hasColumn('hospitales', 'email_contacto')) {
                $table->dropColumn('email_contacto');
            }
        });
    }
};
