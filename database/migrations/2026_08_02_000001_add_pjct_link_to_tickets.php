<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * create_tickets_table sudah mendeklarasikan kolom ini untuk instalasi baru (fresh DB).
     * Untuk database yang tabel tickets-nya sudah ada sebelum kolom ini ditulis (mis. prism
     * asli), ditambahkan di sini secara terpisah — lihat 2026_08_01_000009 untuk pola yang sama.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'pjct_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('pjct_id', 10)->nullable()->index()->after('ast_id');
                $table->string('requester_name', 255)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'pjct_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn(['pjct_id', 'requester_name']);
            });
        }
    }
};
