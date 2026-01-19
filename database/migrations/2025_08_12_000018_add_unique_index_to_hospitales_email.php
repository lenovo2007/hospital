<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('hospitales') && Schema::hasColumn('hospitales', 'email')) {
            Schema::table('hospitales', function (Blueprint $table) {
                // Índice único para email
                $table->unique('email', 'hospitales_email_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hospitales')) {
            Schema::table('hospitales', function (Blueprint $table) {
                // Quitar índice único si existe
                $table->dropUnique('hospitales_email_unique');
            });
        }
    }
};
