<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
 * @property string $currency
 * @property string $locale
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
#[Fillable(['name', 'email', 'password', 'avatar_path', 'currency', 'locale', 'country', 'primary_goal', 'notifications_enabled', 'notification_prefs', 'onboarded'])]
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
        'notifications_enabled' => true,
        'onboarded' => false,
    ];

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
