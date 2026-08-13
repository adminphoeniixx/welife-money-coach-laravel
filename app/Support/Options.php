<?php

namespace App\Support;

use App\Models\Document;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * The option lists the mobile app renders (config/moneycoach.php).
 *
 * Everything the app shows in a picker comes from here so there is exactly one
 * definition per list: the screen endpoints and GET /api/meta/options serve the
 * same values, and validation rules are built from the same arrays.
 */
class Options
{
    /**
     * The whole option catalogue, as served by GET /api/meta/options.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'transactions' => [
                'income_categories' => self::incomeCategories(),
                'expense_categories' => self::expenseCategories(),
                'payment_methods' => self::paymentMethods(),
            ],
            'assets' => [
                'types' => self::assetTypes(),
            ],
            'debts' => [
                'loan_categories' => self::loanCategories(),
                'kinds' => self::debtKinds(),
            ],
            'planning' => [
                'goal_types' => self::goalTypes(),
                'budget_categories' => self::expenseCategories(),
            ],
            'reminders' => [
                'kinds' => self::reminderKinds(),
                'repeat_options' => self::repeatOptions(),
                'remind_days_before' => self::remindDaysBefore(),
            ],
            'vault' => [
                'categories' => self::vaultCategories(),
            ],
            'family' => [
                'categories' => self::familyCategories(),
            ],
            'onboarding' => [
                'goals' => self::onboardingGoals(),
            ],
            'notifications' => [
                'channels' => self::notificationChannels(),
            ],
            'region' => [
                'currencies' => Currency::codes(),
                'currency_details' => Currency::options(),
                'countries' => self::countries(),
                'timezones' => self::timezones(),
                'number_formats' => self::numberFormats(),
            ],
        ];
    }

    // --- Transactions -------------------------------------------------------

    /** @return list<string> */
    public static function incomeCategories(): array
    {
        return array_values((array) config('moneycoach.transactions.income_categories', []));
    }

    /** @return list<string> */
    public static function expenseCategories(): array
    {
        return array_values((array) config('moneycoach.transactions.expense_categories', []));
    }

    /** @return list<string> */
    public static function paymentMethods(): array
    {
        return array_values((array) config('moneycoach.transactions.payment_methods', []));
    }

    // --- Assets -------------------------------------------------------------

    /** @return list<array{key:string, label:string}> */
    public static function assetTypes(): array
    {
        return self::keyLabel((array) config('moneycoach.assets.types', []));
    }

    /** @return list<string> */
    public static function assetTypeKeys(): array
    {
        return array_keys((array) config('moneycoach.assets.types', []));
    }

    public static function assetTypeLabel(?string $key): string
    {
        /** @var array<string, string> $types */
        $types = (array) config('moneycoach.assets.types', []);

        return $types[(string) $key] ?? 'Other';
    }

    // --- Debts --------------------------------------------------------------

    /** @return list<string> */
    public static function loanCategories(): array
    {
        return array_values((array) config('moneycoach.debts.loan_categories', []));
    }

    /** @return list<string> */
    public static function debtKinds(): array
    {
        return array_values((array) config('moneycoach.debts.kinds', []));
    }

    // --- Planning -----------------------------------------------------------

    /** @return list<array{key:string, label:string}> */
    public static function goalTypes(): array
    {
        return self::keyLabel((array) config('moneycoach.planning.goal_types', []));
    }

    /** @return list<string> */
    public static function goalTypeKeys(): array
    {
        return array_keys((array) config('moneycoach.planning.goal_types', []));
    }

    // --- Reminders ----------------------------------------------------------

    /** @return list<string> */
    public static function reminderKinds(): array
    {
        return array_values((array) config('moneycoach.reminders.kinds', []));
    }

    /** @return list<string> */
    public static function repeatOptions(): array
    {
        return array_values((array) config('moneycoach.reminders.repeat_options', []));
    }

    /** @return list<int> */
    public static function remindDaysBefore(): array
    {
        return array_values(array_map('intval', (array) config('moneycoach.reminders.remind_days_before', [])));
    }

    // --- Vault --------------------------------------------------------------

    /** @return list<array{key:string, label:string}> */
    public static function vaultCategories(): array
    {
        return self::keyLabel(Document::CATEGORIES);
    }

    // --- Family -------------------------------------------------------------

    /** @return list<string> */
    public static function familyCategories(): array
    {
        return array_values((array) config('moneycoach.family.categories', []));
    }

    // --- Onboarding ---------------------------------------------------------

    /** @return list<array{key:string, label:string}> */
    public static function onboardingGoals(): array
    {
        return self::keyLabel((array) config('moneycoach.onboarding.goals', []));
    }

    /** @return list<string> */
    public static function onboardingGoalKeys(): array
    {
        return array_keys((array) config('moneycoach.onboarding.goals', []));
    }

    // --- Notifications ------------------------------------------------------

    /** @return list<array{key:string, label:string}> */
    public static function notificationChannels(): array
    {
        return self::keyLabel((array) config('moneycoach.notifications.channels', []));
    }

    /** @return list<string> */
    public static function notificationChannelKeys(): array
    {
        return array_keys((array) config('moneycoach.notifications.channels', []));
    }

    // --- Region -------------------------------------------------------------

    /**
     * Supported countries in the {key, label} shape the app's pickers expect,
     * with the currency each one implies.
     *
     * @return list<array{key:string, label:string, currency:string, symbol:string, locale:string}>
     */
    public static function countries(): array
    {
        return array_map(fn (array $c) => [
            'key' => $c['code'],
            'label' => $c['name'],
            'currency' => $c['currency'],
            'symbol' => $c['symbol'],
            'locale' => $c['locale'],
        ], Currency::countryOptions());
    }

    /** @return list<array{key:string, label:string}> */
    public static function numberFormats(): array
    {
        return self::keyLabel((array) config('moneycoach.region.number_formats', []));
    }

    /** @return list<string> */
    public static function numberFormatKeys(): array
    {
        return array_keys((array) config('moneycoach.region.number_formats', []));
    }

    /**
     * Timezones for the supported countries, labelled with their GMT offset.
     *
     * @return list<array{key:string, label:string, country:string|null, offset:string}>
     */
    public static function timezones(): array
    {
        $identifiers = [];

        foreach (Currency::countryCodes() as $country) {
            foreach (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country) ?: [] as $zone) {
                $identifiers[$zone] ??= $country;
            }
        }

        foreach ((array) config('moneycoach.region.fallback_timezones', []) as $zone) {
            $identifiers[(string) $zone] ??= null;
        }

        ksort($identifiers);

        $zones = [];
        foreach ($identifiers as $zone => $country) {
            $offset = self::gmtOffset((string) $zone);
            $zones[] = [
                'key' => (string) $zone,
                'label' => $zone.' (GMT'.$offset.')',
                'country' => $country,
                'offset' => $offset,
            ];
        }

        return $zones;
    }

    /** @return list<string> */
    public static function timezoneKeys(): array
    {
        return array_column(self::timezones(), 'key');
    }

    /**
     * The default timezone for a country, e.g. IN => Asia/Kolkata.
     */
    public static function timezoneForCountry(?string $country): string
    {
        $zones = $country === null
            ? []
            : (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, strtoupper($country)) ?: []);

        return $zones[0] ?? 'UTC';
    }

    /** "+5:30" / "-4:00" for a timezone identifier. */
    public static function gmtOffset(string $timezone): string
    {
        $seconds = (new DateTimeZone($timezone))->getOffset(Carbon::now());
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('%s%d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * Turn a key => label map into the [{key, label}] shape the app expects.
     *
     * @param  array<string, string>  $map
     * @return list<array{key:string, label:string}>
     */
    private static function keyLabel(array $map): array
    {
        return array_values(array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label],
            array_keys($map),
            array_values($map),
        ));
    }
}
