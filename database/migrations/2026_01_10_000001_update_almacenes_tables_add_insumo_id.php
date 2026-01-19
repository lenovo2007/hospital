<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('almacenes_centrales')) {
            Schema::table('almacenes_centrales', function (Blueprint $table) {
                if (!Schema::hasColumn('almacenes_centrales', 'insumo_id')) {
                    $table->unsignedBigInteger('insumo_id')->nullable()->after('id');
                    $table->index('insumo_id');
                }

                if (!Schema::hasColumn('almacenes_centrales', 'estado')) {
                    $table->string('estado', 50)->default('pendiente')->after('status');
                }
            });
        }

        if (Schema::hasTable('almacenes_aus')) {
            Schema::table('almacenes_aus', function (Blueprint $table) {
                if (!Schema::hasColumn('almacenes_aus', 'insumo_id')) {
                    $table->unsignedBigInteger('insumo_id')->nullable()->after('id');
                    $table->index('insumo_id');
                }

                if (!Schema::hasColumn('almacenes_aus', 'estado')) {
                    $table->string('estado', 50)->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('almacenes_centrales')) {
            Schema::table('almacenes_centrales', function (Blueprint $table) {
                if (Schema::hasColumn('almacenes_centrales', 'estado')) {
                    $table->dropColumn('estado');
                }

                if (Schema::hasColumn('almacenes_centrales', 'insumo_id')) {
                    $table->dropIndex(['insumo_id']);
                    $table->dropColumn('insumo_id');
                }
            });
        }

        if (Schema::hasTable('almacenes_aus')) {
            Schema::table('almacenes_aus', function (Blueprint $table) {
                if (Schema::hasColumn('almacenes_aus', 'estado')) {
                    $table->dropColumn('estado');
                }

                if (Schema::hasColumn('almacenes_aus', 'insumo_id')) {
                    $table->dropIndex(['insumo_id']);
                    $table->dropColumn('insumo_id');
                }
            });
        }
    }
};
