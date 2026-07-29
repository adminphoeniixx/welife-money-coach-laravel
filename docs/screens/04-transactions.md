# 04 · Transactions 💸

**Screens:** `transactions` (tab 2) · `addExpense` · `addIncome`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/transactions` | List screen + segment (All/Income/Expense) badalne pe |
| 2 | POST | `/entries` | Add expense / add income save |
| 3 | PUT | `/entries/{id}` | Edit |
| 4 | DELETE | `/entries/{id}` | Swipe to delete |

## GET /transactions

**Query param — sirf ek:**

| Param | Values | Default |
|---|---|---|
| `type` | `all` · `income` · `expense` | `all` |

> ⚠️ **Ye endpoint sirf CURRENT MONTH ka data deta hai.**
> Koi `from` / `to` / `category` / `search` param **nahi** hai — month picker mat banao.
>
> - Purane mahine chahiye → `GET /reports?month=2026-06` ya `GET /calendar?month=2026-06`
> - Search chahiye → alag endpoint `GET /search?q=`

**Response:**

| Key | Kya hai |
|---|---|
| `filter` | applied type (string) |
| `categories` | dropdown ke liye income + expense category lists |
| `totals` | `income`, `expense`, `net` |
| `groups[]` | **din ke hisaab se grouped** entries — section headers seedha inhi se bana lo |

## POST / PUT body

| Field | Required | Note |
|---|---|---|
| `type` | ✅ | `income` / `expense` |
| `category` | ✅ | |
| `amount` | ✅ | rupees me (paise nahi) |
| `description` | ❌ | |
| `payee` | ❌ | |
| `method` | ❌ | UPI / Bank transfer / Credit Card etc |
| `occurred_on` | ✅ | `YYYY-MM-DD` |

## Demo data

Is mahine **18 entries · 14 day-groups**:

| | |
|---|---|
| Income | **₹97,000** (Salary ₹85,000 + Freelance ₹12,000) |
| Expense | **₹63,718** |
| Net | ₹33,282 |

Entries me Swiggy, BigBasket, Netflix, Amazon, HP Petrol, Jio, EMIs waghairah hain.

> Poora **6 mahine ka history (75 entries)** Insights aur Calendar screens pe dikhega,
> is screen pe nahi.
