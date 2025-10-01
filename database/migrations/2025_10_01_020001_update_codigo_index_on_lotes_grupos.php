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

        $uniqueExists = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes_grupos' AND INDEX_NAME = 'lotes_grupos_codigo_unique'");
        if ($uniqueExists) {
            Schema::table('lotes_grupos', function (Blueprint $table) {
                $table->dropUnique('lotes_grupos_codigo_unique');
            });
        }

        $indexExists = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes_grupos' AND INDEX_NAME = 'lotes_grupos_codigo_idx'");
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

        $indexExists = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes_grupos' AND INDEX_NAME = 'lotes_grupos_codigo_idx'");
        if ($indexExists) {
            Schema::table('lotes_grupos', function (Blueprint $table) {
                $table->dropIndex('lotes_grupos_codigo_idx');
            });
        }

        $uniqueExists = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes_grupos' AND INDEX_NAME = 'lotes_grupos_codigo_unique'");
        if (!$uniqueExists) {
            Schema::table('lotes_grupos', function (Blueprint $table) {
                $table->unique('codigo', 'lotes_grupos_codigo_unique');
            });
        }
    }
};
