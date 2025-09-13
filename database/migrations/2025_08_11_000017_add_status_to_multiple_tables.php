<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = [
        'hospitales',
        'sedes',
        'farmacias',
        'mini_almacenes',
        'almacenes_principales',
        'almacenes_centrales',
        'users',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'status')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->enum('status', ['activo','inactivo'])->default('activo')->after('updated_at');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'status')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('status');
                });
            }
        }
    }
};
