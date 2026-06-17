<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Team;
use App\Support\ProjectManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends AdminController
{
    public function index(): View
    {
        return view('admin.projects', $this->viewData('Project', 'Projects serve as the primary access boundary for clients, agents, and coordinators.'));
    }

    public function store(ProjectRequest $request, ProjectManager $projectManager): RedirectResponse|JsonResponse
    {
        $projectManager->create(auth()->user()->company_id, $request->validated());

        return $this->respond($request, 'Project created successfully.', route('admin.projects.index'));
    }

    public function update(ProjectRequest $request, Team $team, ProjectManager $projectManager): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($team);

        $projectManager->update($team, $request->validated());

        return $this->respond($request, 'Project updated successfully.', route('admin.projects.index'));
    }

    public function destroy(Request $request, Team $team, ProjectManager $projectManager): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($team);

        $projectManager->delete($team);

        return $this->respond($request, 'Project deleted successfully.', route('admin.projects.index'));
    }
}
