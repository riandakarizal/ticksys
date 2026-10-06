<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tickets.ast_id dibuat dengan collation default Laravel (utf8mb4_unicode_ci),
     * tapi ast_main.id (tabel legacy prism) pakai utf8mb4_general_ci — MySQL menolak
     * membandingkan dua kolom dengan collation berbeda ("Illegal mix of collations").
     * Samakan collation ast_id dengan ast_main.id supaya join/whereHas('asset', ...) jalan.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('tickets', 'ast_id')) {
            DB::statement('ALTER TABLE tickets MODIFY ast_id VARCHAR(20) COLLATE utf8mb4_general_ci NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('tickets', 'ast_id')) {
            DB::statement('ALTER TABLE tickets MODIFY ast_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL');
        }
    }
};
