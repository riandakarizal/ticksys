<?php

namespace App\Http\Controllers;

use App\Models\PjctDoc;
use App\Models\PjctMain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $data = $request->validate([
            'doc_type' => ['required', 'in:KONTRAK,RKST,RAB,BAST,SOP,BOQ'],
            'doc_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx'],
        ]);

        $file     = $request->file('doc_file');
        $filename = $file->getClientOriginalName();

        $file->storeAs($project->id, $filename, 'docfile');

        PjctDoc::create([
            'doc_number'   => '',
            'doc_pjctid'   => $project->id,
            'doc_type'     => $data['doc_type'],
            'doc_filetype' => $file->getClientOriginalExtension(),
            'doc_filename' => $filename,
            'doc_filepath' => $project->id . '/' . $filename,
            'doc_desc'     => '',
        ]);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }
}
