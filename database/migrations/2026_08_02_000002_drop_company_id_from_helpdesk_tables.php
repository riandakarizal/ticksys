<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRISM single-tenant — company_id/companies tidak dipakai kode manapun lagi
     * (lihat migration rewrite di 2026_03_25_* dan 2026_08_01_*). Kolom ini masih
     * ada di database yang sudah berjalan sebelum migration itu ditulis ulang
     * (mis. prism asli), dan NOT NULL tanpa default — memblokir insert baru
     * (mis. Ticket::create() tanpa company_id). Drop di sini untuk instance lama.
     */
    private const TABLES = ['tickets', 'teams', 'categories', 'custom_fields', 'sla_policies', 'activity_logs'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($table . '_tenant_id_foreign');
                $blueprint->dropColumn('company_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            });
        }
    }
};
