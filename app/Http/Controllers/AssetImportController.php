<?php

namespace App\Http\Controllers;

use App\Support\AssetImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetImportController extends Controller
{
    private const SESSION_KEY = 'asset_import_file';

    public function __construct(private AssetImportService $service) {}

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $this->authorizeImport($request);

        // Sheet Referensi hanya berisi project yang boleh diisi user ini.
        $spreadsheet = $this->service->makeTemplate($request->user()->allowedDivCodes());

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-import-asset.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request)
    {
        $this->authorizeImport($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [], ['file' => 'File Excel']);

        // Park the upload instead of the parsed rows — a few thousand assets would not
        // sit comfortably in the session payload, and re-parsing on confirm re-checks
        // duplicates against whatever is in the DB by then.
        $stored = $request->file('file')->storeAs(
            'asset-imports',
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

        $classified = $this->service->classify($rows, $request->user()->allowedDivCodes());

        return view('project.assets_import_preview', [
            'classified' => $classified,
            'summary' => $this->service->summarise($classified),
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function confirm(Request $request)
    {
        $this->authorizeImport($request);

        $stored = $request->session()->pull(self::SESSION_KEY);

        if (! $stored || ! Storage::disk('local')->exists($stored)) {
            return redirect()->route('project.assets')
                ->withErrors(['file' => 'Sesi import sudah berakhir. Silakan upload ulang.']);
        }

        try {
            $classified = $this->service->classify(
                $this->service->parse(Storage::disk('local')->path($stored)),
                $request->user()->allowedDivCodes()
            );

            $result = $this->service->execute($classified, $request->user());
        } catch (\RuntimeException $e) {
            // Monthly ID quota exhausted — nothing was written, so this is recoverable.
            return redirect()->route('project.assets')->withErrors(['file' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($stored);
        }

        $message = sprintf('%d aset berhasil ditambahkan.', $result['inserted']);

        if ($result['skipped']) {
            $message .= sprintf(' %d dilewati (serial sudah ada di project yang sama).', $result['skipped']);
        }

        if ($result['failed']) {
            $message .= sprintf(' %d gagal (data tidak valid).', $result['failed']);
        }

        return redirect()->route('project.assets')->with('success', $message);
    }

    public function cancel(Request $request)
    {
        $this->forgetPending($request);

        return redirect()->route('project.assets');
    }

    /** Route sudah membatasi ke superadmin/admin; di sini dipersempit ke admin divisi E&T O&M. */
    private function authorizeImport(Request $request): void
    {
        abort_unless($request->user()->canImportAssets(), 403);
    }

    private function forgetPending(Request $request): void
    {
        $stored = $request->session()->pull(self::SESSION_KEY);

        if ($stored) {
            Storage::disk('local')->delete($stored);
        }
    }
}
