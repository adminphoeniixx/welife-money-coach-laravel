<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureVaultUnlocked;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VaultController extends Controller
{
    /**
     * The lock screen: set a PIN (first time) or unlock an existing vault.
     */
    public function gate(Request $request): Response|RedirectResponse
    {
        if ($this->isUnlocked($request)) {
            return redirect()->route('vault.index');
        }

        return Inertia::render('documents/Lock', [
            'mode' => $request->user()->hasVaultPin() ? 'unlock' : 'setup',
        ]);
    }

    /**
     * Create or change the vault PIN. Changing requires the current PIN.
     */
    public function setPin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'pin' => ['required', 'digits_between:4,6', 'confirmed'],
            'current_pin' => [$user->hasVaultPin() ? 'required' : 'nullable', 'string'],
        ]);

        if ($user->hasVaultPin() && ! Hash::check($validated['current_pin'], $user->vault_pin)) {
            throw ValidationException::withMessages(['current_pin' => 'That PIN is incorrect.']);
        }

        $user->forceFill(['vault_pin' => Hash::make($validated['pin'])])->save();
        $this->markUnlocked($request);

        return redirect()->route('vault.index');
    }

    /**
     * Verify the PIN and unlock the vault for this session.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->hasVaultPin() || ! Hash::check($validated['pin'], $user->vault_pin)) {
            throw ValidationException::withMessages(['pin' => 'Incorrect PIN. Please try again.']);
        }

        $this->markUnlocked($request);

        return redirect()->route('vault.index');
    }

    /**
     * Lock the vault (clear the unlocked session flag).
     */
    public function lock(Request $request): RedirectResponse
    {
        $request->session()->forget(EnsureVaultUnlocked::SESSION_KEY);

        return redirect()->route('vault.gate');
    }

    /**
     * The unlocked vault: documents grouped by category, searchable.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $category = (string) $request->query('category', 'all');

        $documents = $request->user()->documents()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            }))
            ->when($category !== 'all' && $category !== '', fn ($q) => $q->where('category', $category))
            ->latest()
            ->get();

        $counts = $request->user()->documents()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return Inertia::render('documents/Index', [
            'filters' => ['search' => $search, 'category' => $category],
            'categories' => collect(Document::CATEGORIES)
                ->map(fn ($label, $key) => [
                    'key' => $key,
                    'label' => $label,
                    'count' => (int) ($counts[$key] ?? 0),
                ])->values(),
            'total' => (int) $counts->sum(),
            'documents' => $documents->map(fn (Document $doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'category' => $doc->category,
                'category_label' => $doc->categoryLabel(),
                'side' => $doc->side,
                'is_image' => $doc->isImage(),
                'mime_type' => $doc->mime_type,
                'size' => $this->humanSize($doc->size_bytes),
                'notes' => $doc->notes,
                'uploaded_at' => $doc->created_at?->format('d M Y'),
            ])->values(),
        ]);
    }

    private function isUnlocked(Request $request): bool
    {
        if (! $request->user()->hasVaultPin()) {
            return false;
        }

        $at = (int) $request->session()->get(EnsureVaultUnlocked::SESSION_KEY, 0);

        return $at > 0 && (time() - $at) <= EnsureVaultUnlocked::TIMEOUT;
    }

    private function markUnlocked(Request $request): void
    {
        $request->session()->put(EnsureVaultUnlocked::SESSION_KEY, time());
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
