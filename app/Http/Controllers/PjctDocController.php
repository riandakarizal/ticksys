<?php

namespace App\Http\Controllers;

use App\Models\PjctDoc;
use App\Models\PjctMain;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dokumen project. Upload dua langkah, seperti Google Drive:
 *   1. `upload()` — file diunggah lebih dulu (dengan progress bar di browser) ke folder sementara
 *      `storage/app/private/doc-uploads`, dan dicatat di sesi user dengan sebuah token.
 *   2. `store()` — user mengisi nama & jenis lalu menyimpan; file dipindah ke `docfile/PJxxxx/`
 *      dan baris `pjct_doc` dibuat.
 * Token terikat ke sesi user dan ke project, jadi tidak bisa dipakai menyimpan unggahan orang lain.
 */
class PjctDocController extends Controller
{
    /** Ukuran maksimal dokumen (KB) — selaraskan dengan docker/php/uploads.ini (upload_max_filesize). */
    public const MAX_KB = 5120;

    /** Folder sementara di disk `local` untuk unggahan yang belum disimpan. */
    private const TEMP_DIR = 'doc-uploads';

    /** Unggahan sementara lebih tua dari ini dihapus (user batal / menutup modal). */
    private const TEMP_TTL_SECONDS = 86400;

    public function show(PjctDoc $pjctDoc)
    {
        $allowedDivs = auth()->user()->allowedDivCodes();
        if ($allowedDivs !== null) {
            abort_unless(in_array($pjctDoc->project?->pjct_div, $allowedDivs, true), 403);
        }

        abort_unless(Storage::disk('docfile')->exists($pjctDoc->doc_filepath), 404);

        return Storage::disk('docfile')->response($pjctDoc->doc_filepath, $pjctDoc->doc_filename);
    }

    /** Langkah 1: terima file PDF, simpan sementara, kembalikan token untuk langkah 2. */
    public function upload(Request $request, PjctMain $project): JsonResponse
    {
        $this->authorizeProject($request->user(), $project);

        $request->validate(['doc_file' => ['required', 'file']], [
            'doc_file.required' => 'Pilih file PDF yang akan diunggah.',
            'doc_file.uploaded' => 'File gagal diunggah — kemungkinan ukurannya melebihi batas 5 MB.',
        ]);

        $file = $request->file('doc_file');
        if ($problem = $this->describeProblem($file)) {
            throw ValidationException::withMessages(['doc_file' => $problem]);
        }

        $this->pruneTempUploads();

        $token = (string) Str::uuid();
        $path  = $file->storeAs(self::TEMP_DIR, $token . '.pdf', 'local');

        $request->session()->put('doc_uploads.' . $token, [
            'project' => $project->id,
            'path'    => $path,
            'name'    => $file->getClientOriginalName(),
            'size'    => $file->getSize(),
        ]);

        return response()->json([
            'token' => $token,
            'name'  => $file->getClientOriginalName(),
            'size'  => $file->getSize(),
        ]);
    }

    /** Langkah 2: simpan dokumen yang sudah diunggah ke folder project + catat di pjct_doc. */
    public function store(Request $request, PjctMain $project): RedirectResponse
    {
        $this->authorizeProject($request->user(), $project);

        $data = $request->validate([
            'doc_name'     => ['required', 'string', 'max:255'],
            'doc_type'     => ['required', Rule::in(array_keys(PjctDoc::TYPES))],
            'upload_token' => ['required', 'string'],
        ], [
            'upload_token.required' => 'Unggah file PDF dulu sebelum menyimpan.',
        ]);

        $key  = 'doc_uploads.' . $data['upload_token'];
        $temp = $request->session()->get($key);
        $tempDisk = Storage::disk('local');

        if (! $temp || $temp['project'] !== $project->id || ! $tempDisk->exists($temp['path'])) {
            throw ValidationException::withMessages([
                'upload_token' => 'File unggahan tidak ditemukan atau sudah kedaluwarsa — unggah ulang file-nya.',
            ]);
        }

        $disk = Storage::disk('docfile');

        // Folder per project (`docfile/PJxxxx`) — dibuat kalau belum ada (mis. setelah docfile dibersihkan).
        if (! $disk->exists($project->id)) {
            $disk->makeDirectory($project->id);
        }

        $filename = $this->freeFilename($project->id, $temp['name']);
        $disk->writeStream($project->id . '/' . $filename, $tempDisk->readStream($temp['path']));
        $tempDisk->delete($temp['path']);
        $request->session()->forget($key);

        // Tercatat ke system_logs otomatis lewat LogsSystemActivity (aksi `create`).
        PjctDoc::create([
            'doc_number'   => '',
            'doc_pjctid'   => $project->id,
            'doc_type'     => $data['doc_type'],
            'doc_filetype' => 'pdf',
            'doc_filename' => $filename,
            'doc_filepath' => $project->id . '/' . $filename,
            'doc_desc'     => $data['doc_name'],
        ]);

        return back()->with('success', 'Dokumen "' . $data['doc_name'] . '" berhasil disimpan ke ' . $project->id . '.');
    }

    /**
     * Pesan yang menyebut kesalahan sebenarnya (jenis file dan/atau ukuran), atau null kalau file valid.
     * Bunyinya sama dengan pengecekan di browser (`docDescribeProblem` di monitoring/index.blade.php).
     */
    private function describeProblem(UploadedFile $file): ?string
    {
        $name    = $file->getClientOriginalName();
        $ext     = strtolower($file->getClientOriginalExtension());
        $tooBig  = $file->getSize() > self::MAX_KB * 1024;
        $size    = $this->formatSize($file->getSize());

        if ($ext !== 'pdf') {
            $kind = self::kindOf($ext);

            return $tooBig
                ? "\"{$name}\" adalah {$kind}, bukan PDF, dan ukurannya {$size} (maks. 5 MB)."
                : "\"{$name}\" adalah {$kind}, bukan PDF. Simpan/ekspor dulu sebagai PDF, lalu unggah ulang.";
        }

        if ($tooBig) {
            return "\"{$name}\" berukuran {$size}, melebihi batas 5 MB. Kompres PDF-nya atau pecah jadi beberapa file.";
        }

        // Ekstensi .pdf tapi isinya bukan PDF (mis. file lain yang hanya diganti namanya).
        if ($file->getMimeType() !== 'application/pdf') {
            return "\"{$name}\" bukan file PDF yang valid — mungkin hanya diganti namanya. Ekspor ulang sebagai PDF.";
        }

        return null;
    }

    /** Nama jenis file yang dikenal user, untuk pesan kesalahan. */
    private static function kindOf(string $ext): string
    {
        return match ($ext) {
            'doc', 'docx', 'rtf', 'odt'        => 'file Word',
            'xls', 'xlsx', 'csv', 'ods'        => 'file Excel',
            'ppt', 'pptx', 'odp'               => 'file PowerPoint',
            'jpg', 'jpeg', 'png', 'gif', 'heic', 'webp', 'bmp', 'tif', 'tiff' => 'file gambar',
            'zip', 'rar', '7z'                 => 'file arsip (zip/rar)',
            ''                                 => 'file tanpa ekstensi',
            default                            => 'file .' . $ext,
        };
    }

    private function formatSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /** Hanya superadmin / admin Commercial, dan hanya untuk project di divisi yang boleh dilihat. */
    private function authorizeProject(User $user, PjctMain $project): void
    {
        abort_unless(
            $user->isSuperAdmin() || ($user->isAdmin() && $user->user_div === 'Equipment & Technology Commercial'),
            403
        );

        $allowedDivs = $user->allowedDivCodes();
        abort_if($allowedDivs !== null && ! in_array($project->pjct_div, $allowedDivs, true), 403);
    }

    /** Hapus unggahan sementara yang tidak pernah disimpan. */
    private function pruneTempUploads(): void
    {
        $disk = Storage::disk('local');
        $limit = now()->getTimestamp() - self::TEMP_TTL_SECONDS;

        foreach ($disk->files(self::TEMP_DIR) as $file) {
            if ($disk->lastModified($file) < $limit) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Nama file yang belum dipakai di folder project: `kontrak.pdf` → `kontrak (2).pdf`, dst.
     * Tanpa ini unggahan dengan nama sama menimpa file lama, padahal barisnya di pjct_doc tetap ada.
     */
    private function freeFilename(string $folder, string $original): string
    {
        $disk = Storage::disk('docfile');
        $name = pathinfo($original, PATHINFO_FILENAME);
        $ext  = pathinfo($original, PATHINFO_EXTENSION);
        $candidate = $original;

        for ($i = 2; $disk->exists($folder . '/' . $candidate); $i++) {
            $candidate = $name . ' (' . $i . ')' . ($ext !== '' ? '.' . $ext : '');
        }

        return $candidate;
    }
}
