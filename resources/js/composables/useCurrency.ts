import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type SharedCurrency = {
    currency: string;
    locale: string;
    symbol: string;
};

const FALLBACK: SharedCurrency = {
    currency: 'INR',
    locale: 'en-IN',
    symbol: '₹',
};

/** Cache formatters per currency+locale — building them is not free. */
const cache = new Map<string, Intl.NumberFormat>();

function formatter(locale: string, currency: string, compact: boolean) {
    const key = `${locale}|${currency}|${compact}`;
    const hit = cache.get(key);

    if (hit) {
        return hit;
    }

    let built: Intl.NumberFormat;

    try {
        built = new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            ...(compact
                ? { notation: 'compact' as const, maximumFractionDigits: 1 }
                : { maximumFractionDigits: 0 }),
        });
    } catch {
        // An unknown locale/currency pair must never break a page.
        built = new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: FALLBACK.currency,
            maximumFractionDigits: compact ? 1 : 0,
        });
    }

    cache.set(key, built);

    return built;
}

/**
 * Format major-unit amounts in the signed-in user's currency.
 *
 * The currency comes from the shared Inertia props, which follow whatever
 * country the user picked at sign-up (or later changed in settings). There is
 * no conversion anywhere: amounts are already stored in the user's currency.
 */
export function useCurrency() {
    const page = usePage();

    const region = computed<SharedCurrency>(() => {
        const shared = page.props.currency as
            Partial<SharedCurrency> | undefined;

        return {
            currency: shared?.currency || FALLBACK.currency,
            locale: shared?.locale || FALLBACK.locale,
            symbol: shared?.symbol || FALLBACK.symbol,
        };
    });

    return {
        /** ISO code, e.g. "USD". */
        code: computed(() => region.value.currency),
        /** Bare symbol for input labels, e.g. "$". */
        symbol: computed(() => region.value.symbol),
        fmt: (n: number) =>
            formatter(region.value.locale, region.value.currency, false).format(
                n,
            ),
        fmtc: (n: number) =>
            formatter(region.value.locale, region.value.currency, true).format(
                n,
            ),
    };
}
