<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Currency;
use App\Support\Options;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Rules for the region fields (country / currency / locale).
     *
     * All three are optional: the country alone is enough to derive the other
     * two, and leaving everything out keeps the app defaults.
     *
     * @return array<string, array<int, Closure|ValidationRule|array<mixed>|string>>
     */
    protected function regionRules(): array
    {
        return [
            // Matched case-insensitively; App\Support\Currency uppercases for us.
            'country' => ['nullable', 'string', 'size:2', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Currency::supportsCountry(is_string($value) ? $value : null)) {
                    $fail('That country is not supported yet.');
                }
            }],
            'currency' => ['nullable', 'string', 'size:3', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Currency::supports(is_string($value) ? $value : null)) {
                    $fail('That currency is not supported yet.');
                }
            }],
            'locale' => ['nullable', 'string', 'max:12'],
            'timezone' => ['nullable', 'string', 'max:64', Rule::in(Options::timezoneKeys())],
            'number_format' => ['nullable', 'string', Rule::in(Options::numberFormatKeys())],
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
