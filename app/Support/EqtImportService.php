<?php

namespace App\Support;

use App\Models\EqtChangeLog;
use App\Models\EqtHandover;
use App\Models\EqtMaintenance;
use App\Models\EqtProject;
use App\Models\EqtVehicle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EqtImportService
{
    // ─── Sheet definitions ─────────────────────────────────────────────────

    private const SHEETS = [
        'Projects EQ'   => ['type' => 'project_eq',   'model' => EqtProject::class],
        'Projects TECH' => ['type' => 'project_tech',  'model' => EqtProject::class],
        'Hand Over'     => ['type' => 'handover',      'model' => EqtHandover::class],
        'Kendaraan'     => ['type' => 'vehicle',       'model' => EqtVehicle::class],
        'Maintenance'   => ['type' => 'maintenance',   'model' => EqtMaintenance::class],
    ];

    private const COLUMNS = [
        'project_eq' => [
            'tahun', 'nama', 'lob', 'pemberi_kerja', 'area', 'mitra',
            'nilai_pekerjaan', 'nilai_mitra', 'serapan',
            'start_date', 'end_date', 'no_kontrak', 'docs', 'status', 'keterangan',
        ],
        'project_tech' => [
            'tahun', 'nama', 'lob', 'pemberi_kerja', 'area', 'mitra',
            'nilai_pekerjaan', 'nilai_mitra', 'serapan',
            'start_date', 'end_date', 'no_kontrak', 'docs', 'status', 'keterangan',
        ],
        'handover' => [
            'tahun', 'nama', 'lob', 'pemberi_kerja', 'area', 'mitra', 'status', 'keterangan',
        ],
        'vehicle' => [
            'nopol', 'jenis', 'customer', 'vendor', 'no_kontrak',
            'pkb_date', 'nilai_pkb', 'status_pajak', 'keterangan',
        ],
        // urutan sesuai tampilan tabel: nama_alat, tipe_alat, area, mitra, ...
        'maintenance' => [
            'nama_alat', 'tipe_alat', 'area', 'mitra', 'no_kontrak',
            'last_service', 'next_service', 'status_maint', 'keterangan',
        ],
    ];

    private const HEADERS = [
        // project (EQ & TECH)
        'tahun'          => 'Tahun',
        'nama'           => 'Nama Pekerjaan',
        'lob'            => 'LOB',
        'pemberi_kerja'  => 'Pemberi Kerja',
        'area'           => 'Area',
        'mitra'          => 'Mitra',
        'nilai_pekerjaan'=> 'Nilai Pekerjaan (Rp)',
        'nilai_mitra'    => 'Nilai Mitra (Rp)',
        'serapan'        => 'Serapan Opex (Rp)',
        'start_date'     => 'Tanggal Mulai',
        'end_date'       => 'Tanggal Selesai',
        'no_kontrak'     => 'No Kontrak',
        'docs'           => 'Dokumen (JSON)',
        'status'         => 'Status',
        'keterangan'     => 'Keterangan',
        // vehicle
        'nopol'          => 'No Polisi',
        'jenis'          => 'Jenis Kendaraan',
        'customer'       => 'Customer / User',
        'vendor'         => 'Vendor',
        'pkb_date'       => 'PKB s/d',
        'nilai_pkb'      => 'Nilai PKB (Rp)',
        'status_pajak'   => 'Status Pajak',
        // maintenance
        'nama_alat'      => 'Nama Alat / Kendaraan',
        'tipe_alat'      => 'Tipe Alat',
        'last_service'   => 'Terakhir Service',
        'next_service'   => 'Jadwal Berikutnya',
        'status_maint'   => 'Status Maintenance',
    ];

    // ─── Parse ─────────────────────────────────────────────────────────────

    public function parse(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $result = [];

        foreach (self::SHEETS as $sheetName => $meta) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) continue;

            $cols = self::COLUMNS[$meta['type']];
            $rows = [];
            $highestRow = $sheet->getHighestDataRow();

            for ($r = 2; $r <= $highestRow; $r++) {
                $row = [];
                foreach ($cols as $i => $col) {
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $r);
                    $val  = $cell->getValue();
                    $row[$col] = is_string($val) ? trim($val) : $val;
                }
                // Skip entirely blank rows
                if (collect($row)->every(fn($v) => $v === null || $v === '')) continue;

                // Attach type for project sheets
                if (in_array($meta['type'], ['project_eq', 'project_tech'])) {
                    $row['type'] = $meta['type'] === 'project_eq' ? 'EQ' : 'TECH';
                }
                $rows[] = $row;
            }

            $result[$meta['type']] = $rows;
        }

        return $result;
    }

    // ─── Diff ──────────────────────────────────────────────────────────────

    public function diff(array $parsed): array
    {
        $result = [];

        foreach ($parsed as $type => $rows) {
            $existing = $this->loadExisting($type);
            $classified = [];

            foreach ($rows as $row) {
                $key = $this->matchKey($type, $row);
                $record = $key ? ($existing[$key] ?? null) : null;

                if (!$record) {
                    $classified[] = ['status' => 'new', 'row' => $row, 'db' => null, 'id' => null];
                } else {
                    $diff = $this->changedFields($row, $record);
                    if (empty($diff)) {
                        $classified[] = ['status' => 'unchanged', 'row' => $row, 'db' => $record, 'id' => $record['id']];
                    } else {
                        $classified[] = ['status' => 'update', 'row' => $row, 'db' => $record, 'id' => $record['id'], 'diff' => $diff];
                    }
                }
            }

            $result[$type] = $classified;
        }

        return $result;
    }

    // ─── Execute ───────────────────────────────────────────────────────────

    public function execute(array $parsed, User $user): array
    {
        $diff    = $this->diff($parsed);
        $summary = ['new' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($diff as $type => $rows) {
            $modelClass = $this->modelClass($type);

            foreach ($rows as $item) {
                if ($item['status'] === 'unchanged') {
                    $summary['unchanged']++;
                    continue;
                }

                $data  = $this->castRow($type, $item['row']);
                $label = $this->rowLabel($type, $item['row']);

                if ($item['status'] === 'new') {
                    $record = $modelClass::create($data);
                    EqtChangeLog::record($user, $this->logType($type), $record->id, $label, 'imported', null, $data, 'import');
                    $summary['new']++;
                } elseif ($item['status'] === 'update') {
                    $record = $modelClass::findOrFail($item['id']);
                    $old = $record->only(array_keys($item['diff']));
                    $record->update($data);
                    EqtChangeLog::record($user, $this->logType($type), $record->id, $label, 'imported', $old, $item['diff'], 'import');
                    $summary['updated']++;
                }
            }
        }

        return $summary;
    }

    // ─── Template & Export ─────────────────────────────────────────────────

    private const TYPE_TO_SHEET = [
        'project_eq'   => 'Projects EQ',
        'project_tech' => 'Projects TECH',
        'handover'     => 'Hand Over',
        'vehicle'      => 'Kendaraan',
        'maintenance'  => 'Maintenance',
    ];

    public function makeTemplateForType(string $type): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::TYPE_TO_SHEET[$type] ?? $type);
        $this->writeHeader($sheet, self::COLUMNS[$type]);
        return $spreadsheet;
    }

    public function makeTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $index = 0;

        foreach (self::SHEETS as $sheetName => $meta) {
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($sheet, $index++);
            $cols = self::COLUMNS[$meta['type']];
            $this->writeHeader($sheet, $cols);
        }

        return $spreadsheet;
    }

    public function makeExport(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $index = 0;

        $typeData = [
            'project_eq'   => EqtProject::where('type', 'EQ')->get()->toArray(),
            'project_tech' => EqtProject::where('type', 'TECH')->get()->toArray(),
            'handover'     => EqtHandover::all()->toArray(),
            'vehicle'      => EqtVehicle::all()->toArray(),
            'maintenance'  => EqtMaintenance::all()->toArray(),
        ];

        foreach (self::SHEETS as $sheetName => $meta) {
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($sheet, $index++);
            $cols = self::COLUMNS[$meta['type']];
            $this->writeHeader($sheet, $cols);

            foreach ($typeData[$meta['type']] as $row => $record) {
                foreach ($cols as $col => $field) {
                    $value = $record[$field] ?? '';
                    if (is_array($value)) $value = $value ? json_encode($value) : '';
                    $sheet->getCell(Coordinate::stringFromColumnIndex($col + 1) . ($row + 2))->setValue($value);
                }
            }
        }

        return $spreadsheet;
    }

    // ─── Internals ─────────────────────────────────────────────────────────

    private function loadExisting(string $type): array
    {
        $records = match ($type) {
            'project_eq'   => EqtProject::where('type', 'EQ')->get(),
            'project_tech' => EqtProject::where('type', 'TECH')->get(),
            'handover'     => EqtHandover::all(),
            'vehicle'      => EqtVehicle::all(),
            'maintenance'  => EqtMaintenance::all(),
        };

        $indexed = [];
        foreach ($records as $rec) {
            $key = $this->matchKeyFromRecord($type, $rec->toArray());
            if ($key) $indexed[$key] = $rec->toArray();
        }
        return $indexed;
    }

    private function matchKey(string $type, array $row): ?string
    {
        return match ($type) {
            'project_eq', 'project_tech' => $this->projectKey($row),
            'vehicle'      => strtoupper(trim($row['nopol'] ?? '')),
            'maintenance'  => strtolower(trim($row['nama_alat'] ?? '')) . '|' . strtolower(trim($row['area'] ?? '')),
            'handover'     => strtolower(trim($row['nama'] ?? '')) . '|' . ($row['tahun'] ?? ''),
            default        => null,
        };
    }

    private function matchKeyFromRecord(string $type, array $rec): ?string
    {
        return $this->matchKey($type, $rec);
    }

    private function projectKey(array $row): ?string
    {
        $nk = trim($row['no_kontrak'] ?? '');
        if ($nk && $nk !== '-' && $nk !== '') {
            return 'nk|' . strtolower($nk);
        }
        // fallback: nama+tahun+type (no_kontrak absent)
        return 'nm|' . strtolower(trim($row['nama'] ?? '')) . '|' . ($row['tahun'] ?? '') . '|' . ($row['type'] ?? '');
    }

    private function changedFields(array $incoming, array $existing): array
    {
        $normalise = function ($v) {
            if (is_array($v)) return $v ? json_encode($v) : null;
            return ($v === '' || $v === null) ? null : $v;
        };

        $changed = [];
        foreach ($incoming as $field => $value) {
            if (in_array($field, ['id', 'type', 'created_at', 'updated_at', 'deleted_at'])) continue;
            $existVal = $existing[$field] ?? null;
            if ((string) $normalise($value) !== (string) $normalise($existVal)) {
                $changed[$field] = $value;
            }
        }
        return $changed;
    }

    private function castRow(string $type, array $row): array
    {
        $intFields  = ['tahun', 'nilai_pekerjaan', 'nilai_mitra', 'serapan', 'nilai_pkb'];
        $jsonFields = ['docs'];
        $data = [];
        $cols = self::COLUMNS[$type];
        foreach ($cols as $col) {
            $val = $row[$col] ?? null;
            if (in_array($col, $intFields)) {
                $val = $val !== null && $val !== '' ? (int) $val : null;
            } elseif (in_array($col, $jsonFields)) {
                $val = (is_string($val) && $val !== '') ? json_decode($val, true) : (is_array($val) ? $val : null);
            }
            $data[$col] = $val;
        }
        if (isset($row['type'])) $data['type'] = $row['type'];
        return $data;
    }

    private function rowLabel(string $type, array $row): string
    {
        return match ($type) {
            'project_eq', 'project_tech' => ($row['nama'] ?? '') . ' (' . ($row['tahun'] ?? '') . ')',
            'vehicle'     => $row['nopol'] ?? '',
            'maintenance' => ($row['nama_alat'] ?? '') . ' - ' . ($row['area'] ?? ''),
            'handover'    => ($row['nama'] ?? '') . ' (' . ($row['tahun'] ?? '') . ')',
            default       => '',
        };
    }

    private function logType(string $type): string
    {
        return match ($type) {
            'project_eq', 'project_tech' => 'project',
            default => $type,
        };
    }

    private function modelClass(string $type): string
    {
        return match ($type) {
            'project_eq', 'project_tech' => EqtProject::class,
            'handover'    => EqtHandover::class,
            'vehicle'     => EqtVehicle::class,
            'maintenance' => EqtMaintenance::class,
        };
    }

    private function writeHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $cols): void
    {
        foreach ($cols as $i => $col) {
            $coord  = Coordinate::stringFromColumnIndex($i + 1) . '1';
            $label  = self::HEADERS[$col] ?? strtoupper($col);
            $sheet->getCell($coord)->setValue($label);
            $sheet->getStyle($coord)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e40af']],
            ]);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }
}
