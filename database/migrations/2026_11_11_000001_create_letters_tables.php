<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register nomor surat (menu Surat → Nomor Surat), menggantikan Excel
 * "NOMOR SURAT IAS - EQUIPMENT & TECHNOLOGY OPERATION".
 *
 * Format nomor: IASS/{unit}/{kode}/{bulan romawi}/{tahun}/{urut 3 digit}[.{turunan}]
 *
 * - letter_types    : master jenis surat. Beberapa jenis boleh berbagi kode (mis. BA Utilisasi &
 *                     BAPPP sama-sama BAPPP) — urutan dihitung per KODE, jadi nomornya tidak bisa dobel.
 * - letter_counters : satu baris per kode + tahun, dikunci (lockForUpdate) saat mengambil nomor,
 *                     supaya dua admin yang menyimpan bersamaan tidak mendapat nomor yang sama.
 * - letters         : nomor yang sudah dikeluarkan. Tidak pernah dihapus — salah buat → dibatalkan.
 *                     `number` sengaja tidak unique: data impor Excel memuat nomor ganda (ditandai di
 *                     import_note). Nomor baru dijamin unik oleh counter + cek bentrok di LetterNumberService.
 */
return new class extends Migration
{
    /** Jenis surat bawaan, diambil dari sheet Excel register (Nov 2026). [nama, kode, unit] */
    private const TYPES = [
        ['Surat Keluar', 'SKR', 'DEO'],
        ['Nota Dinas', 'NODIN', 'DEO'],
        ['Memo Internal', 'MI', 'DEC'],
        ['Surat Edaran', 'SE', 'DEO'],
        ['Surat Jalan', 'SJ', 'DEO'],
        ['Surat Permohonan Penawaran Harga', 'PPH', 'DEO'],
        ['Quotation', 'QUO', 'DEO'],
        ['SPK', 'SPK', 'DEO'],
        ['SPMK', 'SPMK', 'DEO'],
        ['Addendum', 'ADD', 'DEO'],
        ['Purchase Order (PO)', 'PO', 'DEO'],
        ['Work Order (WO)', 'WO', 'DEO'],
        ['NDA', 'NDA', 'DEO'],
        ['RKST', 'RKST', 'DEO'],
        ['SOP', 'SOP', 'DEO'],
        ['Finding Report', 'FR', 'DEO'],
        ['Laporan Hasil Pekerjaan', 'LHP', 'DEO'],
        ['Risalah Rapat (MoM)', 'RR', 'DEO'],
        ['BA Serah Terima (BAST)', 'BAST', 'DEO'],
        ['BAST Operasi (BASTO)', 'BASTO', 'DEO'],
        ['BA Pemeriksaan / Penyelesaian Pekerjaan (BAPP)', 'BAPP', 'DEO'],
        ['BA Pemeriksaan Pelaksanaan Pekerjaan (BAPPP)', 'BAPPP', 'DEO'],
        ['BA Utilisasi', 'BAPPP', 'DEO'],
        ['BA Training & Familiarization', 'BAPPP', 'DEO'],
        ['BA Pembayaran (BAPM)', 'BAPM', 'DEO'],
        ['BA Pemakaian', 'BAP', 'DEO'],
        ['BA Penyelesaian', 'BAP', 'DEO'],
        ['BA Operasional', 'BAAL', 'DEO'],
        ['BA Evaluasi (BAE)', 'BAE', 'DEO'],
        ['BA Pemeriksaan Fasilitas (BAPF)', 'BAPF', 'DEO'],
        ['BA Approval Material (BAAM)', 'BAAM', 'DEO'],
        ['BA Kesepakatan (BAK)', 'BAK', 'DEO'],
        ['BA Klarifikasi Negosiasi (BAKN)', 'BAKN', 'DEO'],
        ['BA Justifikasi (BAJ)', 'BAJ', 'DEO'],
        ['BA FAT', 'FAT', 'DEO'],
        ['BA TKDN', 'FAT', 'DEO'],
        ['BA Site Acceptance Test (SAT)', 'BA-SAT', 'DEO'],
        ['BA UAT', 'BA-UAT', 'DEO'],
        ['BA Rekonsiliasi', 'REKON', 'DEO'],
    ];

    public function up(): void
    {
        Schema::create('letter_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->index();
            $table->string('unit_code', 10)->default('DEO');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('letter_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_seq')->default(0);
            $table->timestamps();
            $table->unique(['code', 'year']);
        });

        Schema::create('letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('letter_type_id')->constrained('letter_types');
            $table->string('number', 80)->index();
            $table->string('unit_code', 10);
            $table->string('code', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('seq');
            $table->unsignedInteger('sub_seq')->nullable();
            $table->date('letter_date')->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('signer', 255)->nullable();
            $table->string('recipient', 255)->nullable();
            $table->string('pic', 100)->nullable();

            // Tanpa FK constraint ke pjct_main (PK string legacy) — sama seperti tickets.pjct_id.
            $pjct = $table->string('pjct_id', 10)->nullable()->index();
            if ($collation = $this->columnCollation('pjct_main', 'id')) {
                $pjct->collation($collation);
            }

            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('status', 10)->default('active');
            $table->string('cancel_reason', 255)->nullable();
            $table->string('cancelled_by', 20)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('source', 10)->default('app');
            $table->string('import_note', 255)->nullable();
            $table->string('created_by', 20)->nullable();
            $table->string('updated_by', 20)->nullable();
            $table->timestamps();

            $table->index(['code', 'year', 'seq']);
            $table->index('letter_date');
        });

        $now = now();
        DB::table('letter_types')->insert(array_map(fn (array $t) => [
            'name' => $t[0],
            'code' => $t[1],
            'unit_code' => $t[2],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::TYPES));
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_counters');
        Schema::dropIfExists('letter_types');
    }

    private function columnCollation(string $table, string $column): ?string
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable($table)) {
            return null;
        }

        return DB::selectOne(
            'select COLLATION_NAME as c from information_schema.COLUMNS where TABLE_SCHEMA = database() and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$table, $column]
        )?->c;
    }
};
