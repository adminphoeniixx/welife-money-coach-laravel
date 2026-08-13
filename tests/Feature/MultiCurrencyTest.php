<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Signing up outside India must give the user their own currency everywhere —
 * amounts are never converted, they are simply shown in the currency the user
 * picked (see config/currencies.php).
 */
class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    // --- Sign-up ------------------------------------------------------------

    public function test_web_registration_with_a_country_sets_its_currency(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'Amelia Stone',
            'email' => 'amelia@example.com',
            'country' => 'GB',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'amelia@example.com')->firstOrFail();
        $this->assertSame('GB', $user->country);
        $this->assertSame('GBP', $user->currency);
        $this->assertSame('en-GB', $user->locale);
    }

    public function test_web_registration_without_a_country_keeps_the_default(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'No Country',
            'email' => 'none@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'none@example.com')->firstOrFail();
        $this->assertSame(Currency::default(), $user->currency);
    }

    public function test_api_registration_accepts_a_country(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Kenji Watanabe',
            'email' => 'kenji@example.com',
            'country' => 'jp', // lower case must be accepted
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
        ])->assertCreated()
            ->assertJsonPath('user.currency', 'JPY')
            ->assertJsonPath('user.country', 'JP')
            ->assertJsonPath('user.currency_symbol', '¥');
    }

    public function test_api_registration_rejects_an_unsupported_country(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Nowhere',
            'email' => 'nowhere@example.com',
            'country' => 'ZZ',
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
        ])->assertStatus(422)->assertJsonValidationErrors('country');
    }

    public function test_regions_endpoint_lists_countries_and_currencies(): void
    {
        $response = $this->getJson('/api/auth/regions')->assertOk();

        $this->assertContains(
            ['key' => 'US', 'label' => 'United States', 'currency' => 'USD', 'symbol' => '$', 'locale' => 'en-US'],
            $response->json('countries'),
        );
        $this->assertContains('EUR', $response->json('currencies'));
    }

    // --- Changing region later ---------------------------------------------

    public function test_onboarding_country_alone_picks_the_currency(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/onboarding', [
            'country' => 'DE',
            'notifications_enabled' => true,
        ])->assertOk()->assertJsonPath('user.currency', 'EUR');

        $this->assertSame('de-DE', $user->fresh()->locale);
    }

    public function test_region_change_relabels_existing_records(): void
    {
        $user = User::factory()->create(['currency' => 'INR', 'country' => 'IN']);
        $entry = Entry::create([
            'user_id' => $user->id,
            'type' => 'expense',
            'category' => 'food',
            'amount_cents' => 12345,
            'occurred_on' => now(),
        ]);

        $this->assertSame('INR', $entry->fresh()->currency);

        Sanctum::actingAs($user);
        $this->putJson('/api/settings/region', ['country' => 'AE'])->assertOk();

        $this->assertSame('AED', $user->fresh()->currency);
        // The amount is untouched — only the currency label follows the user.
        $this->assertSame('AED', $entry->fresh()->currency);
        $this->assertSame(12345, $entry->fresh()->amount_cents);
    }

    public function test_new_records_inherit_the_users_currency(): void
    {
        $user = User::factory()->create(['currency' => 'USD', 'country' => 'US']);
        Sanctum::actingAs($user);

        $this->postJson('/api/entries', [
            'type' => 'expense',
            'category' => 'food',
            'amount' => 25,
            'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame('USD', $user->entries()->sole()->currency);
    }

    // --- Rendering ----------------------------------------------------------

    public function test_amounts_are_formatted_in_the_users_currency(): void
    {
        $user = User::factory()->create(['currency' => 'USD', 'locale' => 'en-US', 'country' => 'US']);

        $this->assertStringContainsString('$', $user->money(500000));
        $this->assertStringContainsString('5,000', $user->money(500000));
        $this->assertSame('$', $user->currencySymbol());
    }

    public function test_dashboard_reports_the_users_currency(): void
    {
        $user = User::factory()->create(['currency' => 'SGD', 'country' => 'SG']);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('currency', 'SGD')
            ->assertJsonPath('currency_symbol', 'S$');
    }

    public function test_challenge_presets_are_written_in_the_users_currency(): void
    {
        $user = User::factory()->create(['currency' => 'GBP', 'locale' => 'en-GB', 'country' => 'GB']);
        Sanctum::actingAs($user);

        $titles = array_column($this->getJson('/api/challenges')->assertOk()->json('presets'), 'title');

        $this->assertNotEmpty(array_filter($titles, fn (string $t) => str_contains($t, '£')));
        $this->assertEmpty(array_filter($titles, fn (string $t) => str_contains($t, '₹')));
    }

    public function test_csv_export_header_names_the_users_currency(): void
    {
        $user = User::factory()->create(['currency' => 'EUR', 'country' => 'DE']);
        Sanctum::actingAs($user);

        $response = $this->get('/api/reports/export');
        $response->assertOk();

        $this->assertStringContainsString('Amount (EUR)', $response->streamedContent());
    }

    // --- Registry -----------------------------------------------------------

    public function test_every_country_maps_to_a_supported_currency(): void
    {
        foreach (Currency::countries() as $code => $meta) {
            $this->assertTrue(
                Currency::supports($meta['currency']),
                "Country {$code} maps to unsupported currency {$meta['currency']}.",
            );
        }
    }

    public function test_unknown_currency_falls_back_to_the_default(): void
    {
        $this->assertSame(Currency::default(), Currency::normalise('XXX'));
        $this->assertSame(Currency::default(), Currency::forCountry('ZZ'));
    }
}
