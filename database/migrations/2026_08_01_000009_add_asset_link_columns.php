<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * create_tickets_table dan create_ast_main_table sudah mendeklarasikan kolom-kolom
     * ini langsung untuk instalasi baru (fresh DB). Tapi di database yang tabel
     * tickets/ast_main-nya sudah ada sebelum migration ini ditulis (mis. prism asli),
     * migration create-table tersebut tidak akan dijalankan ulang oleh Laravel — jadi
     * kolomnya perlu ditambahkan di sini secara terpisah, dengan guard supaya no-op
     * kalau kolomnya sudah ada (fresh install).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'ast_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('ast_id', 20)->nullable()->index()->after('team_id');
                $table->string('asset_cond_before', 225)->nullable();
            });
        }

        // Kolom biasa (bukan FK constraint keras) — konsisten dengan tabel PRISM legacy
        // lain (ast_pjctid, doc_pjctid, dst) yang juga tidak pakai constraint, dan
        // menghindari ALTER TABLE ADD CONSTRAINT yang memvalidasi ulang seluruh baris
        // tabel (termasuk kolom lama yang datanya kotor, mis. ast_delvdate '0000-00-00').
        if (! Schema::hasColumn('ast_main', 'ast_last_ticket_id')) {
            Schema::table('ast_main', function (Blueprint $table) {
                $table->unsignedBigInteger('ast_last_ticket_id')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ast_main', 'ast_last_ticket_id')) {
            Schema::table('ast_main', function (Blueprint $table) {
                $table->dropColumn('ast_last_ticket_id');
            });
        }

        if (Schema::hasColumn('tickets', 'ast_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn(['ast_id', 'asset_cond_before']);
            });
        }
    }
};
