<?php

namespace App\Http\Controllers;

use App\Models\PjctDoc;
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
}
