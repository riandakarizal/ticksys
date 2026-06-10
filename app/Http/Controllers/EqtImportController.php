<?php

namespace App\Http\Controllers;

use App\Support\EqtImportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EqtImportController extends Controller
{
    public function __construct(private EqtImportService $service) {}

    public function showImportPage()
    {
        return view('monitoring.import');
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        $parsed = $this->service->parse($request->file('file'));
        $diff   = $this->service->diff($parsed);

        $session = session();
        $session->put('eqt_import_parsed', $parsed);

        $summary = [];
        foreach ($diff as $type => $rows) {
            $summary[$type] = [
                'new'       => count(array_filter($rows, fn($r) => $r['status'] === 'new')),
                'update'    => count(array_filter($rows, fn($r) => $r['status'] === 'update')),
                'unchanged' => count(array_filter($rows, fn($r) => $r['status'] === 'unchanged')),
            ];
        }

        return view('monitoring.import_preview', compact('diff', 'summary'));
    }

    public function confirm(Request $request)
    {
        $parsed = session()->pull('eqt_import_parsed');

        if (!$parsed) {
            return redirect()->route('monitoring.import.show')
                ->with('error', 'Import session expired. Please upload again.');
        }

        $summary = $this->service->execute($parsed, auth()->user());

        return redirect()->route('monitoring.index')
            ->with('success', sprintf(
                'Import complete: %d new records, %d updated, %d unchanged.',
                $summary['new'],
                $summary['updated'],
                $summary['unchanged']
            ));
    }

    public function downloadTemplateByType(string $type): StreamedResponse
    {
        $filenames = [
            'project_eq'   => 'template-equipment-eq',
            'project_tech' => 'template-technology-tech',
            'handover'     => 'template-handover',
            'vehicle'      => 'template-kendaraan',
            'maintenance'  => 'template-maintenance',
        ];

        $spreadsheet = $this->service->makeTemplateForType($type);
        $filename    = ($filenames[$type] ?? 'template') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = $this->service->makeTemplate();

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'template-monitoring-eqt.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function export(): StreamedResponse
    {
        $spreadsheet = $this->service->makeExport();

        $filename = 'monitoring-eqt-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
