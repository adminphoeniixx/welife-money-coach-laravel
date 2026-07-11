<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Default settings and their types.
     *
     * @var array<string, array{type: string, default: mixed}>
     */
    private const SCHEMA = [
        'app_name' => ['type' => 'string', 'default' => 'MoneyCoach'],
        'support_email' => ['type' => 'string', 'default' => 'support@moneycoach.test'],
        'default_currency' => ['type' => 'string', 'default' => 'USD'],
        'default_country' => ['type' => 'string', 'default' => 'US'],
        'free_max_loans' => ['type' => 'int', 'default' => 2],
        'free_max_cards' => ['type' => 'int', 'default' => 2],
        'free_max_budgets' => ['type' => 'int', 'default' => 3],
        'registration_enabled' => ['type' => 'bool', 'default' => true],
        'maintenance_mode' => ['type' => 'bool', 'default' => false],
    ];

    /**
     * Show the platform settings form.
     */
    public function index(): Response
    {
        $stored = Setting::map();

        $settings = [];
        foreach (self::SCHEMA as $key => $meta) {
            $settings[$key] = $this->cast(
                $meta['type'],
                array_key_exists($key, $stored) ? $stored[$key] : $meta['default'],
            );
        }

        return Inertia::render('admin/settings/Index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Persist the submitted settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'],
            'default_currency' => ['required', 'string', 'size:3'],
            'default_country' => ['required', 'string', 'size:2'],
            'free_max_loans' => ['required', 'integer', 'min:0', 'max:1000'],
            'free_max_cards' => ['required', 'integer', 'min:0', 'max:1000'],
            'free_max_budgets' => ['required', 'integer', 'min:0', 'max:1000'],
            'registration_enabled' => ['boolean'],
            'maintenance_mode' => ['boolean'],
        ]);

        foreach (self::SCHEMA as $key => $meta) {
            $value = match ($meta['type']) {
                'bool' => $request->boolean($key) ? '1' : '0',
                'string' => $key === 'default_currency'
                    ? strtoupper((string) $validated[$key])
                    : ($key === 'default_country' ? strtoupper((string) $validated[$key]) : (string) $validated[$key]),
                default => (string) $validated[$key],
            };

            Setting::put($key, $value);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Settings saved.']);

        return back();
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => (string) $value,
        };
    }
}
