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
        // Sudah ada di database prism asli — no-op di sana.
        if (Schema::hasTable('pjct_emp')) {
            return;
        }

        Schema::create('pjct_emp', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('emp_id', 20)->nullable();
            $table->string('emp_name', 225);
            $table->string('emp_level', 20)->nullable();
            $table->string('emp_levname', 225)->nullable();
            $table->string('emp_unit', 225)->nullable();
            $table->string('emp_div', 225)->nullable();
            $table->string('emp_area', 225)->nullable();
            $table->string('emp_pjctid', 10)->nullable()->index();
            $table->string('emp_coid', 225)->nullable();
            $table->string('emp_contact', 225)->nullable();
            $table->text('emp_misc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjct_emp');
    }
};
