<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Http\Requests\Admin\SlaPolicyRequest;
use App\Models\SlaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SlaPolicyController extends AdminController
{
    public function index()
    {
        return view('admin.sla', $this->viewData('SLA Policy', 'Manage response and resolution targets per projects.'));
    }

    public function store(SlaPolicyRequest $request): RedirectResponse|JsonResponse
    {
        $authUser = auth()->user();
        $data = $request->validated();

        if ($request->boolean('is_default')) {
            SlaPolicy::query()->update(['is_default' => false]);
        }

        SlaPolicy::create([
            'company_id' => 1,
            'name' => $data['name'],
            'response_minutes' => $data['response_minutes'],
            'resolution_minutes' => $data['resolution_minutes'],
            'is_default' => $request->boolean('is_default'),
        ]);

        return $this->respond($request, 'SLA policy created successfully.', route('admin.sla.index'));
    }

    public function update(SlaPolicyRequest $request, SlaPolicy $slaPolicy): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($slaPolicy);
        $data = $request->validated();

        if ($request->boolean('is_default')) {
            SlaPolicy::query()
                ->whereKeyNot($slaPolicy->id)
                ->update(['is_default' => false]);
        }

        $slaPolicy->update([
            'name' => $data['name'],
            'response_minutes' => $data['response_minutes'],
            'resolution_minutes' => $data['resolution_minutes'],
            'is_default' => $request->boolean('is_default'),
        ]);

        return $this->respond($request, 'SLA policy updated successfully.', route('admin.sla.index'));
    }

    public function destroy(SlaPolicy $slaPolicy): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($slaPolicy);
        $slaPolicy->delete();

        return $this->respond(request(), 'SLA policy deleted successfully.', route('admin.sla.index'));
    }
}
