<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accrual project: kapan di-accrue (`pjct_accdate`) dan oleh unit/divisi mana (`pjct_accby`).
     *
     * `pjct_main` is a legacy table with no migration — it only exists on MySQL/MariaDB
     * (the SQLite test database has no such table), so skip on other drivers.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('pjct_main')) {
            return;
        }

        Schema::table('pjct_main', function (Blueprint $table): void {
            if (! Schema::hasColumn('pjct_main', 'pjct_accdate')) {
                $table->date('pjct_accdate')->nullable()->after('pjct_coend_m');
            }

            if (! Schema::hasColumn('pjct_main', 'pjct_accby')) {
                $table->string('pjct_accby', 225)->nullable()->after('pjct_accdate');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('pjct_main')) {
            return;
        }

        Schema::table('pjct_main', function (Blueprint $table): void {
            $table->dropColumn(['pjct_accdate', 'pjct_accby']);
        });
    }
};
