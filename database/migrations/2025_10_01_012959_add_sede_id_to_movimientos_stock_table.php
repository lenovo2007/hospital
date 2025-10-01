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
        // Let's debug what's happening
        echo "=== DIAGNOSTIC INFORMATION ===\n";

        // Check if sedes table exists and its structure
        if (Schema::hasTable('sedes')) {
            echo "sedes table exists\n";
            $sedesColumns = DB::select("DESCRIBE sedes");
            foreach ($sedesColumns as $column) {
                if ($column->Field === 'id') {
                    echo "sedes.id: {$column->Type} {$column->Null} {$column->Key}\n";
                }
            }

            $sedesInfo = DB::selectOne("SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sedes'");
            if ($sedesInfo) {
                echo "sedes engine: {$sedesInfo->ENGINE}, collation: {$sedesInfo->TABLE_COLLATION}\n";
            }
        } else {
            echo "sedes table does not exist\n";
        }

        // Check movimientos_stock structure
        if (Schema::hasTable('movimientos_stock')) {
            echo "movimientos_stock table exists\n";
            $msColumns = DB::select("DESCRIBE movimientos_stock");
            foreach ($msColumns as $column) {
                if ($column->Field === 'sede_id') {
                    echo "movimientos_stock.sede_id: {$column->Type} {$column->Null} {$column->Key}\n";
                }
            }

            $movimientosInfo = DB::selectOne("SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_stock'");
            if ($movimientosInfo) {
                echo "movimientos_stock engine: {$movimientosInfo->ENGINE}, collation: {$movimientosInfo->TABLE_COLLATION}\n";
            }
        } else {
            echo "movimientos_stock table does not exist\n";
        }

        // Check for any existing constraints
        $existingConstraints = DB::select("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'movimientos_stock'
            AND COLUMN_NAME = 'sede_id'");

        if (!empty($existingConstraints)) {
            echo "Existing constraints found:\n";
            foreach ($existingConstraints as $constraint) {
                echo "  - {$constraint->CONSTRAINT_NAME} -> {$constraint->REFERENCED_TABLE_NAME}\n";
            }
        } else {
            echo "No existing constraints found on movimientos_stock.sede_id\n";
        }

        // Check if there are any records with sede_id values
        $recordsWithSedeId = DB::select("SELECT COUNT(*) as count FROM movimientos_stock WHERE sede_id IS NOT NULL");
        echo "Records with sede_id values: {$recordsWithSedeId[0]->count}\n";

        // Check if sedes table has any records
        $sedesCount = DB::select("SELECT COUNT(*) as count FROM sedes");
        echo "Records in sedes table: {$sedesCount[0]->count}\n";

        echo "=== END DIAGNOSTIC ===\n";

        // Only proceed if we have the right structure
        if (Schema::hasTable('sedes') && Schema::hasTable('movimientos_stock') && Schema::hasColumn('movimientos_stock', 'sede_id')) {
            // Check if constraint already exists
            $constraintExists = !empty(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'movimientos_stock'
                AND COLUMN_NAME = 'sede_id'
                AND REFERENCED_TABLE_NAME = 'sedes'"));

            // Ensure both tables are using InnoDB engine
            $sedesEngine = isset($sedesInfo) ? strtoupper((string) $sedesInfo->ENGINE) : null;
            $movimientosEngine = isset($movimientosInfo) ? strtoupper((string) $movimientosInfo->ENGINE) : null;

            if ($sedesEngine !== 'INNODB') {
                echo "Converting sedes engine from {$sedesEngine} to InnoDB...\n";
                DB::statement("ALTER TABLE sedes ENGINE=InnoDB");
            }

            if ($movimientosEngine !== 'INNODB') {
                echo "Converting movimientos_stock engine from {$movimientosEngine} to InnoDB...\n";
                DB::statement("ALTER TABLE movimientos_stock ENGINE=InnoDB");
            }

            // Ensure the column is exactly UNSIGNED BIGINT
            echo "Ensuring movimientos_stock.sede_id is BIGINT UNSIGNED NULL...\n";
            DB::statement('ALTER TABLE movimientos_stock MODIFY sede_id BIGINT UNSIGNED NULL');

            // Make sure there is no leftover index/constraint with unexpected name
            $orphanIndex = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_stock' AND COLUMN_NAME = 'sede_id' AND INDEX_NAME = 'movimientos_stock_sede_id_foreign'");
            if ($orphanIndex) {
                echo "Dropping orphan index movimientos_stock_sede_id_foreign...\n";
                DB::statement('ALTER TABLE movimientos_stock DROP INDEX movimientos_stock_sede_id_foreign');
            }

            if (!$constraintExists) {
                echo "Adding foreign key constraint...\n";
                Schema::table('movimientos_stock', function (Blueprint $table) {
                    $table->foreign('sede_id')->references('id')->on('sedes')->onDelete('set null');
                });
                echo "Foreign key constraint added successfully\n";
            } else {
                echo "Foreign key constraint already exists\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraint if it exists
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
};
