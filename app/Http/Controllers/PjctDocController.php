<?php

namespace App\Http\Controllers;

use App\Models\PjctDoc;
use App\Models\PjctMain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PjctDocController extends Controller
{
    public function show(PjctDoc $pjctDoc)
    {
        $allowedDivs = auth()->user()->allowedDivCodes();
        if ($allowedDivs !== null) {
            abort_unless(in_array($pjctDoc->project?->pjct_div, $allowedDivs, true), 403);
        }

        abort_unless(Storage::disk('docfile')->exists($pjctDoc->doc_filepath), 404);

        return Storage::disk('docfile')->response($pjctDoc->doc_filepath, $pjctDoc->doc_filename);
    }

    public function store(Request $request, PjctMain $project): RedirectResponse
    {
        $user = $request->user();
        $canCreate = $user->isSuperAdmin()
            || ($user->isAdmin() && $user->user_div === 'Equipment & Technology Commercial');
        abort_unless($canCreate, 403);

        $allowedDivs = $user->allowedDivCodes();
        abort_if($allowedDivs !== null && ! in_array($project->pjct_div, $allowedDivs, true), 403);

        $data = $request->validate([
            'doc_name' => ['required', 'string', 'max:255'],
            'doc_type' => ['required', Rule::in(array_keys(PjctDoc::TYPES))],
            'doc_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx'],
        ]);

        $disk = Storage::disk('docfile');

        // Folder per project (`docfile/PJxxxx`) — biasanya sudah dibuat saat project dibuat,
        // tapi project hasil import/SQL manual bisa belum punya.
        if (! $disk->exists($project->id)) {
            $disk->makeDirectory($project->id);
        }

        $file     = $request->file('doc_file');
        $filename = $this->freeFilename($project->id, $file->getClientOriginalName());
        $file->storeAs($project->id, $filename, 'docfile');

        // Tercatat ke system_logs otomatis lewat LogsSystemActivity (aksi `create`).
        PjctDoc::create([
            'doc_number'   => '',
            'doc_pjctid'   => $project->id,
            'doc_type'     => $data['doc_type'],
            'doc_filetype' => strtolower($file->getClientOriginalExtension()),
            'doc_filename' => $filename,
            'doc_filepath' => $project->id . '/' . $filename,
            'doc_desc'     => $data['doc_name'],
        ]);

        return back()->with('success', 'Dokumen "' . $data['doc_name'] . '" berhasil diunggah ke ' . $project->id . '.');
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
