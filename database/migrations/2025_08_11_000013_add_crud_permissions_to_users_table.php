<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'can_view')) {
                $table->boolean('can_view')->default(true)->after('direccion');
            }
            if (!Schema::hasColumn('users', 'can_create')) {
                $table->boolean('can_create')->default(true)->after('can_view');
            }
            if (!Schema::hasColumn('users', 'can_update')) {
                $table->boolean('can_update')->default(true)->after('can_create');
            }
            if (!Schema::hasColumn('users', 'can_delete')) {
                $table->boolean('can_delete')->default(true)->after('can_update');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'can_delete')) {
                $table->dropColumn('can_delete');
            }
            if (Schema::hasColumn('users', 'can_update')) {
                $table->dropColumn('can_update');
            }
            if (Schema::hasColumn('users', 'can_create')) {
                $table->dropColumn('can_create');
            }
            if (Schema::hasColumn('users', 'can_view')) {
                $table->dropColumn('can_view');
            }
        });
    }
};
