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
        // ast_main sudah ada di database prism asli — no-op di sana, tapi tetap
        // dibuat fresh di CI/dev/test. Kolom baru (ast_last_ticket_id) untuk
        // instance yang sudah punya ast_main ditambahkan di migration terpisah.
        if (Schema::hasTable('ast_main')) {
            return;
        }

        Schema::create('ast_main', function (Blueprint $table) {
            $table->string('id', 20)->primary(); // e.g. AST-000001
            $table->string('ast_type', 225);
            $table->string('ast_brand', 225);
            $table->string('ast_brandmodel', 225);
            $table->year('ast_prodyear')->nullable();
            $table->string('ast_serial', 225);
            $table->string('ast_vendid', 20)->nullable();
            $table->string('ast_username', 225)->nullable();
            $table->string('ast_userreg', 225)->nullable();
            $table->string('ast_userloc', 225)->nullable();
            $table->string('ast_userlocdet', 225)->nullable();
            $table->string('ast_cond', 225); // Excellence, Good, Fair, Bad
            $table->date('ast_delvdate')->nullable();
            $table->date('ast_purcdate')->nullable();
            $table->string('ast_stat', 225); // Aktif, Aktif-Sewa, Aktif-SewaBeli, Back Up, Pinjam, Non-Aktif
            $table->string('ast_pjctid', 10)->nullable()->index();
            $table->string('ast_docid', 20)->nullable();
            $table->text('ast_misc')->nullable();

            // Ticket ticketing yang sedang melaporkan kendala pada aset ini (lihat
            // TicketManager::syncLinkedAssetCondition) — dipakai untuk mengembalikan
            // ast_cond otomatis begitu tidak ada lagi tiket terbuka untuk aset ini.
            // Kolom biasa tanpa FK constraint, konsisten dengan ast_pjctid/ast_docid di atas.
            $table->unsignedBigInteger('ast_last_ticket_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ast_main');
    }
};
