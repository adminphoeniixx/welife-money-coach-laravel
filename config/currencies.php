<?php

/**
 * The currencies and countries MoneyCoach supports.
 *
 * A user picks a country at sign-up; that choice decides the currency and
 * locale used for every amount they see afterwards. There is no FX conversion
 * anywhere in the app — one user, one currency (see App\Support\Currency).
 *
 * Amounts are always stored as 1/100th of a major unit ("cents"), whatever the
 * currency, so the storage layer never has to care which currency a row is in.
 * Display rounding per currency (¥ has no decimals, KWD has three) is handled
 * by the formatter, not by the stored value.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Fallbacks
    |--------------------------------------------------------------------------
    |
    | Used when a user has not picked a country yet, and as the seed value for
    | the `users` table defaults.
    |
    */

    'default' => env('APP_DEFAULT_CURRENCY', 'INR'),

    'default_country' => env('APP_DEFAULT_COUNTRY', 'IN'),

    'default_locale' => env('APP_DEFAULT_LOCALE', 'en-IN'),

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    |
    | code => [name, symbol, locale]. `locale` is the formatting locale used
    | when the user has no locale of their own.
    |
    */

    'list' => [
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'locale' => 'en-AE'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => '$', 'locale' => 'es-AR'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'locale' => 'en-AU'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'locale' => 'en-BD'],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب', 'locale' => 'en-BH'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'locale' => 'pt-BR'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'locale' => 'en-CA'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'locale' => 'de-CH'],
        'CLP' => ['name' => 'Chilean Peso', 'symbol' => '$', 'locale' => 'es-CL'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'locale' => 'zh-CN'],
        'COP' => ['name' => 'Colombian Peso', 'symbol' => '$', 'locale' => 'es-CO'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč', 'locale' => 'cs-CZ'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'locale' => 'da-DK'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'E£', 'locale' => 'en-EG'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'locale' => 'en-IE'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'locale' => 'en-GB'],
        'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => 'GH₵', 'locale' => 'en-GH'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'locale' => 'en-HK'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'locale' => 'hu-HU'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'locale' => 'id-ID'],
        'ILS' => ['name' => 'Israeli Shekel', 'symbol' => '₪', 'locale' => 'he-IL'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'locale' => 'en-IN'],
        'JOD' => ['name' => 'Jordanian Dinar', 'symbol' => 'د.ا', 'locale' => 'en-JO'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'locale' => 'ja-JP'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'locale' => 'en-KE'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩', 'locale' => 'ko-KR'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'locale' => 'en-KW'],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'locale' => 'en-LK'],
        'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'د.م.', 'locale' => 'fr-MA'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'MX$', 'locale' => 'es-MX'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'locale' => 'ms-MY'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'locale' => 'en-NG'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'locale' => 'nb-NO'],
        'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => 'Rs', 'locale' => 'ne-NP'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'locale' => 'en-NZ'],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => 'ر.ع.', 'locale' => 'en-OM'],
        'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/', 'locale' => 'es-PE'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'locale' => 'en-PH'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'locale' => 'en-PK'],
        'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł', 'locale' => 'pl-PL'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'ر.ق', 'locale' => 'en-QA'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei', 'locale' => 'ro-RO'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽', 'locale' => 'ru-RU'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'ر.س', 'locale' => 'en-SA'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'locale' => 'sv-SE'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'locale' => 'en-SG'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'locale' => 'th-TH'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'locale' => 'tr-TR'],
        'TWD' => ['name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'locale' => 'zh-TW'],
        'TZS' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'locale' => 'en-TZ'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'locale' => 'uk-UA'],
        'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'USh', 'locale' => 'en-UG'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'locale' => 'en-US'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫', 'locale' => 'vi-VN'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'locale' => 'en-ZA'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 => [name, currency, locale]. Picking a country at
    | sign-up sets both the currency and the formatting locale.
    |
    */

    'countries' => [
        'AE' => ['name' => 'United Arab Emirates', 'currency' => 'AED', 'locale' => 'en-AE'],
        'AR' => ['name' => 'Argentina', 'currency' => 'ARS', 'locale' => 'es-AR'],
        'AT' => ['name' => 'Austria', 'currency' => 'EUR', 'locale' => 'de-AT'],
        'AU' => ['name' => 'Australia', 'currency' => 'AUD', 'locale' => 'en-AU'],
        'BD' => ['name' => 'Bangladesh', 'currency' => 'BDT', 'locale' => 'en-BD'],
        'BE' => ['name' => 'Belgium', 'currency' => 'EUR', 'locale' => 'nl-BE'],
        'BH' => ['name' => 'Bahrain', 'currency' => 'BHD', 'locale' => 'en-BH'],
        'BR' => ['name' => 'Brazil', 'currency' => 'BRL', 'locale' => 'pt-BR'],
        'CA' => ['name' => 'Canada', 'currency' => 'CAD', 'locale' => 'en-CA'],
        'CH' => ['name' => 'Switzerland', 'currency' => 'CHF', 'locale' => 'de-CH'],
        'CL' => ['name' => 'Chile', 'currency' => 'CLP', 'locale' => 'es-CL'],
        'CN' => ['name' => 'China', 'currency' => 'CNY', 'locale' => 'zh-CN'],
        'CO' => ['name' => 'Colombia', 'currency' => 'COP', 'locale' => 'es-CO'],
        'CZ' => ['name' => 'Czechia', 'currency' => 'CZK', 'locale' => 'cs-CZ'],
        'DE' => ['name' => 'Germany', 'currency' => 'EUR', 'locale' => 'de-DE'],
        'DK' => ['name' => 'Denmark', 'currency' => 'DKK', 'locale' => 'da-DK'],
        'EG' => ['name' => 'Egypt', 'currency' => 'EGP', 'locale' => 'en-EG'],
        'ES' => ['name' => 'Spain', 'currency' => 'EUR', 'locale' => 'es-ES'],
        'FI' => ['name' => 'Finland', 'currency' => 'EUR', 'locale' => 'fi-FI'],
        'FR' => ['name' => 'France', 'currency' => 'EUR', 'locale' => 'fr-FR'],
        'GB' => ['name' => 'United Kingdom', 'currency' => 'GBP', 'locale' => 'en-GB'],
        'GH' => ['name' => 'Ghana', 'currency' => 'GHS', 'locale' => 'en-GH'],
        'GR' => ['name' => 'Greece', 'currency' => 'EUR', 'locale' => 'el-GR'],
        'HK' => ['name' => 'Hong Kong', 'currency' => 'HKD', 'locale' => 'en-HK'],
        'HU' => ['name' => 'Hungary', 'currency' => 'HUF', 'locale' => 'hu-HU'],
        'ID' => ['name' => 'Indonesia', 'currency' => 'IDR', 'locale' => 'id-ID'],
        'IE' => ['name' => 'Ireland', 'currency' => 'EUR', 'locale' => 'en-IE'],
        'IL' => ['name' => 'Israel', 'currency' => 'ILS', 'locale' => 'he-IL'],
        'IN' => ['name' => 'India', 'currency' => 'INR', 'locale' => 'en-IN'],
        'IT' => ['name' => 'Italy', 'currency' => 'EUR', 'locale' => 'it-IT'],
        'JO' => ['name' => 'Jordan', 'currency' => 'JOD', 'locale' => 'en-JO'],
        'JP' => ['name' => 'Japan', 'currency' => 'JPY', 'locale' => 'ja-JP'],
        'KE' => ['name' => 'Kenya', 'currency' => 'KES', 'locale' => 'en-KE'],
        'KR' => ['name' => 'South Korea', 'currency' => 'KRW', 'locale' => 'ko-KR'],
        'KW' => ['name' => 'Kuwait', 'currency' => 'KWD', 'locale' => 'en-KW'],
        'LK' => ['name' => 'Sri Lanka', 'currency' => 'LKR', 'locale' => 'en-LK'],
        'MA' => ['name' => 'Morocco', 'currency' => 'MAD', 'locale' => 'fr-MA'],
        'MX' => ['name' => 'Mexico', 'currency' => 'MXN', 'locale' => 'es-MX'],
        'MY' => ['name' => 'Malaysia', 'currency' => 'MYR', 'locale' => 'ms-MY'],
        'NG' => ['name' => 'Nigeria', 'currency' => 'NGN', 'locale' => 'en-NG'],
        'NL' => ['name' => 'Netherlands', 'currency' => 'EUR', 'locale' => 'nl-NL'],
        'NO' => ['name' => 'Norway', 'currency' => 'NOK', 'locale' => 'nb-NO'],
        'NP' => ['name' => 'Nepal', 'currency' => 'NPR', 'locale' => 'ne-NP'],
        'NZ' => ['name' => 'New Zealand', 'currency' => 'NZD', 'locale' => 'en-NZ'],
        'OM' => ['name' => 'Oman', 'currency' => 'OMR', 'locale' => 'en-OM'],
        'PE' => ['name' => 'Peru', 'currency' => 'PEN', 'locale' => 'es-PE'],
        'PH' => ['name' => 'Philippines', 'currency' => 'PHP', 'locale' => 'en-PH'],
        'PK' => ['name' => 'Pakistan', 'currency' => 'PKR', 'locale' => 'en-PK'],
        'PL' => ['name' => 'Poland', 'currency' => 'PLN', 'locale' => 'pl-PL'],
        'PT' => ['name' => 'Portugal', 'currency' => 'EUR', 'locale' => 'pt-PT'],
        'QA' => ['name' => 'Qatar', 'currency' => 'QAR', 'locale' => 'en-QA'],
        'RO' => ['name' => 'Romania', 'currency' => 'RON', 'locale' => 'ro-RO'],
        'RU' => ['name' => 'Russia', 'currency' => 'RUB', 'locale' => 'ru-RU'],
        'SA' => ['name' => 'Saudi Arabia', 'currency' => 'SAR', 'locale' => 'en-SA'],
        'SE' => ['name' => 'Sweden', 'currency' => 'SEK', 'locale' => 'sv-SE'],
        'SG' => ['name' => 'Singapore', 'currency' => 'SGD', 'locale' => 'en-SG'],
        'TH' => ['name' => 'Thailand', 'currency' => 'THB', 'locale' => 'th-TH'],
        'TR' => ['name' => 'Turkey', 'currency' => 'TRY', 'locale' => 'tr-TR'],
        'TW' => ['name' => 'Taiwan', 'currency' => 'TWD', 'locale' => 'zh-TW'],
        'TZ' => ['name' => 'Tanzania', 'currency' => 'TZS', 'locale' => 'en-TZ'],
        'UA' => ['name' => 'Ukraine', 'currency' => 'UAH', 'locale' => 'uk-UA'],
        'UG' => ['name' => 'Uganda', 'currency' => 'UGX', 'locale' => 'en-UG'],
        'US' => ['name' => 'United States', 'currency' => 'USD', 'locale' => 'en-US'],
        'VN' => ['name' => 'Vietnam', 'currency' => 'VND', 'locale' => 'vi-VN'],
        'ZA' => ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en-ZA'],
    ],

];
