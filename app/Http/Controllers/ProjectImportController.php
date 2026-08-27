<?php

namespace App\Http\Controllers;

use App\Support\ProjectImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectImportController extends Controller
{
    private const SESSION_KEY = 'project_import_file';

    public function __construct(private ProjectImportService $service) {}

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = $this->service->makeTemplate();

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-import-project.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [], ['file' => 'File Excel']);

        // Park the upload instead of the parsed rows — a few thousand projects would not
        // sit comfortably in the session payload, and re-parsing on confirm re-checks
        // duplicates against whatever is in the DB by then.
        $stored = $request->file('file')->storeAs(
            'project-imports',
            Str::uuid().'.xlsx',
            'local'
        );

        try {
            $rows = $this->service->parse(Storage::disk('local')->path($stored));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);

            return back()->withErrors(['file' => 'File tidak bisa dibaca: '.$e->getMessage()]);
        }

        if (! $rows) {
            Storage::disk('local')->delete($stored);

            return back()->withErrors(['file' => 'Tidak ada baris data di file. Isi data mulai baris ke-2.']);
        }

        $this->forgetPending($request);
        $request->session()->put(self::SESSION_KEY, $stored);

        $classified = $this->service->classify($rows);

        return view('monitoring.project_import_preview', [
            'classified' => $classified,
            'summary' => $this->service->summarise($classified),
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function confirm(Request $request)
    {
        $stored = $request->session()->pull(self::SESSION_KEY);

        if (! $stored || ! Storage::disk('local')->exists($stored)) {
            return redirect()->route('monitoring.index')
                ->withErrors(['file' => 'Sesi import sudah berakhir. Silakan upload ulang.']);
        }

        try {
            $classified = $this->service->classify(
                $this->service->parse(Storage::disk('local')->path($stored))
            );

            $result = $this->service->execute($classified, $request->user());
        } catch (\RuntimeException $e) {
            // ID quota exhausted — nothing was written, so this is recoverable.
            return redirect()->route('monitoring.index')->withErrors(['file' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($stored);
        }

        $message = sprintf('%d project berhasil ditambahkan.', $result['inserted']);

        if ($result['skipped']) {
            $message .= sprintf(' %d dilewati (sudah terdaftar).', $result['skipped']);
        }

        if ($result['failed']) {
            $message .= sprintf(' %d gagal (data tidak valid).', $result['failed']);
        }

        return redirect()->route('monitoring.index')->with('success', $message);
    }

    public function cancel(Request $request)
    {
        $this->forgetPending($request);

        return redirect()->route('monitoring.index');
    }

    private function forgetPending(Request $request): void
    {
        $stored = $request->session()->pull(self::SESSION_KEY);

        if ($stored) {
            Storage::disk('local')->delete($stored);
        }
    }
}
