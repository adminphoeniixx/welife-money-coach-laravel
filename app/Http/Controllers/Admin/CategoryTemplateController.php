<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryTemplateController extends Controller
{
    /**
     * List the income/expense category templates offered to users.
     */
    public function index(Request $request): Response
    {
        $type = $request->query('type', 'income');
        $type = in_array($type, ['income', 'expense'], true) ? $type : 'income';

        return Inertia::render('admin/categories/Index', [
            'type' => $type,
            'categories' => CategoryTemplate::where('type', $type)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'counts' => [
                'income' => CategoryTemplate::where('type', 'income')->count(),
                'expense' => CategoryTemplate::where('type', 'expense')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        CategoryTemplate::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category added.']);

        return back();
    }

    public function update(Request $request, CategoryTemplate $category): RedirectResponse
    {
        $data = $this->validateData($request, $category);

        $category->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category updated.']);

        return back();
    }

    public function destroy(CategoryTemplate $category): RedirectResponse
    {
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category deleted.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateData(Request $request, ?CategoryTemplate $category = null): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'group' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = Str::slug($validated['name']);

        $validated['slug'] = Rule::unique('category_templates')->where(
            fn ($q) => $q->where('type', $validated['type'])
        );

        // Ensure a unique slug within the type (append a numeric suffix if needed).
        $base = $slug !== '' ? $slug : 'category';
        $slug = $base;
        $i = 2;
        while (CategoryTemplate::where('type', $validated['type'])
            ->where('slug', $slug)
            ->when($category, fn ($q) => $q->where('id', '!=', $category->id))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $validated['slug'] = $slug;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }
}
