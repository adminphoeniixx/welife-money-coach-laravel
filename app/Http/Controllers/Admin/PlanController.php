<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Manage subscription plans / premium tiers.
     */
    public function index(): Response
    {
        return Inertia::render('admin/plans/Index', [
            'plans' => Plan::orderBy('sort_order')->orderBy('price_cents')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Plan::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan created.']);

        return back();
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validateData($request, $plan);

        $plan->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan updated.']);

        return back();
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan deleted.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateData(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'interval' => ['required', Rule::in(['month', 'year', 'lifetime'])],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = Str::slug($validated['name']);
        $slug = $slug !== '' ? $slug : 'plan';
        $base = $slug;
        $i = 2;
        while (Plan::where('slug', $slug)
            ->when($plan, fn ($q) => $q->where('id', '!=', $plan->id))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return [
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            // Convert the major-unit price into integer minor units for storage.
            'price_cents' => (int) round(((float) $validated['price']) * 100),
            'currency' => strtoupper($validated['currency']),
            'interval' => $validated['interval'],
            'features' => array_values(array_filter($validated['features'] ?? [], fn ($f) => trim((string) $f) !== '')),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }
}
