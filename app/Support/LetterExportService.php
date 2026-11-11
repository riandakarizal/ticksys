<?php

namespace App\Support;

use App\Models\Letter;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Workbook export register nomor surat: sheet `Nomor Surat` (kop + daftar sesuai filter) dan
 * `Ringkasan` (jumlah per jenis surat & per tahun). Nomor yang dibatalkan diberi warna abu.
 */
class LetterExportService
{
    private const COLUMNS = [
        ['Nomor Surat', 34],
        ['Tanggal', 12],
        ['Bulan', 7],
        ['Tahun', 7],
        ['Jenis Surat', 30],
        ['Kode', 9],
        ['Perihal', 60],
        ['Penandatangan', 30],
        ['Tujuan Surat', 30],
        ['PIC Internal', 16],
        ['Project', 40],
        ['Status', 10],
        ['Alasan Batal', 30],
        ['File', 8],
        ['Sumber', 10],
        ['Catatan Impor', 50],
        ['Dibuat Oleh', 20],
        ['Dibuat Pada', 17],
    ];

    private const HEADER_ROW = 5;

    private const TEAL = '0F766E';

    private const SLATE = 'F1F5F9';

    private const GREY = 'E2E8F0';

    /**
     * @param  iterable<int, Letter>  $letters  sudah difilter & di-sort, relasi type/project/creator di-eager-load
     * @param  array<int, string>  $filterLabels
     * @param  array<string, Collection<string, int>>  $breakdowns  judul → (label → jumlah)
     */
    public function make(iterable $letters, array $filterLabels, array $breakdowns, int $total, string $generatedBy): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator('PRISM')->setTitle('Register Nomor Surat');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Nomor Surat');
        $lastColumn = Coordinate::stringFromColumnIndex(count(self::COLUMNS));

        $sheet->setCellValue('A1', 'REGISTER NOMOR SURAT');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0F172A']]]);
        $sheet->setCellValue('A2', sprintf('PRISM — Equipment & Technology  ·  Dicetak: %s  ·  Oleh: %s  ·  Jumlah: %s nomor',
            now()->format('d M Y H:i'), $generatedBy, number_format($total)));
        $sheet->setCellValue('A3', 'Filter: '.($filterLabels ? implode('  ·  ', $filterLabels) : 'Tidak ada (semua data)'));
        foreach ([1, 2, 3] as $r) {
            $sheet->mergeCells("A{$r}:{$lastColumn}{$r}");
        }
        $sheet->getStyle('A2:A3')->applyFromArray(['font' => ['size' => 9, 'color' => ['rgb' => '475569']]]);

        foreach (self::COLUMNS as $i => [$label, $width]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($letter.self::HEADER_ROW, $label);
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastColumn}".self::HEADER_ROW)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $row = self::HEADER_ROW + 1;
        $cancelledRows = [];
        foreach ($letters as $letter) {
            $this->writeRow($sheet, $row, $letter);
            if ($letter->isCancelled()) {
                $cancelledRows[] = $row;
            }
            $row++;
        }
        $lastRow = $row - 1;
        $first = self::HEADER_ROW + 1;

        if ($lastRow >= $first) {
            $sheet->getStyle("B{$first}:B{$lastRow}")->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            $sheet->getStyle("R{$first}:R{$lastRow}")->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:mm');
            $sheet->getStyle("G{$first}:G{$lastRow}")->getAlignment()->setWrapText(true);
            foreach ($cancelledRows as $r) {
                $sheet->getStyle("A{$r}:{$lastColumn}{$r}")->applyFromArray([
                    'font' => ['color' => ['rgb' => '64748B']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::GREY]],
                ]);
            }
        }

        $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastColumn}".max($lastRow, self::HEADER_ROW));
        $sheet->freezePane('B'.(self::HEADER_ROW + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        $this->addSummarySheet($spreadsheet, $breakdowns, $total);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function writeRow(Worksheet $sheet, int $row, Letter $letter): void
    {
        $values = [
            $letter->number,
            $letter->letter_date,
            $letter->monthRoman(),
            $letter->year,
            $letter->type?->name,
            $letter->code,
            $letter->subject,
            $letter->signer,
            $letter->recipient,
            $letter->pic,
            $letter->project ? $letter->project->id.' — '.$letter->project->pjct_name : $letter->pjct_id,
            $letter->isCancelled() ? 'Batal' : 'Aktif',
            $letter->cancel_reason,
            $letter->file_path ? 'Ada' : '',
            $letter->isImported() ? 'Impor Excel' : 'PRISM',
            $letter->import_note,
            $letter->creator?->user_name,
            $letter->created_at,
        ];

        foreach ($values as $i => $value) {
            $cell = Coordinate::stringFromColumnIndex($i + 1).$row;

            if ($value instanceof \DateTimeInterface) {
                $sheet->setCellValue($cell, ExcelDate::PHPToExcel($value));
            } elseif ($i === 0) {
                $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($cell, $value);
            }
        }
    }

    /** @param  array<string, Collection<string, int>>  $breakdowns */
    private function addSummarySheet(Spreadsheet $spreadsheet, array $breakdowns, int $total): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan');
        $sheet->setCellValue('A1', 'RINGKASAN NOMOR SURAT');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $sheet->setCellValue('A2', 'Total nomor');
        $sheet->setCellValue('B2', $total);
        $sheet->getStyle('A2:B2')->applyFromArray(['font' => ['bold' => true]]);

        $row = 4;
        foreach ($breakdowns as $title => $rows) {
            $sheet->setCellValue("A{$row}", strtoupper($title));
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SLATE]],
            ]);
            $row++;
            $sheet->fromArray(['Nilai', 'Jumlah', '% dari total'], null, "A{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
            ]);
            $row++;
            foreach ($rows as $label => $count) {
                $sheet->setCellValue("A{$row}", $label === '' ? '(kosong)' : $label);
                $sheet->setCellValue("B{$row}", $count);
                $sheet->setCellValue("C{$row}", $total ? round($count / $total * 100, 1).'%' : '-');
                $row++;
            }
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(48);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(14);
    }
}
