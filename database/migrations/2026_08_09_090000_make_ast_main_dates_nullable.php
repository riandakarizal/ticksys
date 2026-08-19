<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `ast_main` is a legacy table (not created by any migration) — it only exists on
     * MySQL/MariaDB. `ast_delvdate` / `ast_purcdate` are `date NOT NULL` and legacy rows
     * carry '0000-00-00', which Laravel's strict sql_mode (NO_ZERO_DATE) refuses to write.
     * Imported assets often have no delivery/purchase date, so make both columns nullable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ast_main') || DB::getDriverName() !== 'mysql') {
            return;
        }

        // Existing '0000-00-00' values block the ALTER under strict mode.
        DB::statement("SET SESSION sql_mode = ''");
        DB::statement('ALTER TABLE `ast_main` MODIFY `ast_delvdate` DATE NULL');
        DB::statement('ALTER TABLE `ast_main` MODIFY `ast_purcdate` DATE NULL');
        DB::statement("UPDATE `ast_main` SET `ast_delvdate` = NULL WHERE `ast_delvdate` = '0000-00-00'");
        DB::statement("UPDATE `ast_main` SET `ast_purcdate` = NULL WHERE `ast_purcdate` = '0000-00-00'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('ast_main') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("SET SESSION sql_mode = ''");
        DB::statement("UPDATE `ast_main` SET `ast_delvdate` = '0000-00-00' WHERE `ast_delvdate` IS NULL");
        DB::statement("UPDATE `ast_main` SET `ast_purcdate` = '0000-00-00' WHERE `ast_purcdate` IS NULL");
        DB::statement('ALTER TABLE `ast_main` MODIFY `ast_delvdate` DATE NOT NULL');
        DB::statement('ALTER TABLE `ast_main` MODIFY `ast_purcdate` DATE NOT NULL');
    }
};
