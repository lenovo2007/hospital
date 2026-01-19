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
        // Add codigo_alterno if it doesn't exist
        if (!Schema::hasColumn('insumos', 'codigo_alterno')) {
            Schema::table('insumos', function (Blueprint $table) {
                $table->string('codigo_alterno')->nullable()->after('codigo');
            });
        }
        
        // Make codigo nullable if it's not already
        Schema::table('insumos', function (Blueprint $table) {
            $table->string('codigo')->nullable()->change();
        });
        
        // Move existing codigo values to codigo_alterno
        if (Schema::hasColumn('insumos', 'codigo_alterno')) {
            DB::table('insumos')
                ->whereNotNull('codigo')
                ->update([
                    'codigo_alterno' => DB::raw('codigo'),
                    'codigo' => null
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore codigo values before dropping the column
        DB::table('insumos')
            ->whereNotNull('codigo_alterno')
            ->update([
                'codigo' => DB::raw('codigo_alterno')
            ]);
            
        // Drop codigo_alterno if it exists
        if (Schema::hasColumn('insumos', 'codigo_alterno')) {
            Schema::table('insumos', function (Blueprint $table) {
                $table->dropColumn('codigo_alterno');
            });
        }
        
        // Restore codigo to not nullable
        Schema::table('insumos', function (Blueprint $table) {
            $table->string('codigo')->nullable(false)->change();
        });
    }
};
