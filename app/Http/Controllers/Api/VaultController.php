<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVaultUnlockedApi;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VaultController extends Controller
{
    /**
     * Vault status: whether a PIN is set and whether this session is unlocked.
     * (vaultLock screen)
     */
    public function gate(Request $request): JsonResponse
    {
        return response()->json([
            'mode' => $request->user()->hasVaultPin() ? 'unlock' : 'setup',
            'has_pin' => $request->user()->hasVaultPin(),
            'unlocked' => $this->isUnlocked($request),
        ]);
    }

    /**
     * Create or change the vault PIN. Changing requires the current PIN.
     */
    public function setPin(Request $request): JsonResponse
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

        return response()->json(['message' => 'Vault PIN set.', 'unlocked' => true]);
    }

    /**
     * Verify the PIN and unlock the vault for this token.
     */
    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate(['pin' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->hasVaultPin() || ! Hash::check($validated['pin'], $user->vault_pin)) {
            throw ValidationException::withMessages(['pin' => 'Incorrect PIN. Please try again.']);
        }

        $this->markUnlocked($request);

        return response()->json(['message' => 'Vault unlocked.', 'unlocked' => true]);
    }

    /**
     * Lock the vault (clear the unlocked flag for this token).
     */
    public function lock(Request $request): JsonResponse
    {
        $key = EnsureVaultUnlockedApi::cacheKey($request);
        if ($key !== null) {
            Cache::forget($key);
        }

        return response()->json(['message' => 'Vault locked.', 'unlocked' => false]);
    }

    /**
     * The unlocked vault: documents grouped by category, searchable. (vault screen)
     */
    public function index(Request $request): JsonResponse
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

        return response()->json([
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

        $key = EnsureVaultUnlockedApi::cacheKey($request);

        return $key !== null && Cache::has($key);
    }

    private function markUnlocked(Request $request): void
    {
        $key = EnsureVaultUnlockedApi::cacheKey($request);
        if ($key !== null) {
            Cache::put($key, time(), EnsureVaultUnlockedApi::TIMEOUT);
        }
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
