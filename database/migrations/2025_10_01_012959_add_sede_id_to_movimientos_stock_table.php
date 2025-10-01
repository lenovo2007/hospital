>><?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's check what we're working with
        $columnExists = Schema::hasColumn('movimientos_stock', 'sede_id');
        $tableInfo = DB::select("DESCRIBE movimientos_stock");
        $sedeColumn = collect($tableInfo)->firstWhere('Field', 'sede_id');

        if (!$columnExists) {
            // Column doesn't exist, create it
            Schema::table('movimientos_stock', function (Blueprint $table) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('hospital_id');
            });
        } else {
            // Column exists, check if it needs to be modified
            if ($sedeColumn && ($sedeColumn->Type !== 'bigint unsigned' || $sedeColumn->Null !== 'YES')) {
                // Need to modify the column
                DB::statement('ALTER TABLE movimientos_stock MODIFY sede_id BIGINT UNSIGNED NULL');
            }
        }

        // Now add the foreign key constraint if it doesn't exist
        $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'movimientos_stock'
            AND COLUMN_NAME = 'sede_id'
            AND REFERENCED_TABLE_NAME = 'sedes'");

        if (empty($constraints)) {
            Schema::table('movimientos_stock', function (Blueprint $table) {
                $table->foreign('sede_id')->references('id')->on('sedes')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('movimientos_stock', 'sede_id')) {
            // Drop foreign key constraint first
            $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'movimientos_stock'
                AND COLUMN_NAME = 'sede_id'
                AND REFERENCED_TABLE_NAME = 'sedes'");

            if (!empty($constraints)) {
                $constraintName = $constraints[0]->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE movimientos_stock DROP FOREIGN KEY {$constraintName}");
            }

            // Then drop the column
            Schema::table('movimientos_stock', function (Blueprint $table) {
                $table->dropColumn('sede_id');
            });
        }
    }
};
