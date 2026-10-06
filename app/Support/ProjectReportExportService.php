<?php

namespace App\Support;

use App\Models\PjctMain;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Membangun workbook laporan untuk Report → Projects.
 *
 * Dua sheet: `Data Project` (kop laporan + seluruh kolom `pjct_main`) dan `Ringkasan`
 * (rekap jumlah & nilai per status/divisi/type/client + jumlah data yang masih kosong).
 * Sel nilai/tanggal/nomor kontrak yang kosong diberi warna supaya mudah dikonfirmasi.
 */
class ProjectReportExportService
{
    /** Judul kolom + lebarnya, urut sesuai `rowValues()`. */
    private const COLUMNS = [
        ['ID', 10],
        ['No Kontrak', 30],
        ['Tgl Kontrak', 12],
        ['Divisi', 10],
        ['Nama Project', 50],
        ['Type', 9],
        ['Client', 26],
        ['Area', 16],
        ['Nilai Kontrak (Rp)', 18],
        ['Mulai', 12],
        ['Durasi (bln)', 10],
        ['Selesai', 12],
        ['Status', 12],
        ['Jml Asset', 9],
        ['Catatan', 50],
        ['Diarsipkan', 12],
    ];

    /** Kolom (huruf) yang ditandai kalau kosong: No Kontrak, Nilai, Mulai, Selesai. */
    private const GAP_COLUMNS = ['B', 'I', 'J', 'L'];

    /** Baris pertama data — di atasnya kop laporan. */
    private const HEADER_ROW = 6;

    private const TEAL = '0F766E';

    private const SLATE = 'F1F5F9';

    private const AMBER = 'FEF3C7';

    /**
     * @param  iterable<int, PjctMain>  $projects  hasil query yang sudah difilter & di-sort
     * @param  array<int, string>  $filterLabels  filter aktif, untuk dicetak di kop
     * @param  array<string, Collection<string, array{count: int, value: int}>>  $breakdowns
     * @param  array<string, int>  $gaps  jumlah project per jenis data kosong
     */
    public function make(
        iterable $projects,
        array $filterLabels,
        array $breakdowns,
        array $gaps,
        int $totalRows,
        int $totalValue,
        string $generatedBy
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('PRISM')
            ->setTitle('Laporan Data Project')
            ->setSubject('Report — Data Project');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Project');

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::COLUMNS));

        $this->writeReportHead($sheet, $lastColumn, $filterLabels, $totalRows, $totalValue, $generatedBy);
        $this->writeColumnHeader($sheet, $lastColumn);

        $row = self::HEADER_ROW + 1;
        $gapCells = [];
        foreach ($projects as $project) {
            $gapCells = array_merge($gapCells, $this->writeRow($sheet, $row++, $project));
        }

        $lastRow = $row - 1;
        $this->finishDataSheet($sheet, $lastColumn, $lastRow, $gapCells);
        $this->addSummarySheet($spreadsheet, $breakdowns, $gaps, $filterLabels, $totalRows, $totalValue, $generatedBy);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** Kop laporan: judul, waktu cetak, siapa yang menarik, filter yang dipakai. */
    private function writeReportHead(
        Worksheet $sheet,
        string $lastColumn,
        array $filterLabels,
        int $totalRows,
        int $totalValue,
        string $generatedBy
    ): void {
        $sheet->setCellValue('A1', 'LAPORAN DATA PROJECT');
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0F172A']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', 'PRISM — Equipment & Technology');
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '64748B']],
        ]);

        $sheet->setCellValue('A3', sprintf(
            'Dicetak: %s  ·  Oleh: %s  ·  Jumlah project: %s  ·  Total nilai kontrak: Rp %s  ·  Sel kuning = data kosong',
            now()->format('d M Y H:i'),
            $generatedBy,
            number_format($totalRows),
            number_format($totalValue, 0, ',', '.')
        ));
        $sheet->mergeCells("A3:{$lastColumn}3");

        $sheet->setCellValue('A4', 'Filter: '.($filterLabels ? implode('  ·  ', $filterLabels) : 'Tidak ada (semua data)'));
        $sheet->mergeCells("A4:{$lastColumn}4");

        $sheet->getStyle('A3:A4')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '475569']],
        ]);
    }

    private function writeColumnHeader(Worksheet $sheet, string $lastColumn): void
    {
        foreach (self::COLUMNS as $i => [$label, $width]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($letter.self::HEADER_ROW, $label);
            $sheet->getColumnDimension($letter)->setWidth($width);
        }

        $range = 'A'.self::HEADER_ROW.":{$lastColumn}".self::HEADER_ROW;
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(20);
    }

    /**
     * @return array<int, string> sel kosong yang perlu ditandai, mis. `I12`
     */
    private function writeRow(Worksheet $sheet, int $row, PjctMain $project): array
    {
        $gapCells = [];

        foreach ($this->rowValues($project) as $i => $value) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);

            if (in_array($letter, self::GAP_COLUMNS, true) && ($value === null || $value === '' || $value === 0)) {
                $gapCells[] = $letter.$row;
            }

            // Format tanggal/angka dipasang sekali per kolom di `finishDataSheet()` —
            // memanggil getStyle() per sel bikin export lambat.
            if ($value instanceof \DateTimeInterface) {
                $sheet->setCellValue($letter.$row, ExcelDate::PHPToExcel($value));

                continue;
            }

            // ID & nomor kontrak dipaksa teks supaya Excel tidak mengubah formatnya.
            in_array($i, [0, 1], true)
                ? $sheet->setCellValueExplicit($letter.$row, (string) $value, DataType::TYPE_STRING)
                : $sheet->setCellValue($letter.$row, $value);
        }

        return $gapCells;
    }

    /** @return array<int, string|int|\DateTimeInterface|null> */
    private function rowValues(PjctMain $project): array
    {
        return [
            $project->id,
            $project->pjct_contract,
            $project->pjct_codate,
            $project->pjct_div,
            $project->pjct_name,
            $project->pjct_type,
            $project->pjct_client,
            $project->pjct_area,
            $project->pjct_value ? (int) $project->pjct_value : null,
            $project->pjct_costart,
            $project->pjct_totalperiod ? (int) $project->pjct_totalperiod : null,
            $project->pjct_coend_m,
            $project->statusLabel(),
            (int) ($project->assets_count ?? 0),
            $project->pjct_misc,
            $project->deleted_at,
        ];
    }

    /** @param  array<int, string>  $gapCells */
    private function finishDataSheet(Worksheet $sheet, string $lastColumn, int $lastRow, array $gapCells): void
    {
        $headerRow = self::HEADER_ROW;
        $firstDataRow = $headerRow + 1;

        if ($lastRow >= $firstDataRow) {
            // Tgl Kontrak (C), Mulai (J), Selesai (L), Diarsipkan (P) diformat sekaligus per kolom.
            foreach (['C', 'J', 'L', 'P'] as $letter) {
                $sheet->getStyle("{$letter}{$firstDataRow}:{$letter}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            }
            $sheet->getStyle("I{$firstDataRow}:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("E{$firstDataRow}:E{$lastRow}")->getAlignment()->setWrapText(true);

            foreach ($gapCells as $cell) {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AMBER);
            }

            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::TEAL]]],
            ]);
        }

        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}".max($lastRow, $headerRow));
        $sheet->freezePane('C'.($headerRow + 1));
        $sheet->setSelectedCell('A'.($headerRow + 1));

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
    }

    /**
     * @param  array<string, Collection<string, array{count: int, value: int}>>  $breakdowns
     * @param  array<string, int>  $gaps
     */
    private function addSummarySheet(
        Spreadsheet $spreadsheet,
        array $breakdowns,
        array $gaps,
        array $filterLabels,
        int $totalRows,
        int $totalValue,
        string $generatedBy
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan');

        $sheet->setCellValue('A1', 'RINGKASAN DATA PROJECT');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
        ]);

        $sheet->setCellValue('A2', sprintf('Dicetak: %s · Oleh: %s', now()->format('d M Y H:i'), $generatedBy));
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A3', 'Filter: '.($filterLabels ? implode(' · ', $filterLabels) : 'Tidak ada (semua data)'));
        $sheet->mergeCells('A3:D3');
        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '475569']],
        ]);

        $sheet->setCellValue('A5', 'Total project');
        $sheet->setCellValue('B5', $totalRows);
        $sheet->setCellValue('A6', 'Total nilai kontrak (Rp)');
        $sheet->setCellValue('B6', $totalValue);
        $sheet->getStyle('B6')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A5:B6')->applyFromArray(['font' => ['bold' => true]]);

        $row = 8;

        // Kelengkapan data — yang paling sering dicari dari laporan ini.
        $sheet->setCellValue('A'.$row, 'KELENGKAPAN DATA');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle('A'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AMBER]],
        ]);
        $row++;
        foreach ($gaps as $label => $count) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $count);
            $sheet->setCellValue('C'.$row, $totalRows ? round($count / $totalRows * 100, 1).'%' : '-');
            $row++;
        }
        $row++;

        foreach ($breakdowns as $title => $rows) {
            $sheet->setCellValue('A'.$row, strtoupper($title));
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->getStyle('A'.$row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SLATE]],
            ]);
            $row++;

            foreach (['A' => 'Nilai', 'B' => 'Jumlah', 'C' => '% dari total', 'D' => 'Nilai Kontrak (Rp)'] as $letter => $heading) {
                $sheet->setCellValue($letter.$row, $heading);
            }
            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
            ]);
            $row++;

            $first = $row;
            foreach ($rows as $label => $data) {
                $sheet->setCellValue('A'.$row, $label === '' ? '(kosong)' : $label);
                $sheet->setCellValue('B'.$row, $data['count']);
                $sheet->setCellValue('C'.$row, $totalRows ? round($data['count'] / $totalRows * 100, 1).'%' : '-');
                $sheet->setCellValue('D'.$row, $data['value']);
                $row++;
            }
            if ($row > $first) {
                $sheet->getStyle("D{$first}:D".($row - 1))->getNumberFormat()->setFormatCode('#,##0');
            }

            $row++;
        }

        foreach (['A' => 34, 'B' => 12, 'C' => 14, 'D' => 22] as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
    }
}
