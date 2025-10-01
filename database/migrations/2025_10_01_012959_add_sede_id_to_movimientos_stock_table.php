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
        // Check if sede_id column exists
        if (!Schema::hasColumn('movimientos_stock', 'sede_id')) {
            Schema::table('movimientos_stock', function (Blueprint $table) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('hospital_id');
            });
        }

        // Check if foreign key constraint already exists
        $foreignKeys = DB::select("SHOW CREATE TABLE movimientos_stock");
        $createTableSQL = $foreignKeys[0]->{'Create Table'} ?? '';

        if (Schema::hasColumn('movimientos_stock', 'sede_id') &&
            !str_contains($createTableSQL, 'movimientos_stock_sede_id_foreign')) {
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
            Schema::table('movimientos_stock', function (Blueprint $table) {
                $table->dropForeign(['sede_id']);
                $table->dropColumn('sede_id');
            });
        }
    }
};
