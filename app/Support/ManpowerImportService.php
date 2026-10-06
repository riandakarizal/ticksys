<?php

namespace App\Support;

use App\Models\PjctEmp;
use App\Models\PjctMain;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bulk insert for `pjct_emp` (manpower per project) from an xlsx sheet — the same
 * parse → classify → preview → execute shape as AssetImportService.
 *
 * Insert-only: a row whose (emp_pjctid + emp_id/NIK) pair already exists is skipped,
 * never updated. One person may still be listed on several projects.
 */
class ManpowerImportService
{
    public const SHEET_NAME = 'Manpower';

    /** Rows pre-formatted as text (NIK, No. PKWT, Kontak) — well past a realistic upload. */
    private const TEXT_ROWS = 2000;

    /** Template column order — index maps 1:1 to spreadsheet column A, B, C… */
    private const COLUMNS = [
        'emp_pjctid', 'emp_id', 'emp_name', 'emp_level', 'emp_levname', 'emp_unit',
        'emp_div', 'emp_area', 'emp_coid', 'emp_costart', 'emp_coend', 'emp_contact', 'emp_misc',
    ];

    /**
     * Headers follow the labels on the Manpower page, not the column names: `emp_div`
     * is shown there as "Site / Area" and `emp_area` as "Region".
     */
    private const HEADERS = [
        'emp_pjctid' => 'Project ID *',
        'emp_id' => 'NIK *',
        'emp_name' => 'Nama *',
        'emp_level' => 'Level',
        'emp_levname' => 'Jabatan *',
        'emp_unit' => 'Unit',
        'emp_div' => 'Site / Area',
        'emp_area' => 'Region',
        'emp_coid' => 'No. PKWT',
        'emp_costart' => 'Mulai PKWT (YYYY-MM-DD)',
        'emp_coend' => 'Selesai PKWT (YYYY-MM-DD)',
        'emp_contact' => 'Kontak',
        'emp_misc' => 'Catatan',
    ];

    /** Column widths in the legacy schema — longer text would be rejected by MySQL. */
    private const MAX_LENGTH = [
        'emp_id' => 20, 'emp_name' => 225, 'emp_level' => 20, 'emp_levname' => 20,
        'emp_unit' => 20, 'emp_div' => 20, 'emp_area' => 225, 'emp_coid' => 225, 'emp_contact' => 225,
    ];

    /** NOT NULL text columns with no default — blank becomes '' like the legacy rows. */
    private const NOT_NULL_TEXT = [
        'emp_id', 'emp_name', 'emp_level', 'emp_levname', 'emp_unit', 'emp_div', 'emp_area', 'emp_coid',
    ];

    // ─── Parse ─────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function parse(string $path): array
    {
        // Data only: the template's text formatting on thousands of rows would otherwise be
        // loaded too and push a one-row file to ~80 MB — over PHP's default 128 MB on confirm.
        // Date cells then arrive as Excel serials, which toDate() already handles.
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
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

    /** Trim a cell; treat database-export placeholders (`NULL`, `\N`, `#N/A`) as blank. */
    private function normaliseCell(mixed $value): mixed
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            // NIK typed into a General cell arrives as a float — keep the digits, drop ".0".
            return $value;
        }

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

        foreach ($rows as $row) {
            $line = $row['_line'] ?? 0;
            $errors = [];

            $pjctIdRaw = $this->text($row['emp_pjctid'] ?? '');
            $pjctId = $projectIds[strtolower($pjctIdRaw)] ?? null;
            if ($pjctIdRaw === '') {
                $errors[] = 'Project ID wajib diisi';
            } elseif (! $pjctId) {
                $errors[] = "Project ID \"{$pjctIdRaw}\" tidak ada di daftar project";
            }

            $nik = $this->text($row['emp_id'] ?? '');
            if ($nik === '') {
                $errors[] = 'NIK wajib diisi';
            }

            if ($this->text($row['emp_name'] ?? '') === '') {
                $errors[] = 'Nama wajib diisi';
            }

            if ($this->text($row['emp_levname'] ?? '') === '') {
                $errors[] = 'Jabatan wajib diisi';
            }

            foreach (self::MAX_LENGTH as $col => $max) {
                $len = mb_strlen($this->text($row[$col] ?? ''));
                if ($len > $max) {
                    $errors[] = self::label($col)." terlalu panjang ({$len} karakter, maks. {$max})";
                }
            }

            $start = $end = null;
            foreach (['emp_costart' => 'Mulai PKWT', 'emp_coend' => 'Selesai PKWT'] as $col => $label) {
                $raw = $row[$col] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $date = $this->toDate($raw);
                if ($date === null) {
                    $errors[] = "{$label} tidak bisa dibaca (pakai YYYY-MM-DD)";
                } elseif ($col === 'emp_costart') {
                    $start = $date;
                } else {
                    $end = $date;
                }
            }
            if ($start && $end && $end < $start) {
                $errors[] = "Selesai PKWT ({$end}) lebih awal dari Mulai PKWT ({$start})";
            }

            $key = $errors ? null : $this->dedupKey($pjctId, $nik);
            $prepared[] = [
                'line' => $line,
                'row' => $row,
                'errors' => $errors,
                'key' => $key,
                'data' => $errors ? [] : $this->castRow($row, $pjctId),
                'existing' => $key ? ($existing[$key] ?? null) : null,
            ];
        }

        // Valid, not-yet-existing rows sharing (project + NIK) — only visible once the whole file is read.
        $groups = [];
        foreach ($prepared as $i => $p) {
            if (! $p['errors'] && ! $p['existing']) {
                $groups[$p['key']][] = $i;
            }
        }

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
                    'message' => "NIK sudah terdaftar di {$p['data']['emp_pjctid']} ({$p['existing']})",
                ];

                continue;
            }

            $group = $groups[$p['key']];

            if (count($group) > 1) {
                $others = array_values(array_diff(array_map(fn ($j) => $prepared[$j]['line'], $group), [$p['line']]));

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
                        'message' => 'NIK kembar dengan baris '.$this->joinLines($others).' di project yang sama tapi isinya berbeda — periksa mana yang benar',
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

    /** @return array{new: int, skip: int, error: int, total: int} */
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
            // Reserved up front: mass insert bypasses the model's `creating` hook.
            $ids = PjctEmp::nextIds(count($pending));
            $rows = [];

            foreach ($pending as $i => $item) {
                $rows[] = ['id' => $ids[$i]] + $item['data'];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                PjctEmp::insert($chunk);
            }
        });

        // One log line per import batch — `insert()` skips the model events that log single rows.
        SystemLog::create([
            'user_id' => $user->id,
            'action' => 'import',
            'loggable_type' => 'PjctEmp',
            'loggable_id' => count($ids) === 1 ? $ids[0] : $ids[0].'..'.end($ids),
            'description' => sprintf('Import %d manpower dari Excel', count($ids)),
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

        // NIK and PKWT numbers are digit strings — keep them as text so Excel neither
        // rounds a 16-digit NIK nor shows it in scientific notation.
        foreach (['B', 'I', 'L'] as $col) {
            $sheet->getStyle($col.'2:'.$col.self::TEXT_ROWS)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // The example lives on the Referensi sheet — a sample row left here would be imported.
        $this->addReferenceSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
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

    private function addReferenceSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Referensi');

        foreach (['A' => 'Project ID', 'B' => 'No. Kontrak', 'C' => 'Nama Project', 'D' => 'Divisi', 'E' => 'Status'] as $col => $title) {
            $sheet->getCell($col.'1')->setValue($title);
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $r = 2;
        foreach (PjctMain::orderBy('id')->get(['id', 'pjct_contract', 'pjct_name', 'pjct_div', 'pjct_status']) as $p) {
            $sheet->getCell('A'.$r)->setValue($p->id);
            $sheet->getCell('B'.$r)->setValueExplicit((string) $p->pjct_contract, DataType::TYPE_STRING);
            $sheet->getCell('C'.$r)->setValue($p->pjct_name);
            $sheet->getCell('D'.$r)->setValue($p->pjct_div);
            $sheet->getCell('E'.$r)->setValue($p->pjct_status);
            $r++;
        }

        $this->addExampleBlock($sheet);
        $sheet->freezePane('A2');
    }

    /** One filled-in row shown as column → value pairs, off the Manpower sheet so it is never imported. */
    private function addExampleBlock(Worksheet $sheet): void
    {
        $example = [
            'emp_pjctid' => 'PJ0003',
            'emp_id' => '241002011691',
            'emp_name' => 'Suryadi',
            'emp_level' => 'L7.1',
            'emp_levname' => 'Koordinator Teknisi',
            'emp_unit' => 'Regional 2',
            'emp_div' => 'Perkantoran 601 CGK',
            'emp_area' => 'Tangerang',
            'emp_coid' => 'IASS/HR/PKWT/XII/2024/00/1680.62',
            'emp_costart' => '2025-01-01',
            'emp_coend' => '2025-12-31',
            'emp_contact' => '089680888752',
            'emp_misc' => '',
        ];

        foreach (['G' => 'Contoh pengisian', 'H' => 'Nilai'] as $col => $title) {
            $sheet->getCell($col.'1')->setValue($title);
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

        $sheet->getCell('G'.($r + 1))->setValue('Jabatan yang sudah dipakai: Teknisi, Koordinator Teknisi, Admin (boleh isi lain, maks. 20 karakter).');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /** @return array<string, string> existing (project + NIK) pairs mapped to the row ID holding them */
    private function existingKeys(): array
    {
        return PjctEmp::query()
            ->select('id', 'emp_pjctid', 'emp_id')
            ->get()
            ->mapWithKeys(fn ($e) => [$this->dedupKey((string) $e->emp_pjctid, (string) $e->emp_id) => $e->id])
            ->all();
    }

    private function dedupKey(string $pjctId, string $nik): string
    {
        return strtolower(trim($pjctId)).'|'.strtolower(trim($nik));
    }

    /** Cell as trimmed text; whole-number floats (NIK in a General cell) lose their ".0". */
    private function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return number_format($value, 0, '', '');
        }

        return trim((string) ($value ?? ''));
    }

    private static function label(string $col): string
    {
        return rtrim(preg_replace('/\s*\(.*\)|\s*\*$/', '', self::HEADERS[$col]));
    }

    private function joinLines(array $lines): string
    {
        if (count($lines) <= 1) {
            return (string) ($lines[0] ?? '');
        }

        $last = array_pop($lines);

        return implode(', ', $lines).' dan '.$last;
    }

    /** @return array<string, mixed> */
    private function castRow(array $row, string $pjctId): array
    {
        $data = [
            'emp_pjctid' => $pjctId,
            'emp_costart' => $this->toDate($row['emp_costart'] ?? null),
            'emp_coend' => $this->toDate($row['emp_coend'] ?? null),
        ];

        foreach (['emp_id', 'emp_name', 'emp_level', 'emp_levname', 'emp_unit', 'emp_div', 'emp_area', 'emp_coid'] as $col) {
            $data[$col] = $this->text($row[$col] ?? '');
        }

        foreach (['emp_contact', 'emp_misc'] as $col) {
            $value = $this->text($row[$col] ?? '');
            $data[$col] = $value === '' ? null : $value;
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
