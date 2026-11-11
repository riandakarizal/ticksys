<?php

namespace App\Support;

use App\Models\Letter;
use App\Models\LetterCounter;
use App\Models\LetterType;
use App\Models\SystemLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Impor register nomor surat lama dari Excel "NOMOR SURAT IAS - EQUIPMENT & TECHNOLOGY OPERATION"
 * (satu sheet per jenis surat, kolom A–J: NO, TANGGAL, BULAN, TAHUN, TIPE, NOMOR, PERIHAL,
 * PENANDATANGAN, TUJUAN, PIC). Dijalankan sekali lewat `php artisan letters:import`.
 *
 * Nomor diambil dari kolom NOMOR SURAT (yang tercetak di surat), bukan disusun ulang dari kolom
 * BULAN/TAHUN. Data lama dibiarkan apa adanya — kejanggalan tidak diperbaiki diam-diam tetapi
 * dicatat di `import_note`: nomor ganda, tahun di nomor melebihi tahun berjalan, perihal kosong,
 * nomor yang dirapikan (koma → titik, urutan tanpa nol di depan).
 *
 * Setelah impor, `letter_counters` dinaikkan ke urutan tertinggi per kode+tahun (tahun berjalan dan
 * sebelumnya), supaya nomor pertama yang dibuat di PRISM melanjutkan register.
 */
class LetterImportService
{
    /** Sheet → jenis surat bawaan. Sheet yang tidak ada di sini dipetakan lewat kode di nomornya. */
    private const SHEET_TYPES = [
        'FR' => 'Finding Report',
        'BA TKDN' => 'BA TKDN',
        'BAFAT' => 'BA FAT',
        'BA NDA' => 'NDA',
        'SKR' => 'Surat Keluar',
        'NODIN' => 'Nota Dinas',
        'SPK' => 'SPK',
        'BAPP' => 'BA Pemeriksaan / Penyelesaian Pekerjaan (BAPP)',
        'BAST' => 'BA Serah Terima (BAST)',
        'BAE' => 'BA Evaluasi (BAE)',
        'BAPF' => 'BA Pemeriksaan Fasilitas (BAPF)',
        'RKST' => 'RKST',
        'BA Utilisasi' => 'BA Utilisasi',
        'BA TRAINING' => 'BA Training & Familiarization',
        'BAAM' => 'BA Approval Material (BAAM)',
        'BAPM' => 'BA Pembayaran (BAPM)',
        'BAK' => 'BA Kesepakatan (BAK)',
        'SPH' => 'Surat Permohonan Penawaran Harga',
        'BA REKON' => 'BA Rekonsiliasi',
        'BAPPP' => 'BA Pemeriksaan Pelaksanaan Pekerjaan (BAPPP)',
        'BA-SAT' => 'BA Site Acceptance Test (SAT)',
        'BASTO' => 'BAST Operasi (BASTO)',
        'BAOPS' => 'BA Operasional',
        'PO WO' => 'Purchase Order (PO)',
        'BAP' => 'BA Pemakaian',
        'Quotation' => 'Quotation',
        'BA Penyelesaian' => 'BA Penyelesaian',
        'MEMO INTERNAL' => 'Memo Internal',
        'SURAT JALAN' => 'Surat Jalan',
        'MoM' => 'Risalah Rapat (MoM)',
        'SOP' => 'SOP',
        'BAJ' => 'BA Justifikasi (BAJ)',
        'BAKn' => 'BA Klarifikasi Negosiasi (BAKN)',
        'Surat Edaran' => 'Surat Edaran',
        'Laporan Pekerjaan' => 'Laporan Hasil Pekerjaan',
        'UAT' => 'BA UAT',
    ];

    /** Sheet yang memuat beberapa jenis: kode di nomor → jenis surat. */
    private const SHEET_CODE_TYPES = [
        'SPK' => ['SPMK' => 'SPMK', 'ADD' => 'Addendum'],
        'BAOPS' => ['BAP' => 'BA Pemakaian', 'BAAM' => 'BA Approval Material (BAAM)'],
        'PO WO' => ['WO' => 'Work Order (WO)'],
        'BAJ' => ['BAP' => 'BA Pemakaian'],
    ];

    private const MONTH_NAMES = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    private const NUMBER_PATTERN = '~^IASS/([A-Z]+)/([A-Z-]+)/([IVX]+)/(\d{4})/(\d+)(?:[.,](\d+))?$~';

    /**
     * Baca workbook dan tentukan nasib tiap baris.
     *
     * @return array{rows: list<array<string, mixed>>, skipped: list<array{sheet: string, row: int, number: string, reason: string}>}
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $types = LetterType::query()->get()->keyBy('name');
        $typesByCode = LetterType::query()->orderBy('id')->get()->groupBy('code')->map->first();
        $currentYear = (int) now()->year;

        $rows = [];
        $skipped = [];
        $seen = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            // Sheet tersembunyi = salinan kerja (mis. "BAKn (2)"), bukan register.
            if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }

            $title = $sheet->getTitle();
            $highest = $sheet->getHighestDataRow();

            for ($r = 1; $r <= $highest; $r++) {
                $cells = $this->readRow($sheet, $r);
                $rawNumber = trim((string) ($cells['F'] ?? ''));
                $hasData = collect(['B', 'G', 'H', 'I', 'J'])->contains(fn ($c) => trim((string) ($cells[$c] ?? '')) !== '');

                if (in_array(trim((string) ($cells['A'] ?? '')), ['NO', 'Kolom1'], true) || ! $hasData) {
                    continue;
                }

                $parsed = $this->parseNumber($rawNumber);
                if ($parsed === null) {
                    $skipped[] = ['sheet' => $title, 'row' => $r, 'number' => $rawNumber, 'reason' => 'Nomor tidak lengkap / tidak terbaca'];

                    continue;
                }

                $subject = $this->clean($cells['G'] ?? null, 500);
                $key = $parsed['number'];
                $subjectKey = mb_strtolower((string) $subject);

                $type = $this->resolveType($title, $parsed['code'], $types, $typesByCode);
                if (! $type) {
                    $skipped[] = ['sheet' => $title, 'row' => $r, 'number' => $key, 'reason' => "Jenis surat untuk kode {$parsed['code']} tidak ditemukan"];

                    continue;
                }

                // Baris yang sama persis (nomor, jenis, perihal) disalin ke sheet lain (mis. BAP → BAJ)
                // cukup diimpor sekali. Jenis berbeda dengan nomor sama (BA FAT & BA TKDN) = dua surat.
                $copyOf = collect($seen[$key] ?? [])->first(fn ($s) => $s['subject'] === $subjectKey && $s['type'] === $type->id);
                if ($copyOf) {
                    $skipped[] = ['sheet' => $title, 'row' => $r, 'number' => $key, 'reason' => "Salinan dari sheet {$copyOf['sheet']} baris {$copyOf['row']}"];

                    continue;
                }

                $notes = [];
                if ($parsed['number'] !== $parsed['raw']) {
                    $notes[] = 'Nomor asli di Excel: '.$rawNumber;
                }
                // Tahun salah ketik: tanggal yang dilengkapi dari nomor ikut salah, jadi dikosongkan
                // (kalau tidak, baris ini selalu muncul paling atas di urutan "tanggal terbaru").
                $yearSuspect = $parsed['year'] > $currentYear;
                if ($yearSuspect) {
                    $notes[] = "Tahun di nomor ({$parsed['year']}) melebihi tahun berjalan — kemungkinan salah ketik di Excel";
                }
                if ($subject === null) {
                    $notes[] = 'Perihal kosong di Excel';
                }

                $seen[$key][] = ['sheet' => $title, 'row' => $r, 'subject' => $subjectKey, 'type' => $type->id, 'index' => count($rows)];

                $rows[] = [
                    'sheet' => $title,
                    'row' => $r,
                    'letter_type_id' => $type->id,
                    'type_name' => $type->name,
                    'number' => $parsed['number'],
                    'unit_code' => $parsed['unit'],
                    'code' => $parsed['code'],
                    'year' => $parsed['year'],
                    'month' => $parsed['month'],
                    'seq' => $parsed['seq'],
                    'sub_seq' => $parsed['sub'],
                    'letter_date' => $yearSuspect ? null : $this->parseDate($cells['B'] ?? null, $parsed['year'], $parsed['month'])?->toDateString(),
                    'subject' => $subject,
                    'signer' => $this->clean($cells['H'] ?? null, 255),
                    'recipient' => $this->clean($cells['I'] ?? null, 255),
                    'pic' => ($pic = $this->clean($cells['J'] ?? null, 100)) ? mb_convert_case(mb_strtolower($pic), MB_CASE_TITLE) : null,
                    'notes' => $notes,
                ];
            }
        }

        // Nomor yang sama dipakai dua surat berbeda — ditandai di semua barisnya.
        foreach ($seen as $number => $occurrences) {
            if (count($occurrences) < 2) {
                continue;
            }

            foreach ($occurrences as $occ) {
                $others = collect($occurrences)->reject(fn ($o) => $o['index'] === $occ['index'])
                    ->map(fn ($o) => "{$o['sheet']} baris {$o['row']}")->implode(', ');
                $rows[$occ['index']]['notes'][] = "Nomor ganda di Excel (juga di {$others})";
            }
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * Tulis hasil parse() ke tabel `letters` dan naikkan counter.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{inserted: int, counters: int}
     */
    public function execute(array $rows, ?string $userId, bool $replace = false): array
    {
        $currentYear = (int) now()->year;
        $counters = 0;

        DB::transaction(function () use ($rows, $userId, $replace, $currentYear, &$counters): void {
            if ($replace) {
                Letter::query()->where('source', 'import')->delete();
            }

            $now = now();
            $records = array_map(fn (array $row) => [
                'letter_type_id' => $row['letter_type_id'],
                'number' => $row['number'],
                'unit_code' => $row['unit_code'],
                'code' => $row['code'],
                'year' => $row['year'],
                'month' => $row['month'],
                'seq' => $row['seq'],
                'sub_seq' => $row['sub_seq'],
                'letter_date' => $row['letter_date'],
                'subject' => $row['subject'],
                'signer' => $row['signer'],
                'recipient' => $row['recipient'],
                'pic' => $row['pic'],
                'status' => Letter::STATUS_ACTIVE,
                'source' => 'import',
                'import_note' => $row['notes'] ? mb_substr(implode(' · ', $row['notes']), 0, 255) : null,
                'created_by' => $userId,
                'updated_by' => $userId,
                // insert() melewati Eloquent, jadi timestamp diisi manual.
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows);

            foreach (array_chunk($records, 500) as $chunk) {
                Letter::insert($chunk);
            }

            // Tahun di masa depan (salah ketik) tidak menaikkan counter — nomor yang bentrok
            // dengan data itu dilompati otomatis oleh LetterNumberService.
            $max = collect($rows)
                ->filter(fn ($r) => $r['year'] <= $currentYear)
                ->groupBy(fn ($r) => $r['code'].'|'.$r['year'])
                ->map(fn ($group) => $group->max('seq'));

            foreach ($max as $key => $seq) {
                [$code, $year] = explode('|', $key);
                $counter = LetterCounter::query()->firstOrCreate(['code' => $code, 'year' => (int) $year], ['last_seq' => 0]);
                if ($counter->last_seq < $seq) {
                    $counter->update(['last_seq' => $seq]);
                }
                $counters++;
            }
        });

        // Satu baris log untuk seluruh impor — satu baris per surat akan membanjiri log aktivitas.
        SystemLog::create([
            'user_id' => $userId,
            'action' => 'import',
            'loggable_type' => 'Letter',
            'loggable_id' => 'import',
            'description' => sprintf('Import %d nomor surat dari Excel', count($rows)),
            'properties' => ['inserted' => count($rows), 'counters' => $counters, 'replace' => $replace],
        ]);

        return ['inserted' => count($rows), 'counters' => $counters];
    }

    /** @return array<string, mixed> kolom A–J */
    private function readRow(Worksheet $sheet, int $row): array
    {
        $cells = [];
        foreach (range('A', 'J') as $col) {
            $cell = $sheet->getCell($col.$row);
            // Kolom NOMOR SURAT berisi rumus CONCATENATE — pakai hasil terakhir yang tersimpan di file.
            $cells[$col] = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        }

        return $cells;
    }

    /**
     * @return array{raw: string, number: string, unit: string, code: string, month: int, year: int, seq: int, sub: int|null}|null
     */
    private function parseNumber(string $raw): ?array
    {
        $compact = strtoupper(preg_replace('/\s+/', '', $raw));

        if (! preg_match(self::NUMBER_PATTERN, $compact, $m)) {
            return null;
        }

        $month = array_search($m[3], Letter::ROMAN_MONTHS, true);
        if ($month === false) {
            return null;
        }

        $sub = isset($m[6]) && $m[6] !== '' ? (int) $m[6] : null;

        return [
            'raw' => $compact,
            'number' => Letter::formatNumber($m[1], $m[2], $month, (int) $m[4], (int) $m[5], $sub),
            'unit' => $m[1],
            'code' => $m[2],
            'month' => $month,
            'year' => (int) $m[4],
            'seq' => (int) $m[5],
            'sub' => $sub,
        ];
    }

    private function resolveType(string $sheet, string $code, $types, $typesByCode): ?LetterType
    {
        $name = self::SHEET_CODE_TYPES[$sheet][$code] ?? self::SHEET_TYPES[$sheet] ?? null;

        return ($name ? $types->get($name) : null) ?? $typesByCode->get($code);
    }

    /**
     * Kolom TANGGAL di register campur aduk: angka hari saja (24), tanggal Excel penuh, tanggal 1900
     * (angka hari yang terformat sebagai tanggal), "27 Februari 2026", "07/05/2026". Hari saja
     * dilengkapi bulan & tahun dari nomornya.
     */
    private function parseDate(mixed $raw, int $year, int $month): ?CarbonImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $n = (float) $raw;
            if ($n >= 1 && $n <= 31 && floor($n) === $n) {
                return $this->safeDate($year, $month, (int) $n);
            }
            if ($n > 30000) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($n))->startOfDay();
            }

            return null;
        }

        $s = mb_strtolower(trim((string) $raw));

        if (preg_match('~^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$~', $s, $m)) {
            return $this->safeDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $s, $m)) {
            return $this->safeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('~^(\d{1,2})\b~', $s, $m)) {
            $monthFromName = collect(self::MONTH_NAMES)->first(fn ($i, $name) => str_contains($s, $name));
            $yearInText = preg_match('~\b(20\d{2})\b~', $s, $y) ? (int) $y[1] : $year;

            return $this->safeDate($yearInText, $monthFromName ?? $month, (int) $m[1]);
        }

        return null;
    }

    private function safeDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day) : null;
    }

    private function clean(mixed $value, int $max): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' || $text === '-' ? null : mb_substr($text, 0, $max);
    }
}
