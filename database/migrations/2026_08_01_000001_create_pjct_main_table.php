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
        // pjct_main sudah ada di database prism asli (dibuat manual, di luar
        // migration history) — no-op di sana, tapi tetap dibuat fresh di CI/dev/test.
        if (Schema::hasTable('pjct_main')) {
            return;
        }

        Schema::create('pjct_main', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // e.g. PJ0001
            $table->string('pjct_contract', 255)->nullable();
            $table->date('pjct_codate')->nullable();
            $table->string('pjct_div', 225)->nullable();
            $table->string('pjct_name', 255);
            $table->string('pjct_type', 255)->nullable();
            $table->string('pjct_client', 255)->nullable();
            $table->string('pjct_area', 255)->nullable();
            $table->bigInteger('pjct_value')->nullable();
            $table->integer('pjct_budgetid')->nullable();
            $table->date('pjct_costart')->nullable();
            $table->integer('pjct_totalperiod')->nullable();
            $table->date('pjct_coend_m')->nullable();
            $table->string('pjct_status', 255);
            $table->text('pjct_misc')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjct_main');
    }
};
