<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `pjct_cotype` jadi VARCHAR biasa: jenis dokumen kontrak bisa bertambah, dan ENUM
     * butuh ALTER TABLE tiap kali. Nilai yang berlaku dijaga aplikasi lewat `PjctMain::COTYPES`.
     *
     * `pjct_main` is a legacy table with no migration — it only exists on MySQL/MariaDB
     * (the SQLite test database has no such table), so skip on other drivers.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('pjct_main', 'pjct_cotype')) {
            return;
        }

        DB::statement('ALTER TABLE pjct_main MODIFY pjct_cotype VARCHAR(50) NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('pjct_main', 'pjct_cotype')) {
            return;
        }

        // Nilai di luar daftar lama dikosongkan dulu, kalau tidak MODIFY ke ENUM gagal.
        DB::table('pjct_main')->whereNotIn('pjct_cotype', ['Contract', 'Contract Addendum'])->update(['pjct_cotype' => null]);
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_cotype ENUM('Contract','Contract Addendum') NULL");
    }
};
