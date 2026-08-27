<?php

namespace App\Support;

use App\Models\PjctMain;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bulk insert for `pjct_main` from an xlsx sheet.
 *
 * Mirrors {@see AssetImportService}: insert-only, never an update. A row whose contract
 * number already exists is skipped; rows with no contract number fall back to
 * (nama + divisi) so a re-uploaded file does not create twins. Duplicates inside the
 * uploaded file itself are skipped the same way.
 */
class ProjectImportService
{
    public const SHEET_NAME = 'Projects';

    public const TYPES = ['RENT', 'SUPPLY', 'JASA'];

    public const STATUSES = ['UPC', 'OG', 'HVR', 'DLY', 'END'];

    /**
     * Division codes accepted by the import.
     *
     * Wider than the `TC`/`EQ` pair the +Project form offers, because the regional codes
     * already exist on live rows and a bulk file is usually a backfill of exactly those.
     */
    public const DIVISIONS = ['TC', 'TCREG1', 'TCREG2', 'EQ', 'EQC', 'EQREG1', 'EQREG2', 'EQREG3'];

    public const DIVISION_LABELS = [
        'TC' => 'Technology',
        'TCREG1' => 'Technology Regional 1',
        'TCREG2' => 'Technology Regional 2',
        'EQ' => 'Equipment',
        'EQC' => 'Equipment Commercial',
        'EQREG1' => 'Equipment Regional 1',
        'EQREG2' => 'Equipment Regional 2',
        'EQREG3' => 'Equipment Regional 3',
    ];

    public const STATUS_LABELS = [
        'UPC' => 'Upcoming',
        'OG' => 'On Going',
        'HVR' => 'Hand Over',
        'DLY' => 'Delay',
        'END' => 'Ended',
    ];

    /** Rows the dropdown validation covers — well past any realistic upload. */
    private const VALIDATION_ROWS = 5000;

    /** Template column order — index maps 1:1 to spreadsheet column A, B, C… */
    private const COLUMNS = [
        'pjct_contract', 'pjct_codate', 'pjct_div', 'pjct_name', 'pjct_type',
        'pjct_client', 'pjct_area', 'pjct_value', 'pjct_costart', 'pjct_totalperiod',
        'pjct_coend_m', 'pjct_status', 'pjct_misc',
    ];

    private const HEADERS = [
        'pjct_contract' => 'No. Kontrak',
        'pjct_codate' => 'Tanggal Kontrak (YYYY-MM-DD)',
        'pjct_div' => 'Divisi *',
        'pjct_name' => 'Nama Project *',
        'pjct_type' => 'Tipe *',
        'pjct_client' => 'Client',
        'pjct_area' => 'Area',
        'pjct_value' => 'Nilai Kontrak (Rp)',
        'pjct_costart' => 'Tanggal Mulai (YYYY-MM-DD)',
        'pjct_totalperiod' => 'Durasi (bulan)',
        'pjct_coend_m' => 'Tanggal Selesai (YYYY-MM-DD)',
        'pjct_status' => 'Status *',
        'pjct_misc' => 'Catatan',
    ];

    // ─── Parse ─────────────────────────────────────────────────────────────

    /**
     * Read the sheet into raw rows. `_line` keeps the spreadsheet row number so the
     * preview can point the user at the offending line.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME) ?? $spreadsheet->getSheet(0);

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [];
            foreach (self::COLUMNS as $i => $col) {
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1).$r)->getValue();
                $row[$col] = $this->normaliseCell($value);
            }

            if (collect($row)->every(fn ($v) => $v === null || $v === '')) {
                continue;
            }

            $row['_line'] = $r;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Trim a cell and treat the placeholders that database exports leave behind as blank.
     *
     * Sheets produced by phpMyAdmin / mysqldump-style tooling write empty columns as the
     * literal text `NULL` (or `\N`), which would otherwise be read as a real value and
     * fail validation on every single row.
     */
    private function normaliseCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return in_array(strtoupper($value), ['NULL', '\N', '#N/A'], true) ? null : $value;
    }

    // ─── Classify ──────────────────────────────────────────────────────────

    /**
     * Tag every parsed row as `new`, `skip` (duplicate) or `error` (invalid).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{status: string, message: string, line: int, row: array, data: array}>
     */
    public function classify(array $rows): array
    {
        $existing = $this->existingKeys();
        $prepared = [];

        // Pass 1 — validate each row on its own.
        foreach ($rows as $row) {
            $line = $row['_line'] ?? 0;
            $errors = [];

            $name = trim((string) ($row['pjct_name'] ?? ''));
            if ($name === '') {
                $errors[] = 'Nama Project wajib diisi';
            }

            $divRaw = trim((string) ($row['pjct_div'] ?? ''));
            $div = $this->matchOption($divRaw, self::DIVISIONS);
            if ($divRaw === '') {
                $errors[] = 'Divisi wajib diisi';
            } elseif (! $div) {
                $errors[] = "Divisi \"{$divRaw}\" tidak valid";
            }

            $typeRaw = trim((string) ($row['pjct_type'] ?? ''));
            if ($typeRaw === '') {
                $errors[] = 'Tipe wajib diisi';
            } elseif (! $this->matchOption($typeRaw, self::TYPES)) {
                $errors[] = "Tipe \"{$typeRaw}\" tidak valid";
            }

            $statRaw = trim((string) ($row['pjct_status'] ?? ''));
            if ($statRaw === '') {
                $errors[] = 'Status wajib diisi';
            } elseif (! $this->matchOption($statRaw, self::STATUSES)) {
                $errors[] = "Status \"{$statRaw}\" tidak valid";
            }

            $value = $row['pjct_value'] ?? null;
            if ($value !== null && $value !== '' && $this->toMoney($value) === null) {
                $errors[] = "Nilai Kontrak \"{$value}\" bukan angka";
            }

            $period = $row['pjct_totalperiod'] ?? null;
            if ($period !== null && $period !== '' && (! ctype_digit((string) $period) || (int) $period < 0)) {
                $errors[] = "Durasi \"{$period}\" harus bilangan bulat";
            }

            $dates = [];
            foreach ([
                'pjct_codate' => 'Tanggal Kontrak',
                'pjct_costart' => 'Tanggal Mulai',
                'pjct_coend_m' => 'Tanggal Selesai',
            ] as $col => $label) {
                $raw = $row[$col] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $dates[$col] = $this->toDate($raw);
                if ($dates[$col] === null) {
                    $errors[] = "{$label} tidak bisa dibaca (pakai YYYY-MM-DD)";
                }
            }

            // A contract that ends before it starts is a typo often enough to be worth
            // stopping — the two dates drive the whole period column on the monitoring table.
            if (! empty($dates['pjct_costart']) && ! empty($dates['pjct_coend_m'])
                && $dates['pjct_coend_m'] < $dates['pjct_costart']) {
                $errors[] = 'Tanggal Selesai lebih awal dari Tanggal Mulai';
            }

            $contract = trim((string) ($row['pjct_contract'] ?? ''));
            $key = $errors ? null : $this->dedupKey($contract, $name, (string) $div);

            $prepared[] = [
                'line' => $line,
                'row' => $row,
                'errors' => $errors,
                'key' => $key,
                'data' => $errors ? [] : $this->castRow($row, (string) $div),
                'existing' => $key === null ? null : ($existing[$key] ?? null),
            ];
        }

        // Pass 2 — group valid rows sharing a dedup key. A conflict can only be spotted
        // once the whole file is read, since the twin of the first row may appear
        // thousands of lines later.
        $groups = [];
        foreach ($prepared as $i => $p) {
            if (! $p['errors'] && ! $p['existing']) {
                $groups[$p['key']][] = $i;
            }
        }

        // Pass 3 — settle each row's verdict.
        $result = [];
        foreach ($prepared as $i => $p) {
            $base = ['line' => $p['line'], 'row' => $p['row'], 'data' => []];

            if ($p['errors']) {
                $result[] = $base + ['status' => 'error', 'message' => implode('; ', $p['errors'])];

                continue;
            }

            if ($p['existing']) {
                $result[] = $base + ['status' => 'skip', 'message' => $p['existing']];

                continue;
            }

            $group = $groups[$p['key']];

            if (count($group) > 1) {
                $others = array_values(array_diff(array_map(fn ($j) => $prepared[$j]['line'], $group), [$p['line']]));

                // Byte-identical twins are harmless — keep the first, drop the rest.
                // Twins that disagree are a data question only a human can settle.
                $identical = true;
                foreach ($group as $j) {
                    if ($prepared[$j]['data'] != $p['data']) {
                        $identical = false;
                        break;
                    }
                }

                if (! $identical) {
                    $result[] = $base + [
                        'status' => 'error',
                        'message' => 'Kembar dengan baris '.$this->joinLines($others).' tapi isinya berbeda — periksa mana yang benar',
                    ];

                    continue;
                }

                if ($i !== $group[0]) {
                    $result[] = $base + [
                        'status' => 'skip',
                        'message' => 'Duplikat persis dengan baris '.$prepared[$group[0]]['line'],
                    ];

                    continue;
                }
            }

            $result[] = ['status' => 'new', 'message' => '', 'line' => $p['line'], 'row' => $p['row'], 'data' => $p['data']];
        }

        return $result;
    }

    /**
     * @param  array<int, array{status: string}>  $classified
     * @return array{new: int, skip: int, error: int, total: int}
     */
    public function summarise(array $classified): array
    {
        return [
            'new' => count(array_filter($classified, fn ($r) => $r['status'] === 'new')),
            'skip' => count(array_filter($classified, fn ($r) => $r['status'] === 'skip')),
            'error' => count(array_filter($classified, fn ($r) => $r['status'] === 'error')),
            'total' => count($classified),
        ];
    }

    // ─── Execute ───────────────────────────────────────────────────────────

    /**
     * Insert every `new` row. Rows are re-classified by the caller against live data,
     * so this only has to assign IDs and write.
     *
     * @param  array<int, array{status: string, data: array}>  $classified
     * @return array{inserted: int, skipped: int, failed: int}
     */
    public function execute(array $classified, User $user): array
    {
        $pending = array_values(array_filter($classified, fn ($r) => $r['status'] === 'new'));
        $summary = $this->summarise($classified);

        if (! $pending) {
            return ['inserted' => 0, 'skipped' => $summary['skip'], 'failed' => $summary['error']];
        }

        $ids = [];

        DB::transaction(function () use ($pending, &$ids): void {
            // Reserved up front, so a batch that would cross the PJ9999 ceiling fails
            // before any row is written rather than half way through.
            $ids = PjctMain::nextIds(count($pending));
            $now = now();
            $rows = [];

            foreach ($pending as $i => $item) {
                $rows[] = ['id' => $ids[$i]] + $item['data'] + [
                    // `insert()` bypasses Eloquent, so timestamps are set by hand.
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                PjctMain::insert($chunk);
            }
        });

        // `insert()` also bypasses the `created` hook that normally makes the project's
        // document folder, so create them here — otherwise the first Kontrak/RKST upload
        // for an imported project would have nowhere to land.
        foreach ($ids as $id) {
            Storage::disk('docfile')->makeDirectory($id);
        }

        // One log line per import batch — a line per project would drown the activity log.
        SystemLog::create([
            'user_id' => $user->id,
            'action' => 'import',
            'loggable_type' => 'PjctMain',
            'loggable_id' => count($ids) === 1 ? $ids[0] : $ids[0].'..'.end($ids),
            'description' => sprintf('Import %d project dari Excel', count($ids)),
            'properties' => [
                'inserted' => count($ids),
                'skipped' => $summary['skip'],
                'failed' => $summary['error'],
                'ids' => $ids,
            ],
        ]);

        return [
            'inserted' => count($ids),
            'skipped' => $summary['skip'],
            'failed' => $summary['error'],
        ];
    }

    // ─── Template ──────────────────────────────────────────────────────────

    public function makeTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $this->writeHeader($sheet);

        // Divisi (C), Tipe (E) and Status (L) are picked from a list, never typed.
        $this->addDropdown($sheet, 'C', self::DIVISIONS, 'Divisi');
        $this->addDropdown($sheet, 'E', self::TYPES, 'Tipe');
        $this->addDropdown($sheet, 'L', self::STATUSES, 'Status');

        // The example lives on the Referensi sheet, not here — a sample row left in
        // place on this sheet would be imported as a real project.
        $this->addReferenceSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Attach a list-validation dropdown down an entire template column.
     *
     * The options are inlined into the formula rather than pointed at a range on the
     * Referensi sheet, so the dropdown survives if someone deletes or renames that sheet.
     */
    private function addDropdown(Worksheet $sheet, string $column, array $options, string $label): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowInputMessage(true);
        $validation->setPromptTitle($label);
        $validation->setPrompt('Pilih dari daftar.');
        $validation->setErrorTitle($label.' tidak valid');
        $validation->setError('Pilih salah satu: '.implode(', ', $options));
        $validation->setFormula1('"'.implode(',', $options).'"');

        $sheet->setDataValidation($column.'2:'.$column.self::VALIDATION_ROWS, $validation);
    }

    private function addReferenceSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Referensi');

        $sheet->getCell('A1')->setValue('Divisi (kolom C)');
        $sheet->getCell('B1')->setValue('Keterangan');
        $sheet->getCell('C1')->setValue('Tipe (kolom E)');
        $sheet->getCell('D1')->setValue('Status (kolom L)');
        $sheet->getCell('E1')->setValue('Keterangan Status');

        foreach (range('A', 'E') as $col) {
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $r = 2;
        foreach (self::DIVISION_LABELS as $code => $label) {
            $sheet->getCell('A'.$r)->setValue($code);
            $sheet->getCell('B'.$r)->setValue($label);
            $r++;
        }

        foreach (self::TYPES as $i => $type) {
            $sheet->getCell('C'.($i + 2))->setValue($type);
        }

        $r = 2;
        foreach (self::STATUS_LABELS as $code => $label) {
            $sheet->getCell('D'.$r)->setValue($code);
            $sheet->getCell('E'.$r)->setValue($label);
            $r++;
        }

        $this->addExampleBlock($sheet);
        $this->addExistingProjectBlock($sheet);

        $sheet->freezePane('A2');
    }

    /**
     * One filled-in row shown as column → value pairs, kept off the Projects sheet so it
     * can never be imported by accident.
     */
    private function addExampleBlock(Worksheet $sheet): void
    {
        $example = [
            'pjct_contract' => 'IAS/TC/2026/0042',
            'pjct_codate' => '2026-01-10',
            'pjct_div' => 'TC',
            'pjct_name' => 'Pengadaan Perangkat X-Ray Bandara Soekarno-Hatta',
            'pjct_type' => 'RENT',
            'pjct_client' => 'PT Integrasi Aviasi Solusi',
            'pjct_area' => 'CGK',
            'pjct_value' => '1500000000',
            'pjct_costart' => '2026-02-01',
            'pjct_totalperiod' => '12',
            'pjct_coend_m' => '2027-01-31',
            'pjct_status' => 'OG',
            'pjct_misc' => 'Perpanjangan kontrak tahun sebelumnya',
        ];

        $sheet->getCell('G1')->setValue('Contoh pengisian');
        $sheet->getCell('H1')->setValue('Nilai');

        foreach (['G', 'H'] as $col) {
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $r = 2;
        foreach ($example as $col => $value) {
            $letter = Coordinate::stringFromColumnIndex(array_search($col, self::COLUMNS, true) + 1);
            $sheet->getCell('G'.$r)->setValue($letter.' — '.self::HEADERS[$col]);
            $sheet->getCell('H'.$r)->setValueExplicit((string) $value, DataType::TYPE_STRING);
            $r++;
        }
    }

    /**
     * Every project already in the system, so the user can see what would be skipped as a
     * duplicate before uploading rather than after.
     */
    private function addExistingProjectBlock(Worksheet $sheet): void
    {
        $sheet->getCell('J1')->setValue('Project ID');
        $sheet->getCell('K1')->setValue('No. Kontrak');
        $sheet->getCell('L1')->setValue('Nama Project');
        $sheet->getCell('M1')->setValue('Divisi');
        $sheet->getCell('N1')->setValue('Status');

        foreach (range('J', 'N') as $col) {
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $projects = PjctMain::withTrashed()->orderBy('id')
            ->get(['id', 'pjct_contract', 'pjct_name', 'pjct_div', 'pjct_status']);

        $r = 2;
        foreach ($projects as $p) {
            $sheet->getCell('J'.$r)->setValue($p->id);
            // Contract numbers are slash-heavy strings — force text so Excel never
            // reads one as a date or fraction.
            $sheet->getCell('K'.$r)->setValueExplicit((string) $p->pjct_contract, DataType::TYPE_STRING);
            $sheet->getCell('L'.$r)->setValue($p->pjct_name);
            $sheet->getCell('M'.$r)->setValue($p->pjct_div);
            $sheet->getCell('N'.$r)->setValue($p->pjct_status);
            $r++;
        }
    }

    private function writeHeader(Worksheet $sheet): void
    {
        foreach (self::COLUMNS as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getCell($letter.'1')->setValue(self::HEADERS[$col]);
            $sheet->getStyle($letter.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            ]);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Dedup keys of every project already stored, mapped to the message shown when an
     * uploaded row collides with one.
     *
     * Archived (soft-deleted) projects are included: silently creating a twin of a project
     * someone deliberately archived is worse than making them un-archive it.
     *
     * @return array<string, string>
     */
    private function existingKeys(): array
    {
        return PjctMain::withTrashed()
            ->get(['id', 'pjct_contract', 'pjct_name', 'pjct_div', 'deleted_at'])
            ->mapWithKeys(function ($p) {
                $key = $this->dedupKey(
                    (string) $p->pjct_contract,
                    (string) $p->pjct_name,
                    (string) $p->pjct_div
                );

                $reason = $p->pjct_contract
                    ? "No. Kontrak sudah terdaftar di {$p->id}"
                    : "Nama + divisi sudah terdaftar di {$p->id}";

                return [$key => $reason.($p->deleted_at ? ' (project diarsipkan)' : '')];
            })
            ->all();
    }

    /**
     * Identity of a project row.
     *
     * The contract number is the real business key, but it is nullable and plenty of live
     * rows leave it blank — those fall back to (nama + divisi), which is what a human
     * would compare by eye.
     */
    private function dedupKey(string $contract, string $name, string $div): string
    {
        $contract = trim($contract);

        return $contract !== ''
            ? 'c|'.mb_strtolower($contract)
            : 'n|'.mb_strtolower(trim($name)).'|'.mb_strtolower(trim($div));
    }

    /** "3062, 3063 dan 3074" — reads better than a bare comma list in an error message. */
    private function joinLines(array $lines): string
    {
        if (count($lines) <= 1) {
            return (string) ($lines[0] ?? '');
        }

        $last = array_pop($lines);

        return implode(', ', $lines).' dan '.$last;
    }

    /** Case-insensitive match that returns the canonical spelling, or null. */
    private function matchOption(string $value, array $options): ?string
    {
        foreach ($options as $option) {
            if (strcasecmp(trim($value), $option) === 0) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Build the insert payload. `pjct_value` and the two NOT NULL text columns fall back
     * the way the legacy rows do; dates and period stay null when blank.
     *
     * @return array<string, mixed>
     */
    private function castRow(array $row, string $div): array
    {
        $period = $row['pjct_totalperiod'] ?? null;

        $data = [
            'pjct_div' => $div,
            'pjct_type' => $this->matchOption(trim((string) ($row['pjct_type'] ?? '')), self::TYPES),
            'pjct_status' => $this->matchOption(trim((string) ($row['pjct_status'] ?? '')), self::STATUSES),
            'pjct_value' => $this->toMoney($row['pjct_value'] ?? null) ?? 0,
            'pjct_totalperiod' => ($period === null || $period === '') ? null : (int) $period,
            'pjct_codate' => $this->toDate($row['pjct_codate'] ?? null),
            'pjct_costart' => $this->toDate($row['pjct_costart'] ?? null),
            'pjct_coend_m' => $this->toDate($row['pjct_coend_m'] ?? null),
            'pjct_budgetid' => null,
        ];

        foreach (self::COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                continue;
            }
            $value = trim((string) ($row[$col] ?? ''));
            // `pjct_name` is NOT NULL; the rest are nullable, and a blank cell means
            // "unknown" rather than an empty string.
            $data[$col] = ($value === '' && $col !== 'pjct_name') ? null : $value;
        }

        return $data;
    }

    /**
     * Accepts a bare number or a typed-in Rupiah string (`Rp 1.500.000`, `1,500,000`).
     *
     * Returns null when the cell holds something that is not a number at all, so the
     * caller can report it rather than silently importing a zero.
     */
    private function toMoney(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value);
        }

        $clean = preg_replace('/[^0-9]/', '', (string) $value);

        if ($clean === '' || $clean === null) {
            return null;
        }

        return (int) $clean;
    }

    /** Accepts an Excel date serial, a DateTime, or a parseable string. */
    private function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
