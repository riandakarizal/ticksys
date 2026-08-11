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
        // Spesifikasi teknis per brand+model, digabung ke ast_main lewat join
        // (ast_main.ast_brand, ast_main.ast_brandmodel) — lihat FE-01 di
        // docs/PRISM-SYSTEM-DOCUMENT.md. Belum ada model/controller yang memakainya.
        Schema::create('ast_spec', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('spc_brand', 225);
            $table->string('spc_model', 225);
            $table->string('spc_name', 225);
            $table->string('spc_value', 225);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ast_spec');
    }
};
