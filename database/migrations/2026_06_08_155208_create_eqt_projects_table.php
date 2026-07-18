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
        Schema::create('eqt_projects', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['EQ', 'TECH']);
            $table->unsignedSmallInteger('tahun');
            $table->string('nama');
            $table->string('lob')->nullable();
            $table->string('pemberi_kerja')->nullable();
            $table->string('area')->nullable();
            $table->string('mitra')->nullable();
            $table->bigInteger('nilai_pekerjaan')->default(0);
            $table->bigInteger('nilai_mitra')->default(0);
            $table->bigInteger('serapan')->default(0);
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->string('no_kontrak')->nullable();
            $table->json('docs')->nullable();
            $table->string('status')->default('PENDING');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eqt_projects');
    }
};
