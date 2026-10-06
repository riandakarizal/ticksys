<?php

namespace App\Support;

use App\Models\AstMain;
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
 * Membangun workbook laporan untuk Report → Data.
 *
 * Dua sheet: `Data Asset` (kop laporan + seluruh kolom `ast_main`) dan `Ringkasan`
 * (rekap per status/kondisi/type/region dari baris yang sama).
 */
class AssetReportExportService
{
    /** Judul kolom + lebarnya, urut sesuai `rowValues()`. */
    private const COLUMNS = [
        ['ID', 14],
        ['Type', 20],
        ['Brand', 16],
        ['Model', 26],
        ['Tahun', 8],
        ['Serial', 20],
        ['Vendor', 22],
        ['User', 22],
        ['Region', 16],
        ['Lokasi', 24],
        ['Detail Lokasi', 24],
        ['Kondisi', 12],
        ['Tgl Kirim', 12],
        ['Tgl Beli', 12],
        ['Status', 15],
        ['Project ID', 12],
        ['Project Name', 38],
        ['Doc ID', 14],
        ['Catatan', 30],
    ];

    /** Baris pertama data — di atasnya kop laporan. */
    private const HEADER_ROW = 6;

    private const TEAL = '0F766E';

    private const SLATE = 'F1F5F9';

    /**
     * @param  iterable<int, AstMain>  $assets  hasil query yang sudah difilter & di-sort
     * @param  array<int, string>  $filterLabels  filter aktif, untuk dicetak di kop
     * @param  array<string, Collection<string, int>>  $breakdowns  rekap untuk sheet Ringkasan
     * @param  Collection<string, string>  $projectNames
     */
    public function make(
        iterable $assets,
        array $filterLabels,
        array $breakdowns,
        Collection $projectNames,
        int $totalRows,
        string $generatedBy
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('PRISM')
            ->setTitle('Laporan Data Aset')
            ->setSubject('Report — Data Asset');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Asset');

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::COLUMNS));

        $this->writeReportHead($sheet, $lastColumn, $filterLabels, $totalRows, $generatedBy);
        $this->writeColumnHeader($sheet, $lastColumn);

        $row = self::HEADER_ROW + 1;
        foreach ($assets as $asset) {
            $this->writeRow($sheet, $row++, $asset, $projectNames);
        }

        $lastRow = $row - 1;
        $this->finishDataSheet($sheet, $lastColumn, $lastRow);
        $this->addSummarySheet($spreadsheet, $breakdowns, $filterLabels, $totalRows, $generatedBy);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** Kop laporan: judul, waktu cetak, siapa yang menarik, filter yang dipakai. */
    private function writeReportHead(
        Worksheet $sheet,
        string $lastColumn,
        array $filterLabels,
        int $totalRows,
        string $generatedBy
    ): void {
        $sheet->setCellValue('A1', 'LAPORAN DATA ASET');
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
            'Dicetak: %s  ·  Oleh: %s  ·  Jumlah baris: %s',
            now()->format('d M Y H:i'),
            $generatedBy,
            number_format($totalRows)
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

    /** @param  Collection<string, string>  $projectNames */
    private function writeRow(Worksheet $sheet, int $row, AstMain $asset, Collection $projectNames): void
    {
        foreach ($this->rowValues($asset, $projectNames) as $i => $value) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);

            // Format tanggalnya dipasang sekali per kolom di `finishDataSheet()` —
            // memanggil getStyle() per sel bikin export 3.500 baris jadi lambat sekali.
            if ($value instanceof \DateTimeInterface) {
                $sheet->setCellValue($letter.$row, ExcelDate::PHPToExcel($value));

                continue;
            }

            // Serial/ID sering berupa angka panjang atau berawalan 0 — paksa jadi teks
            // supaya Excel tidak mengubahnya jadi notasi ilmiah atau memotong nol depan.
            in_array($i, [0, 5], true)
                ? $sheet->setCellValueExplicit($letter.$row, (string) $value, DataType::TYPE_STRING)
                : $sheet->setCellValue($letter.$row, $value);
        }
    }

    /**
     * @param  Collection<string, string>  $projectNames
     * @return array<int, string|int|\DateTimeInterface|null>
     */
    private function rowValues(AstMain $asset, Collection $projectNames): array
    {
        return [
            $asset->id,
            $asset->ast_type,
            $asset->ast_brand,
            $asset->ast_brandmodel,
            $asset->ast_prodyear ? (int) $asset->ast_prodyear : null,
            $asset->ast_serial,
            $asset->ast_vendid,
            $asset->ast_username,
            $asset->ast_userreg,
            $asset->ast_userloc,
            $asset->ast_userlocdet,
            $asset->ast_cond,
            $asset->ast_delvdate,
            $asset->ast_purcdate,
            $asset->ast_stat,
            $asset->ast_pjctid,
            $asset->ast_pjctid ? ($projectNames[$asset->ast_pjctid] ?? null) : null,
            $asset->ast_docid,
            $asset->ast_misc,
        ];
    }

    private function finishDataSheet(Worksheet $sheet, string $lastColumn, int $lastRow): void
    {
        $headerRow = self::HEADER_ROW;
        $firstDataRow = $headerRow + 1;

        if ($lastRow >= $firstDataRow) {
            // Kolom Tgl Kirim (M) & Tgl Beli (N) diformat sekaligus per kolom.
            foreach (['M', 'N'] as $letter) {
                $sheet->getStyle("{$letter}{$firstDataRow}:{$letter}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            }

            // Garis hanya di bawah baris header. Membordir seluruh range data
            // berarti PhpSpreadsheet menyentuh puluhan ribu sel satu per satu.
            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0F766E']]],
            ]);
        }

        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}".max($lastRow, $headerRow));
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setSelectedCell('A'.($headerRow + 1));

        // Landscape + repeat header, supaya cetak/print preview tetap terbaca.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
    }

    /** @param  array<string, Collection<string, int>>  $breakdowns */
    private function addSummarySheet(
        Spreadsheet $spreadsheet,
        array $breakdowns,
        array $filterLabels,
        int $totalRows,
        string $generatedBy
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan');

        $sheet->setCellValue('A1', 'RINGKASAN DATA ASET');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
        ]);

        $sheet->setCellValue('A2', sprintf('Dicetak: %s · Oleh: %s', now()->format('d M Y H:i'), $generatedBy));
        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('A3', 'Filter: '.($filterLabels ? implode(' · ', $filterLabels) : 'Tidak ada (semua data)'));
        $sheet->mergeCells('A3:C3');
        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '475569']],
        ]);

        $sheet->setCellValue('A5', 'Total baris');
        $sheet->setCellValue('B5', $totalRows);
        $sheet->getStyle('A5:B5')->applyFromArray(['font' => ['bold' => true]]);

        $row = 7;
        foreach ($breakdowns as $title => $rows) {
            $sheet->setCellValue('A'.$row, strtoupper($title));
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle('A'.$row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SLATE]],
            ]);
            $row++;

            $sheet->setCellValue('A'.$row, 'Nilai');
            $sheet->setCellValue('B'.$row, 'Jumlah');
            $sheet->setCellValue('C'.$row, '% dari total');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
            ]);
            $row++;

            foreach ($rows as $label => $count) {
                $sheet->setCellValue('A'.$row, $label === '' || $label === null ? '(kosong)' : $label);
                $sheet->setCellValue('B'.$row, $count);
                $sheet->setCellValue('C'.$row, $totalRows ? round($count / $totalRows * 100, 1).'%' : '-');
                $row++;
            }

            $row++;
        }

        foreach (['A' => 34, 'B' => 12, 'C' => 14] as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
    }
}
