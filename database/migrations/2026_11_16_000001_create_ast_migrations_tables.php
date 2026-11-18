<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat migrasi aset antar-project (Project → Migrasi Aset): kontrak project habis / tidak
 * diperpanjang → asetnya dipindah ke project baru. `ast_main.ast_pjctid` hanya menyimpan posisi
 * terkini, jadi jejak "dulu di project mana" disimpan di sini.
 *
 * - ast_migrations      : satu baris per batch (asal → tujuan, siapa, opsi, catatan).
 * - ast_migration_items : aset yang ikut dipindah + snapshot kolom yang mungkin ditimpa
 *                         (status, pengguna, region, lokasi) sebelum dipindah.
 *
 * Kolom project/aset tanpa FK constraint, konsisten dengan ast_pjctid (tabel legacy beda collation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ast_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('from_pjct_id', 10)->index();
            $table->string('to_pjct_id', 10)->index();
            $table->string('user_id', 20)->nullable();
            $table->unsignedInteger('asset_count');
            $table->string('stat_after', 20)->nullable()->comment('null = status aset tidak diubah');
            $table->boolean('cleared_location')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('ast_migration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ast_migration_id')->constrained('ast_migrations')->cascadeOnDelete();
            $table->string('ast_id', 20)->index();
            $table->json('before')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ast_migration_items');
        Schema::dropIfExists('ast_migrations');
    }
};
