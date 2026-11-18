<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat addendum kontrak per project (Project → Main → tombol Addendum). Project tetap satu baris di
 * `pjct_main`; tiap addendum dicatat di sini dan langsung diterapkan ke kolom project yang terdampak
 * (nilai, tanggal akhir, nama client), dengan nilai sebelum/sesudah di `changes`.
 *
 * - seq      : addendum ke-berapa untuk project ini (1, 2, …), unik per project.
 * - reasons  : kode alasan (PjctAddendum::REASONS), bisa lebih dari satu.
 * - notes    : keterangan per kode alasan.
 * - changes  : { kolom pjct_main: { before, after } } untuk alasan yang mengubah data project.
 * - doc_id   : dokumen addendum (PDF) di `pjct_doc` (doc_type ADD), file di `docfile/PJxxxx/`.
 *
 * Tanpa FK ke pjct_main/pjct_doc (tabel legacy, beda collation) — konsisten dengan kolom relasi lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjct_addenda', function (Blueprint $table) {
            $table->id();
            $table->string('pjct_id', 10)->index();
            $table->unsignedSmallInteger('seq');
            $table->string('number', 255);
            $table->date('addendum_date');
            $table->json('reasons');
            $table->json('notes')->nullable();
            $table->json('changes')->nullable();
            $table->string('doc_id', 20)->nullable();
            $table->string('user_id', 20)->nullable();
            $table->timestamps();

            $table->unique(['pjct_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjct_addenda');
    }
};
