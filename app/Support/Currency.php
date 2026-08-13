<?php

namespace App\Support;

use App\Models\User;
use NumberFormatter;

/**
 * Read-only access to the supported currencies and countries (config/currencies.php).
 *
 * Every user has exactly one currency, chosen from their country at sign-up.
 * Nothing here converts between currencies — amounts are only ever displayed
 * in the currency of the user they belong to.
 */
class Currency
{
    /**
     * All supported currencies, keyed by ISO 4217 code.
     *
     * @return array<string, array{name:string, symbol:string, locale:string}>
     */
    public static function all(): array
    {
        /** @var array<string, array{name:string, symbol:string, locale:string}> */
        return config('currencies.list', []);
    }

    /**
     * The supported currency codes, for validation rules.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * All supported countries, keyed by ISO 3166-1 alpha-2 code.
     *
     * @return array<string, array{name:string, currency:string, locale:string}>
     */
    public static function countries(): array
    {
        /** @var array<string, array{name:string, currency:string, locale:string}> */
        return config('currencies.countries', []);
    }

    /**
     * The supported country codes, for validation rules.
     *
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return array_keys(self::countries());
    }

    public static function default(): string
    {
        return (string) config('currencies.default', 'INR');
    }

    public static function defaultLocale(): string
    {
        return (string) config('currencies.default_locale', 'en-IN');
    }

    public static function supports(?string $code): bool
    {
        return $code !== null && array_key_exists(strtoupper($code), self::all());
    }

    public static function supportsCountry(?string $code): bool
    {
        return $code !== null && array_key_exists(strtoupper($code), self::countries());
    }

    /**
     * Normalise a currency code, falling back to the app default.
     */
    public static function normalise(?string $code): string
    {
        $code = $code === null ? null : strtoupper($code);

        return self::supports($code) ? $code : self::default();
    }

    /**
     * The currency used in a country, or the app default for unknown countries.
     */
    public static function forCountry(?string $country): string
    {
        $country = $country === null ? null : strtoupper($country);

        return self::supportsCountry($country)
            ? self::countries()[$country]['currency']
            : self::default();
    }

    /**
     * The formatting locale for a country, or the app default.
     */
    public static function localeForCountry(?string $country): string
    {
        $country = $country === null ? null : strtoupper($country);

        return self::supportsCountry($country)
            ? self::countries()[$country]['locale']
            : self::defaultLocale();
    }

    /**
     * The display symbol for a currency ("₹", "$", "€", …).
     */
    public static function symbol(?string $code): string
    {
        $code = self::normalise($code);

        return self::all()[$code]['symbol'];
    }

    public static function name(?string $code): string
    {
        $code = self::normalise($code);

        return self::all()[$code]['name'];
    }

    /**
     * The locale to format a currency in when the user has none of their own.
     */
    public static function locale(?string $code): string
    {
        $code = self::normalise($code);

        return self::all()[$code]['locale'];
    }

    /**
     * The currency + locale to render a user's amounts in.
     *
     * @return array{currency:string, locale:string, symbol:string}
     */
    public static function forUser(?User $user): array
    {
        $code = self::normalise($user?->currency);

        return [
            'currency' => $code,
            'locale' => $user?->locale ?: self::locale($code),
            'symbol' => self::symbol($code),
        ];
    }

    /**
     * Currencies as a list for API responses / select menus.
     *
     * @return list<array{code:string, name:string, symbol:string, locale:string}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (string $code, array $meta) => [
                'code' => $code,
                'name' => $meta['name'],
                'symbol' => $meta['symbol'],
                'locale' => $meta['locale'],
            ],
            array_keys(self::all()),
            self::all(),
        ));
    }

    /**
     * Countries as a list for API responses / select menus, alphabetical by name.
     *
     * @return list<array{code:string, name:string, currency:string, symbol:string, locale:string}>
     */
    public static function countryOptions(): array
    {
        $options = array_map(
            fn (string $code, array $meta) => [
                'code' => $code,
                'name' => $meta['name'],
                'currency' => $meta['currency'],
                'symbol' => self::symbol($meta['currency']),
                'locale' => $meta['locale'],
            ],
            array_keys(self::countries()),
            self::countries(),
        );

        usort($options, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return array_values($options);
    }

    /**
     * Format an amount held in minor units (see App\Support\Money).
     *
     * @param  bool  $decimals  Whether to show the fractional part.
     */
    public static function format(int $cents, ?string $currency = null, ?string $locale = null, bool $decimals = false): string
    {
        $code = self::normalise($currency);
        $amount = Money::toRupees($cents);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?: self::locale($code), NumberFormatter::CURRENCY);

            if (! $decimals) {
                $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
            }

            $formatted = $formatter->formatCurrency($amount, $code);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return self::symbol($code).number_format($amount, $decimals ? 2 : 0);
    }
}
