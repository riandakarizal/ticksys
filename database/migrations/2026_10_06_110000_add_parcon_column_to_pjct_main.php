<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Untuk project berjenis `Contract Addendum`: ID project kontrak induknya (`pjct_main.id`).
     *
     * Sengaja tanpa foreign key — FK yang menunjuk ke tabelnya sendiri membuat
     * `TRUNCATE pjct_main` ditolak MySQL, padahal itu dipakai saat menata ulang data.
     * Cukup index supaya pencarian addendum per kontrak tetap cepat.
     *
     * `pjct_main` is a legacy table with no migration — it only exists on MySQL/MariaDB
     * (the SQLite test database has no such table), so skip on other drivers.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('pjct_main') || Schema::hasColumn('pjct_main', 'pjct_parcon')) {
            return;
        }

        Schema::table('pjct_main', function (Blueprint $table): void {
            $table->string('pjct_parcon', 10)->nullable()->after('pjct_cotype');
            $table->index('pjct_parcon');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('pjct_main', 'pjct_parcon')) {
            return;
        }

        Schema::table('pjct_main', function (Blueprint $table): void {
            $table->dropIndex(['pjct_parcon']);
            $table->dropColumn('pjct_parcon');
        });
    }
};
