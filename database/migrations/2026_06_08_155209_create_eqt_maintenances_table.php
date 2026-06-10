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
        Schema::create('eqt_maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('nama_alat');
            $table->string('area')->nullable();
            $table->string('mitra')->nullable();
            $table->string('no_kontrak')->nullable();
            $table->string('tipe_alat')->nullable();
            $table->string('last_service')->nullable();
            $table->string('next_service')->nullable();
            $table->string('status_maint')->default('BELUM');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eqt_maintenances');
    }
};
