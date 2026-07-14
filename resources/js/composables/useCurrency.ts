const inr = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
});

const inrCompact = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    notation: 'compact',
    maximumFractionDigits: 1,
});

/** Format a major-unit (rupee) amount as currency. */
export function useCurrency() {
    return {
        fmt: (n: number) => inr.format(n),
        fmtc: (n: number) => inrCompact.format(n),
    };
}
