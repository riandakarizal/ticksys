<?php

use App\Models\PjctMain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jenis dokumen kontrak project: kontrak induk atau addendum-nya. Terpisah dari
     * `pjct_type`, yang berisi jenis pekerjaan (RENT/SUPPLY/JASA).
     *
     * `pjct_main` is a legacy table with no migration — it only exists on MySQL/MariaDB
     * (the SQLite test database has no such table), so skip on other drivers.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('pjct_main') || Schema::hasColumn('pjct_main', 'pjct_cotype')) {
            return;
        }

        Schema::table('pjct_main', function (Blueprint $table): void {
            $table->enum('pjct_cotype', PjctMain::COTYPES)->nullable()->after('pjct_type');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('pjct_main', 'pjct_cotype')) {
            return;
        }

        Schema::table('pjct_main', function (Blueprint $table): void {
            $table->dropColumn('pjct_cotype');
        });
    }
};
