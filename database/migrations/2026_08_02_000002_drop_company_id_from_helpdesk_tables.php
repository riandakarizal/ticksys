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

            // FK & index dibaca dari skema aktual, bukan nama tebakan: di instance lama FK-nya
            // bisa sudah hilang (run sebelumnya gagal di tengah — DDL MySQL tidak transaksional).
            $foreignKeys = collect(Schema::getForeignKeys($table))
                ->filter(fn (array $fk) => in_array('company_id', $fk['columns'], true));
            $indexes = collect(Schema::getIndexes($table))
                ->filter(fn (array $index) => ! $index['primary'] && in_array('company_id', $index['columns'], true));

            Schema::table($table, function (Blueprint $blueprint) use ($foreignKeys): void {
                foreach ($foreignKeys as $fk) {
                    $blueprint->dropForeign($fk['name']);
                }
            });

            // MariaDB menolak DROP COLUMN untuk kolom yang jadi bagian index komposit (error 1072),
            // sedangkan MySQL 8 diam-diam menyusutkan index-nya. Samakan hasilnya: drop index-nya,
            // lalu buat ulang dari kolom yang tersisa.
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $index) {
                    $blueprint->dropIndex($index['name']);
                }

                $blueprint->dropColumn('company_id');

                foreach ($indexes as $index) {
                    $remaining = array_values(array_diff($index['columns'], ['company_id']));

                    if ($remaining !== []) {
                        $index['unique'] ? $blueprint->unique($remaining) : $blueprint->index($remaining);
                    }
                }
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
