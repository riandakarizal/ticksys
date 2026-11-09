<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flow tiket baru (teknisi lapangan): open → (escalated → open) → solved → closed.
 *
 * - tickets.issue_type    : kerusakan / accident / sistem — menentukan device wajib atau tidak.
 * - tickets.reporter_name : nama user client yang melapor ke teknisi (bukan akun PRISM).
 * - tickets.approved_by   : atasan (user_parid teknisi) yang meng-approve solved → closed.
 * - ticket_assets         : device yang terlibat (bisa >1), menggantikan tickets.ast_id tunggal.
 *                           cond_before = kondisi device sebelum dilaporkan, dipulihkan saat closed.
 * - ticket_attachments.doc_type : jenis dokumen (kronologi, BA eskalasi, BA pengembalian, ...).
 *
 * tickets.ast_id / asset_cond_before tidak di-drop — datanya disalin ke ticket_assets, kolomnya
 * dibiarkan supaya rollback tidak kehilangan data.
 */
return new class extends Migration
{
    private const STATUS_MAP = [
        'in_progress' => 'open',
        'pending' => 'open',
        'resolved' => 'solved',
    ];

    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('issue_type', 20)->default('kerusakan')->after('description');
            $table->string('reporter_name', 255)->nullable()->after('requester_name');
            $table->string('approved_by', 20)->nullable()->after('closed_at');
        });

        Schema::table('ticket_attachments', function (Blueprint $table): void {
            $table->string('doc_type', 30)->nullable()->after('ticket_message_id');
        });

        Schema::create('ticket_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Tanpa FK constraint ke ast_main (tabel legacy, PK string) — sama seperti tickets.ast_id.
            $column = $table->string('ast_id', 20)->index();
            if ($collation = $this->columnCollation('ast_main', 'id')) {
                $column->collation($collation);
            }
            $table->string('cond_before', 225)->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'ast_id']);
        });

        // tickets.pjct_id dibuat dengan collation default Laravel; pjct_main.id di instance lama bisa
        // berbeda, dan whereHas('project') (filter divisi) gagal kalau collation-nya tidak sama.
        if ($this->isMysql() && ($collation = $this->columnCollation('pjct_main', 'id'))) {
            DB::statement("ALTER TABLE tickets MODIFY pjct_id VARCHAR(10) COLLATE {$collation} NULL");
        }

        DB::table('tickets')->whereNotNull('ast_id')->orderBy('id')
            ->each(function (object $ticket): void {
                DB::table('ticket_assets')->insert([
                    'ticket_id' => $ticket->id,
                    'ast_id' => $ticket->ast_id,
                    'cond_before' => $ticket->asset_cond_before,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // Ticket lama tanpa aset = laporan non-device (VPN, aplikasi, dst.) → masalah sistem, supaya
        // tidak langsung gagal validasi "device wajib" saat detailnya diubah.
        DB::table('tickets')->whereNull('ast_id')->update(['issue_type' => 'sistem']);

        foreach (self::STATUS_MAP as $from => $to) {
            DB::table('tickets')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'solved')->update(['status' => 'resolved']);
        DB::table('tickets')->where('status', 'escalated')->update(['status' => 'open']);

        Schema::dropIfExists('ticket_assets');

        Schema::table('ticket_attachments', function (Blueprint $table): void {
            $table->dropColumn('doc_type');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['issue_type', 'reporter_name', 'approved_by']);
        });
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function columnCollation(string $table, string $column): ?string
    {
        if (! $this->isMysql()) {
            return null;
        }

        return DB::selectOne(
            'select COLLATION_NAME as c from information_schema.COLUMNS where TABLE_SCHEMA = database() and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$table, $column]
        )?->c;
    }
};
