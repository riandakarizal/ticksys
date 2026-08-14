<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Belum ada model/controller yang memakai tabel ini — masih kosong di prism
        // ("Pengeluaran project (belum diisi)", lihat docs/PRISM-DATABASE.md). Dibuat
        // di sini agar skema tetap reproducible dari migration.
        // Sudah ada di database prism asli — no-op di sana.
        if (Schema::hasTable('pjct_expense')) {
            return;
        }

        Schema::create('pjct_expense', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('exp_bdgid', 225)->index();
            $table->string('exp_name', 225);
            $table->integer('exp_value');
            $table->string('exp_status', 225);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjct_expense');
    }
};
