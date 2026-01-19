<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lotes_grupos')) {
            return;
        }

        $uniqueNames = [
            'lote_grupo_codigo_unique',
            'lotes_grupos_codigo_unique',
        ];

        foreach ($uniqueNames as $name) {
            $exists = DB::selectOne(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'lotes_grupos'
                   AND INDEX_NAME = ?",
                [$name]
            );

            if ($exists) {
                Schema::table('lotes_grupos', function (Blueprint $table) use ($name) {
                    $table->dropIndex($name);
                });
            }
        }

        $indexExists = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'lotes_grupos'
               AND INDEX_NAME = 'lotes_grupos_codigo_idx'"
        );

        if (!$indexExists) {
            Schema::table('lotes_grupos', function (Blueprint $table) {
                $table->index('codigo', 'lotes_grupos_codigo_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('lotes_grupos')) {
            return;
        }

        $indexExists = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'lotes_grupos'
               AND INDEX_NAME = 'lotes_grupos_codigo_idx'"
        );

        if ($indexExists) {
            Schema::table('lotes_grupos', function (Blueprint $table) {
                $table->dropIndex('lotes_grupos_codigo_idx');
            });
        }

        Schema::table('lotes_grupos', function (Blueprint $table) {
            $table->unique('codigo', 'lotes_grupos_codigo_unique');
        });
    }
};
