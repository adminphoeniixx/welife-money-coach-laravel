<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    private const TYPES = ['announcement', 'faq', 'legal', 'page'];

    /**
     * List content items grouped by type (announcements, FAQ, legal, pages).
     */
    public function index(Request $request): Response
    {
        $type = $request->query('type', 'announcement');
        $type = in_array($type, self::TYPES, true) ? $type : 'announcement';

        return Inertia::render('admin/content/Index', [
            'type' => $type,
            'items' => ContentItem::where('type', $type)
                ->orderBy('sort_order')
                ->latest()
                ->get(),
            'counts' => collect(self::TYPES)->mapWithKeys(
                fn ($t) => [$t => ContentItem::where('type', $t)->count()]
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        ContentItem::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Content created.']);

        return back();
    }

    public function update(Request $request, ContentItem $content): RedirectResponse
    {
        $data = $this->validateData($request, $content);

        $content->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Content updated.']);

        return back();
    }

    public function destroy(ContentItem $content): RedirectResponse
    {
        $content->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Content deleted.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateData(Request $request, ?ContentItem $content = null): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Unique slug within the type.
        $base = Str::slug($validated['title']) ?: 'item';
        $slug = $base;
        $i = 2;
        while (ContentItem::where('type', $validated['type'])
            ->where('slug', $slug)
            ->when($content, fn ($q) => $q->where('id', '!=', $content->id))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $published = $request->boolean('is_published');

        return [
            'type' => $validated['type'],
            'title' => $validated['title'],
            'slug' => $slug,
            'body' => $validated['body'] ?? null,
            'is_published' => $published,
            'published_at' => $published ? ($content?->published_at ?? Carbon::now()) : null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }
}
