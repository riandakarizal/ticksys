<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `pjct_main` is a legacy table with no migration — it only exists on MySQL/MariaDB,
     * and `MODIFY` is MySQL-only syntax, so skip this entirely on other drivers
     * (the SQLite test database has no such table).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('pjct_main')) {
            return;
        }

        DB::statement('ALTER TABLE pjct_main MODIFY pjct_contract VARCHAR(255) NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_codate DATE NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_type VARCHAR(255) NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_client VARCHAR(255) NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_area VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('pjct_main')) {
            return;
        }

        DB::statement("ALTER TABLE pjct_main MODIFY pjct_contract VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_codate DATE NOT NULL DEFAULT '1970-01-01'");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_type VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_client VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_area VARCHAR(255) NOT NULL DEFAULT ''");
    }
};
