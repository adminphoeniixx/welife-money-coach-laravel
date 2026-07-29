# 03 · Home 🏠

**Screen:** `home` (tab 1)

## API — sirf ek

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/dashboard` | Screen open + pull-to-refresh |

> **Poora home page isi ek response me aata hai.** Koi doosri API call mat karo.
> Koi query param nahi hai.

## Response ke blocks

| Key | Kya hai | Demo me kya dikhega |
|---|---|---|
| `currency` | `"INR"` | ₹ symbol |
| `user.name` | greeting ke liye | "Test User" |
| `health` | `score`, `status`, `tone`, `factors[]` | **68 · "Good" · teal** |
| `kpis` | 10 numbers (neeche detail) | Net worth **₹12,94,250** |
| `priority` | sabse mehnga debt + reason | **HDFC Millennia @ 42%** |
| `debt_free` | kab tak debt-free | **Sep 2027 · 1 yr 2 mo · 60%** |
| `emergency_fund` | `name, target, saved, progress` | ₹1,75,000 / ₹3,00,000 = **58.3%** |
| `goals[]` | baaki goals | Goa Vacation **37.5%** |
| `budgets[]` | `category, spent, limit, percent, exceeded` | 5 budgets |
| `upcoming[]` | agle bills / EMIs | HDFC Millennia (1 din) |
| `spending` | category-wise donut data | Loans, Food, Housing… |
| `trend` | 6 mahine ka income-vs-expense chart | 6 points |
| `tips[]` | coach ke short tips | |
| `debts[]` | chhoti debt list | 4 debts |

### `health.factors[]` — 5 factors, har ek me `label` / `points` / `max`

| Factor | Demo | Max |
|---|---|---|
| Savings rate | 30 | 30 |
| Debt burden | 17 | 25 |
| Credit utilisation | 9 | 20 |
| Emergency fund | 7 | 15 |
| On-time bills | 5 | 10 |

`tone` colour deta hai (`teal` / etc) — progress ring usi rang ka rakho.

### `kpis`

| Field | Demo |
|---|---|
| `net_worth` | 12,94,250 |
| `assets` | 16,77,850 |
| `liabilities` | 3,83,600 |
| `income` | 97,000 |
| `expense` | 63,718 |
| `savings` | 33,282 |
| `savings_rate` | 34 (%) |
| `total_debt` | 3,83,600 |
| `monthly_emi` | 31,100 |
| `emi_to_income` | 32 (%) |

### `priority` — "Pay this first" card

```json
{
  "name": "HDFC Millennia", "institution": "HDFC", "kind": "credit_card",
  "interest_rate": 42, "balance": 84000, "monthly_interest": 2940,
  "due_in_days": 1,
  "headline": "Pay down HDFC Millennia first — 42% is your most expensive debt",
  "reason": "It costs about Rs 2,940 in interest every month..."
}
```

`headline` aur `reason` seedha dikha do — backend ne already bana ke bheje hain.

### `budgets[]` — demo values

| Category | Spent / Limit | % | Exceeded |
|---|---|---|---|
| Entertainment | 5,149 / 4,000 | **129%** | ✅ **red dikhao** |
| Utilities | 5,169 / 6,000 | 86% | ❌ |
| Transport | 6,000 / 8,000 | 75% | ❌ |
| Food | 5,260 / 15,000 | 35% | ❌ |
| Shopping | 2,890 / 10,000 | 29% | ❌ |

## Notes

- `emergency_fund` **null** ho sakta hai agar user ne emergency fund goal banaya hi na ho —
  us case me "Set up emergency fund" empty state dikhao.
- `goals[]` me emergency fund **nahi** aata (wo alag key me hai) — double mat dikhana.
