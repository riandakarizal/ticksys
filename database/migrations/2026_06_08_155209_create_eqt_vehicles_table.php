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
        Schema::create('eqt_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('nopol');
            $table->string('jenis')->nullable();
            $table->string('customer')->nullable();
            $table->string('vendor')->nullable();
            $table->string('no_kontrak')->nullable();
            $table->string('pkb_date')->nullable();
            $table->bigInteger('nilai_pkb')->default(0);
            $table->string('status_pajak')->default('N/A');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eqt_vehicles');
    }
};
