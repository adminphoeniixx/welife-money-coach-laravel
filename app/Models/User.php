<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Currency;
use App\Support\Options;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $currency
 * @property string $locale
 * @property string|null $timezone
 * @property string $number_format
 * @property string|null $country
 * @property string|null $primary_goal
 * @property bool $notifications_enabled
 * @property array<string, mixed>|null $notification_prefs
 * @property bool $onboarded
 * @property string|null $avatar_path
 * @property bool $is_admin
 * @property Carbon|null $suspended_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $vault_pin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'avatar_path', 'currency', 'locale', 'timezone', 'number_format', 'country', 'primary_goal', 'notifications_enabled', 'notification_prefs', 'onboarded'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'vault_pin'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Appended to the serialised model so the UI always has a photo URL.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    /**
     * Default preference values, mirroring the database column defaults so a
     * freshly built (not-yet-reloaded) model never exposes nulls.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'INR',
        'locale' => 'en-IN',
        'number_format' => 'indian',
        'notifications_enabled' => true,
        'onboarded' => false,
    ];

    /**
     * Apply a region choice: the country decides the currency, formatting
     * locale, timezone and number grouping unless one is given explicitly.
     *
     * Unknown or missing values fall back to what the user already has, so a
     * partial update (country only, currency only) never blanks the rest.
     */
    public function applyRegion(
        ?string $country = null,
        ?string $currency = null,
        ?string $locale = null,
        ?string $timezone = null,
        ?string $numberFormat = null,
    ): static {
        $country = Currency::supportsCountry($country) ? strtoupper((string) $country) : null;

        $this->country = $country ?? $this->country;

        $this->currency = Currency::supports($currency)
            ? strtoupper((string) $currency)
            : ($country !== null ? Currency::forCountry($country) : Currency::normalise($this->currency));

        $this->locale = $locale
            ?: ($country !== null ? Currency::localeForCountry($country) : ($this->locale ?: Currency::locale($this->currency)));

        $this->timezone = $timezone
            ?: ($country !== null ? Options::timezoneForCountry($country) : ($this->timezone ?: Options::timezoneForCountry($this->country)));

        $this->number_format = in_array($numberFormat, Options::numberFormatKeys(), true)
            ? (string) $numberFormat
            : ($country !== null
                ? ($country === 'IN' ? 'indian' : 'international')
                : ($this->number_format ?: 'indian'));

        return $this;
    }

    /**
     * Relabel the user's existing records with their current currency.
     *
     * Amounts are never converted — the app has no FX rates. This only keeps
     * the stored currency code on each row honest after a region change, so
     * exports do not claim an amount is in a currency the user left behind.
     */
    public function restampRecordCurrency(): void
    {
        foreach ([$this->entries(), $this->budgets(), $this->goals(), $this->debts(), $this->bills(), $this->financeAccounts()] as $records) {
            $records->update(['currency' => $this->currency]);
        }
    }

    /**
     * The symbol amounts should be rendered with for this user.
     */
    public function currencySymbol(): string
    {
        return Currency::symbol($this->currency);
    }

    /**
     * Format an amount held in minor units in this user's currency.
     */
    public function money(int $cents, bool $decimals = false): string
    {
        return Currency::format($cents, $this->currency, $this->formattingLocale(), $decimals);
    }

    /**
     * The locale numbers are grouped with: the user's own locale, unless they
     * asked for a specific grouping style on the region screen (1,00,000 vs
     * 100,000), which wins.
     */
    public function formattingLocale(): string
    {
        $locale = $this->locale ?: Currency::locale($this->currency);

        return match ($this->number_format) {
            'indian' => 'en-IN',
            'international' => str_starts_with($locale, 'en-IN') ? 'en-US' : $locale,
            default => $locale,
        };
    }

    /**
     * Public URL of the profile photo, or null to fall back to initials.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'notifications_enabled' => 'boolean',
            'notification_prefs' => 'array',
            'onboarded' => 'boolean',
        ];
    }

    /**
     * Determine whether the account is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** @return HasMany<FinanceAccount, $this> */
    public function financeAccounts(): HasMany
    {
        return $this->hasMany(FinanceAccount::class);
    }

    /** @return HasMany<Debt, $this> */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /** @return HasMany<Goal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Challenge, $this> */
    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /** @return BelongsToMany<Household, $this> */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class)->withPivot('role')->withTimestamps();
    }

    /**
     * The household the user currently belongs to (at most one), or null.
     */
    public function currentHousehold(): ?Household
    {
        return $this->households()->first();
    }

    public function hasHousehold(): bool
    {
        return $this->households()->exists();
    }

    /**
     * Whether the user has set a Secure Documents Vault PIN.
     */
    public function hasVaultPin(): bool
    {
        return $this->vault_pin !== null;
    }
}
