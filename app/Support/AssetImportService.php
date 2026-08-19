<?php

namespace App\Support;

use App\Models\AstMain;
use App\Models\PjctMain;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bulk insert for `ast_main` from an xlsx sheet.
 *
 * Insert-only: a row whose (ast_pjctid + ast_serial) pair already exists is skipped,
 * never updated. Duplicates inside the uploaded file itself are skipped the same way.
 */
class AssetImportService
{
    public const SHEET_NAME = 'Assets';

    public const CONDITIONS = ['Excellence', 'Good', 'Fair', 'Bad'];

    /**
     * Importable statuses. Narrower than what `ast_main` holds historically —
     * `Aktif-Sewa` / `Aktif-SewaBeli` exist on legacy rows and still render on the
     * assets page, but are deliberately not offered (or accepted) via import.
     */
    public const STATUSES = ['Aktif', 'Back Up', 'Pinjam', 'Non-Aktif'];

    /** Rows the dropdown validation covers — well past any realistic upload. */
    private const VALIDATION_ROWS = 5000;

    /** Template column order — index maps 1:1 to spreadsheet column A, B, C… */
    private const COLUMNS = [
        'ast_pjctid', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_prodyear',
        'ast_serial', 'ast_vendid', 'ast_username', 'ast_userreg', 'ast_userloc',
        'ast_userlocdet', 'ast_cond', 'ast_delvdate', 'ast_purcdate', 'ast_stat', 'ast_misc',
    ];

    private const HEADERS = [
        'ast_pjctid' => 'Project ID *',
        'ast_type' => 'Jenis Aset *',
        'ast_brand' => 'Merk *',
        'ast_brandmodel' => 'Model / Seri',
        'ast_prodyear' => 'Tahun Produksi',
        'ast_serial' => 'Serial Number *',
        'ast_vendid' => 'Vendor ID',
        'ast_username' => 'Nama Pengguna',
        'ast_userreg' => 'Region',
        'ast_userloc' => 'Lokasi',
        'ast_userlocdet' => 'Detail Lokasi',
        'ast_cond' => 'Kondisi',
        'ast_delvdate' => 'Tanggal Kirim (YYYY-MM-DD)',
        'ast_purcdate' => 'Tanggal Beli (YYYY-MM-DD)',
        'ast_stat' => 'Status *',
        'ast_misc' => 'Catatan',
    ];

    /** Columns that are NOT NULL in the legacy schema and have no default. */
    private const NOT_NULL_TEXT = [
        'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_serial', 'ast_vendid',
        'ast_username', 'ast_userreg', 'ast_userloc', 'ast_userlocdet',
        'ast_cond', 'ast_stat', 'ast_docid', 'ast_misc',
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
        $projectIds = PjctMain::pluck('id')
            ->mapWithKeys(fn ($id) => [strtolower($id) => $id])
            ->all();

        $existing = $this->existingKeys();
        $prepared = [];

        // Pass 1 — validate each row on its own.
        foreach ($rows as $row) {
            $line = $row['_line'] ?? 0;
            $errors = [];

            $pjctIdRaw = (string) ($row['ast_pjctid'] ?? '');
            $pjctId = $projectIds[strtolower(trim($pjctIdRaw))] ?? null;
            if ($pjctIdRaw === '') {
                $errors[] = 'Project ID wajib diisi';
            } elseif (! $pjctId) {
                $errors[] = "Project ID \"{$pjctIdRaw}\" tidak ada di pjct_main";
            }

            $serial = trim((string) ($row['ast_serial'] ?? ''));
            if ($serial === '') {
                $errors[] = 'Serial Number wajib diisi';
            } elseif ($this->isPlaceholderSerial($serial)) {
                $errors[] = "Serial Number \"{$serial}\" bukan nomor seri — isi nomor seri asli";
            }

            if (trim((string) ($row['ast_type'] ?? '')) === '') {
                $errors[] = 'Jenis Aset wajib diisi';
            }

            if (trim((string) ($row['ast_brand'] ?? '')) === '') {
                $errors[] = 'Merk wajib diisi';
            }

            $cond = trim((string) ($row['ast_cond'] ?? ''));
            if ($cond !== '' && ! $this->matchOption($cond, self::CONDITIONS)) {
                $errors[] = "Kondisi \"{$cond}\" tidak valid";
            }

            $stat = trim((string) ($row['ast_stat'] ?? ''));
            if ($stat === '') {
                $errors[] = 'Status wajib diisi';
            } elseif (! $this->matchOption($stat, self::STATUSES)) {
                $errors[] = "Status \"{$stat}\" tidak valid";
            }

            $year = $row['ast_prodyear'] ?? null;
            if ($year !== null && $year !== '' && (! ctype_digit((string) $year) || (int) $year < 1901 || (int) $year > 2155)) {
                $errors[] = "Tahun Produksi \"{$year}\" tidak valid";
            }

            foreach (['ast_delvdate' => 'Tanggal Kirim', 'ast_purcdate' => 'Tanggal Beli'] as $col => $label) {
                if (($row[$col] ?? '') !== '' && $row[$col] !== null && $this->toDate($row[$col]) === null) {
                    $errors[] = "{$label} tidak bisa dibaca (pakai YYYY-MM-DD)";
                }
            }

            $prepared[] = [
                'line' => $line,
                'row' => $row,
                'errors' => $errors,
                'key' => $errors ? null : $this->dedupKey($pjctId, $serial),
                'data' => $errors ? [] : $this->castRow($row, $pjctId),
                'existing' => $errors ? null : ($existing[$this->dedupKey($pjctId, $serial)] ?? null),
            ];
        }

        // Pass 2 — group valid rows sharing a (project + serial) key. A conflict can
        // only be spotted once the whole file is read, since the twin of the first row
        // may appear thousands of lines later.
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
                $result[] = $base + [
                    'status' => 'skip',
                    'message' => "Serial sudah terdaftar di {$p['data']['ast_pjctid']} ({$p['existing']})",
                ];

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
                        'message' => 'Serial kembar dengan baris '.$this->joinLines($others).' tapi isinya berbeda — periksa mana yang benar',
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
            // Reserved up front, so a month that cannot fit the whole batch fails
            // before any row is written rather than half way through.
            $ids = AstMain::nextIds(count($pending));
            $rows = [];

            foreach ($pending as $i => $item) {
                $rows[] = ['id' => $ids[$i]] + $item['data'];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                AstMain::insert($chunk);
            }
        });

        // One log line per import batch — a line per asset would drown the activity log.
        SystemLog::create([
            'user_id' => $user->id,
            'action' => 'import',
            'loggable_type' => 'AstMain',
            'loggable_id' => count($ids) === 1 ? $ids[0] : $ids[0].'..'.end($ids),
            'description' => sprintf('Import %d aset dari Excel', count($ids)),
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

        // Kondisi (L) and Status (O) are picked from a list, never typed.
        $this->addDropdown($sheet, 'L', self::CONDITIONS, 'Kondisi');
        $this->addDropdown($sheet, 'O', self::STATUSES, 'Status');

        // The example lives on the Referensi sheet, not here — a sample row left in
        // place on this sheet would be imported as a real asset.
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

        $sheet->getCell('A1')->setValue('Project ID');
        $sheet->getCell('B1')->setValue('No. Kontrak');
        $sheet->getCell('C1')->setValue('Nama Project');
        $sheet->getCell('D1')->setValue('Divisi');
        $sheet->getCell('E1')->setValue('Status');
        // Column F is a blank spacer between the project list and the enum lists.
        $sheet->getCell('G1')->setValue('Kondisi (kolom L)');
        $sheet->getCell('H1')->setValue('Status Aset (kolom O)');

        foreach (range('A', 'H') as $col) {
            if ($col === 'F') {
                continue;
            }
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $projects = PjctMain::orderBy('id')
            ->get(['id', 'pjct_contract', 'pjct_name', 'pjct_div', 'pjct_status']);

        $r = 2;
        foreach ($projects as $p) {
            $sheet->getCell('A'.$r)->setValue($p->id);
            // Contract numbers are slash-heavy strings — force text so Excel never
            // reads one as a date or fraction.
            $sheet->getCell('B'.$r)->setValueExplicit((string) $p->pjct_contract, DataType::TYPE_STRING);
            $sheet->getCell('C'.$r)->setValue($p->pjct_name);
            $sheet->getCell('D'.$r)->setValue($p->pjct_div);
            $sheet->getCell('E'.$r)->setValue($p->pjct_status);
            $r++;
        }

        foreach (self::CONDITIONS as $i => $cond) {
            $sheet->getCell('G'.($i + 2))->setValue($cond);
        }

        foreach (self::STATUSES as $i => $stat) {
            $sheet->getCell('H'.($i + 2))->setValue($stat);
        }

        $this->addExampleBlock($sheet);

        $sheet->freezePane('A2');
    }

    /**
     * One filled-in row shown as column → value pairs, kept off the Assets sheet so it
     * can never be imported by accident.
     */
    private function addExampleBlock(Worksheet $sheet): void
    {
        $example = [
            'ast_pjctid' => 'PJ0012',
            'ast_type' => 'Laptop',
            'ast_brand' => 'HP',
            'ast_brandmodel' => 'Elitebook 840 G8',
            'ast_prodyear' => '2024',
            'ast_serial' => 'SGH828RTSL',
            'ast_vendid' => '',
            'ast_username' => 'Budi Santoso',
            // Region -> Lokasi -> Detail, mirroring how the legacy rows are filled
            // (e.g. BANDUNG / BDO Airport / Lt. 2).
            'ast_userreg' => 'BANDUNG',
            'ast_userloc' => 'BDO Airport',
            'ast_userlocdet' => 'Lt. 2',
            'ast_cond' => 'Good',
            'ast_delvdate' => '2024-01-15',
            'ast_purcdate' => '',
            'ast_stat' => 'Aktif',
            'ast_misc' => '',
        ];

        $sheet->getCell('J1')->setValue('Contoh pengisian');
        $sheet->getCell('K1')->setValue('Nilai');

        foreach (['J', 'K'] as $col) {
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $r = 2;
        foreach ($example as $col => $value) {
            $letter = Coordinate::stringFromColumnIndex(array_search($col, self::COLUMNS, true) + 1);
            $sheet->getCell('J'.$r)->setValue($letter.' — '.self::HEADERS[$col]);
            $sheet->getCell('K'.$r)->setValueExplicit((string) $value, DataType::TYPE_STRING);
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
     * Existing (project + serial) pairs mapped to the asset ID holding them.
     *
     * @return array<string, string>
     */
    private function existingKeys(): array
    {
        return AstMain::query()
            ->select('id', 'ast_pjctid', 'ast_serial')
            ->get()
            ->mapWithKeys(fn ($a) => [
                $this->dedupKey((string) $a->ast_pjctid, (string) $a->ast_serial) => $a->id,
            ])
            ->all();
    }

    private function dedupKey(string $pjctId, string $serial): string
    {
        return strtolower(trim($pjctId)).'|'.strtolower(trim($serial));
    }

    /**
     * Filler standing in for "no serial number".
     *
     * These carry no identity, so they must never reach the duplicate check — several
     * genuinely different assets all typed as `-` would otherwise collapse into one.
     */
    private function isPlaceholderSerial(string $serial): bool
    {
        if (preg_match('/^[-–—_.?*\/\\\\]+$/u', $serial)) {
            return true;
        }

        return in_array(strtoupper($serial), ['NA', 'N/A', 'NONE', 'TIDAK ADA', 'TIDAKADA', 'NO SERIAL', 'UNKNOWN'], true);
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
     * Build the insert payload. NOT NULL text columns fall back to '' the way the
     * legacy rows do; dates and year stay null when blank.
     *
     * @return array<string, mixed>
     */
    private function castRow(array $row, string $pjctId): array
    {
        $year = $row['ast_prodyear'] ?? null;
        $cond = trim((string) ($row['ast_cond'] ?? ''));

        $data = [
            'ast_pjctid' => $pjctId,
            'ast_prodyear' => ($year === null || $year === '') ? null : (int) $year,
            'ast_delvdate' => $this->toDate($row['ast_delvdate'] ?? null),
            'ast_purcdate' => $this->toDate($row['ast_purcdate'] ?? null),
            'ast_cond' => $cond === '' ? 'UNKNOWN' : $this->matchOption($cond, self::CONDITIONS),
            'ast_stat' => $this->matchOption(trim((string) ($row['ast_stat'] ?? '')), self::STATUSES),
            'ast_docid' => '',
        ];

        foreach (self::COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                continue;
            }
            $data[$col] = trim((string) ($row[$col] ?? ''));
        }

        foreach (self::NOT_NULL_TEXT as $col) {
            $data[$col] ??= '';
        }

        return $data;
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
