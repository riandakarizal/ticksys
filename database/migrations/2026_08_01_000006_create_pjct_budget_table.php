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
        Schema::create('pjct_budget', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('bdg_pjctid', 225)->index();
            $table->string('bdg_name', 225);
            $table->string('bdg_type', 20); // PENGADAAN, PEKERJAAN, JASA
            $table->string('bdg_type2', 20)->nullable();
            $table->integer('bdg_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjct_budget');
    }
};
