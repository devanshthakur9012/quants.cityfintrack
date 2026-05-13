<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseCategory;
use Illuminate\Http\Request;

class CourseCategoryController extends Controller
{
    public function index()
    {
        $pageTitle  = 'Course Categories';
        $categories = CourseCategory::withCount('courses')
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->paginate(getPaginate());

        return view('admin.courses.categories.index', compact('pageTitle', 'categories'));
    }

    public function create()
    {
        $pageTitle = 'Add Course Category';
        return view('admin.courses.categories.form', compact('pageTitle'));
    }

    public function store(Request $request)
    {
        $data = $this->validateCategory($request);
        $data['slug'] = CourseCategory::generateSlug($request->name);

        CourseCategory::create($data);

        $notify[] = ['success', 'Category created successfully'];
        return redirect()->route('admin.courses.categories.index')->withNotify($notify);
    }

    public function edit(CourseCategory $category)
    {
        $pageTitle = 'Edit Course Category';
        return view('admin.courses.categories.form', compact('pageTitle', 'category'));
    }

    public function update(Request $request, CourseCategory $category)
    {
        $data = $this->validateCategory($request);
        $data['slug'] = CourseCategory::generateSlug($request->name, $category->id);

        $category->update($data);

        $notify[] = ['success', 'Category updated successfully'];
        return redirect()->route('admin.courses.categories.index')->withNotify($notify);
    }

    public function destroy(CourseCategory $category)
    {
        if ($category->courses()->count() > 0) {
            $notify[] = ['error', 'Cannot delete category with existing courses'];
            return back()->withNotify($notify);
        }
        $category->delete();
        $notify[] = ['success', 'Category deleted successfully'];
        return back()->withNotify($notify);
    }

    public function statusToggle(CourseCategory $category)
    {
        $category->update(['status' => $category->status == 1 ? 0 : 1]);
        $notify[] = ['success', 'Status updated successfully'];
        return back()->withNotify($notify);
    }

    // ── Private ─────────────────────────────────────────────────────────────
    private function validateCategory(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:191',
            'icon'        => 'nullable|string|max:100',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
        ]) + ['sort_order' => $request->sort_order ?? 0];
    }
}