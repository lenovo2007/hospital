<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_root')->default(false)->after('status');
        });
        // Marcar el usuario con ID=1 como root si existe
        try {
            DB::table('users')->where('id', 1)->update(['is_root' => true]);
        } catch (\Throwable $e) {
            // ignorar errores si la tabla no existe aún en algunos entornos
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_root')) {
                $table->dropColumn('is_root');
            }
        });
    }
};
