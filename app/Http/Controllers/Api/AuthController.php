<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use PresentsUser;

    /**
     * Register a new account and return an API token. (register screen)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return response()->json([
            'token' => $user->createToken($this->deviceName($request))->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Verify credentials and issue an API token. (login screen)
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($this->deviceName($request))->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * The currently authenticated user. (used on app launch)
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Revoke the token used for the current request (log out this device).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Revoke every token for the user (log out of all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out of all devices.']);
    }

    /**
     * Email a password-reset link. (forgot screen)
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($validated);

        // Always report success to avoid leaking which emails are registered.
        return response()->json([
            'message' => $status === Password::RESET_LINK_SENT
                ? __($status)
                : 'If that email exists, a reset link has been sent.',
        ]);
    }

    /**
     * Complete a password reset with the emailed token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($validated, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }

    private function deviceName(Request $request): string
    {
        return (string) $request->input('device_name', $request->userAgent() ?: 'mobile');
    }
}
