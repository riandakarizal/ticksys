<?php

namespace App\Http\Controllers;

use App\Models\PjctBudget;
use App\Models\PjctDoc;
use App\Models\PjctMain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PjctBudgetController extends Controller
{
    public function store(Request $request, PjctMain $project): RedirectResponse
    {
        $user = $request->user();
        $canCreate = $user->isSuperAdmin()
            || ($user->isAdmin() && $user->user_div === 'Equipment & Technology Commercial');
        abort_unless($canCreate, 403);

        $data = $request->validate([
            'components'              => ['required', 'array', 'min:1'],
            'components.*.bdg_name'   => ['required', 'string', 'max:225'],
            'components.*.bdg_type'   => ['required', 'in:PENGADAAN,PEKERJAAN'],
            'components.*.bdg_type2'  => ['required', 'string', 'max:20'],
            'components.*.bdg_value'  => ['required', 'integer', 'min:0'],
            'boq_file'                => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx'],
        ]);

        foreach ($data['components'] as $component) {
            PjctBudget::create([
                'bdg_pjctid' => $project->id,
                'bdg_name'   => $component['bdg_name'],
                'bdg_type'   => $component['bdg_type'],
                'bdg_type2'  => $component['bdg_type2'],
                'bdg_value'  => $component['bdg_value'],
            ]);
        }

        if ($request->hasFile('boq_file')) {
            $file     = $request->file('boq_file');
            $filename = $file->getClientOriginalName();

            $file->storeAs($project->id, $filename, 'docfile');

            PjctDoc::create([
                'doc_number'   => '',
                'doc_pjctid'   => $project->id,
                'doc_type'     => 'BOQ',
                'doc_filetype' => $file->getClientOriginalExtension(),
                'doc_filename' => $filename,
                'doc_filepath' => $project->id . '/' . $filename,
                'doc_desc'     => '',
            ]);
        }

        return back()->with('success', 'BoQ berhasil disimpan.');
    }
}
