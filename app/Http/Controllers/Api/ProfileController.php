<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use PresentsUser;

    /**
     * The user's profile. (profile / editProfile screens)
     */
    public function show(Request $request): JsonResponse
    {
        $payload = $this->userPayload($request->user());

        // `profile` is the key the app reads; `user` is kept for the web client
        // and older builds. Both hold exactly the same object.
        return response()->json(['profile' => $payload, 'user' => $payload]);
    }

    /**
     * Update name and email.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $this->profileResponse($user->fresh(), 'Profile updated.');
    }

    /**
     * Upload or replace the profile photo.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('photo')->store('avatars', 'public');
        abort_if($path === false, 422, 'The photo could not be saved.');

        $user->update(['avatar_path' => $path]);

        return $this->profileResponse($user->fresh(), 'Photo updated.');
    }

    /**
     * Remove the profile photo.
     */
    public function destroyPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return $this->profileResponse($user->fresh(), 'Photo removed.');
    }

    /**
     * A mutation response carrying the fresh profile under both keys.
     */
    private function profileResponse(User $user, string $message): JsonResponse
    {
        $payload = $this->userPayload($user);

        return response()->json(['message' => $message, 'profile' => $payload, 'user' => $payload]);
    }

    /**
     * Change the account password. (setSecurity screen)
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        // Keep this device signed in; drop every other token.
        $currentId = $request->user()->currentAccessToken()->getKey();
        $request->user()->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Permanently delete the account. (dataPrivacy screen)
     */
    public function destroyAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Your account has been deleted.']);
    }
}
