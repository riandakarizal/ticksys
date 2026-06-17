<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends AdminController
{
    public function index(): View
    {
        return view('admin.categories', $this->viewData('Category', 'Manage category routing, subcategories, and auto assignment.'));
    }

    public function store(CategoryRequest $request): RedirectResponse|JsonResponse
    {
        $authUser = auth()->user();
        $data = $request->validated();

        $parentCategory = ! empty($data['parent_id'])
            ? Category::query()->where('company_id', $authUser->company_id)->find($data['parent_id'])
            : null;

        Category::create([
            'company_id' => $authUser->company_id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'color' => $this->resolveCategoryColor($authUser->company_id, $data['color'] ?? null, $parentCategory),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->respond($request, 'Category created successfully.', route('admin.categories.index'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($category);

        $data = $request->validated();

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }

        $parentCategory = ! empty($data['parent_id'])
            ? Category::query()->where('company_id', auth()->user()->company_id)->find($data['parent_id'])
            : null;

        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'color' => $this->resolveCategoryColor(
                auth()->user()->company_id,
                $data['color'] ?? null,
                $parentCategory,
                $category->color
            ),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (blank($category->parent_id)) {
            $category->children()->update(['color' => $category->color]);
        }

        return $this->respond($request, 'Category updated successfully.', route('admin.categories.index'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($category);

        $category->projects()->detach();
        $category->delete();

        return $this->respond($request, 'Category deleted successfully.', route('admin.categories.index'));
    }
}
