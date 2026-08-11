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
        // Belum ada model/controller yang memakai tabel ini — kosong di prism. Kemungkinan
        // cikal-bakal fitur "Report > Issues" (FE-04, docs/PRISM-SYSTEM-DOCUMENT.md) yang
        // masih stub. Dibuat di sini agar skema tetap reproducible dari migration.
        // Sudah ada di database prism asli — no-op di sana.
        if (Schema::hasTable('pjct_issue')) {
            return;
        }

        Schema::create('pjct_issue', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('iss_pjctid', 20)->index();
            $table->text('iss_main')->nullable();
            $table->text('iss_plan')->nullable();
            $table->string('iss_status', 20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjct_issue');
    }
};
