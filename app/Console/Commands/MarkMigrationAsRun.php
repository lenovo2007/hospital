<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarkMigrationAsRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migration:mark-as-run {migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark a migration as run without executing it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $migration = $this->argument('migration');
        
        // Verificar si la migración ya está registrada
        $exists = DB::table('migrations')
            ->where('migration', $migration)
            ->exists();
            
        if ($exists) {
            $this->info("Migration '{$migration}' is already marked as run.");
            return;
        }
        
        // Obtener el último batch number
        $lastBatch = DB::table('migrations')->max('batch') ?? 0;
        $newBatch = $lastBatch + 1;
        
        // Insertar el registro de migración
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => $newBatch
        ]);
        
        $this->info("Migration '{$migration}' has been marked as run (batch {$newBatch}).");
    }
}
