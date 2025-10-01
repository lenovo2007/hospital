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
        // Check if there are any invalid sede_id values
        $invalidRecords = DB::select("SELECT sede_id FROM movimientos_stock
            WHERE sede_id IS NOT NULL
            AND sede_id NOT IN (SELECT id FROM sedes)");

        if (!empty($invalidRecords)) {
            echo "Found " . count($invalidRecords) . " records with invalid sede_id values.\n";
            echo "These need to be fixed before adding the foreign key constraint.\n";

            // Option 1: Set invalid sede_id values to NULL (recommended for data integrity)
            DB::statement("UPDATE movimientos_stock
                SET sede_id = NULL
                WHERE sede_id IS NOT NULL
                AND sede_id NOT IN (SELECT id FROM sedes)");

            echo "Invalid sede_id values have been set to NULL.\n";
        }

        // Now add the foreign key constraint
        if (Schema::hasColumn('movimientos_stock', 'sede_id')) {
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
            // Drop foreign key constraint
            $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'movimientos_stock'
                AND COLUMN_NAME = 'sede_id'
                AND REFERENCED_TABLE_NAME = 'sedes'");

            if (!empty($constraints)) {
                $constraintName = $constraints[0]->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE movimientos_stock DROP FOREIGN KEY {$constraintName}");
            }
        }
    }
};
