# 10 · Insights, Calendar, Search, Reports 📊

**Screens:** `insights` (tab 4) · `calendar` · `search` · `achievements` · `notifications`

## APIs

| # | Method | Endpoint | Screen |
|---|---|---|---|
| 1 | GET | `/insights` | insights — yearly analytics |
| 2 | GET | `/calendar` | calendar |
| 3 | GET | `/search?q=` | search |
| 4 | GET | `/achievements` | achievements |
| 5 | GET | `/notifications` | notifications |
| 6 | GET | `/reports` | monthly report |
| 7 | GET | `/reports/export` | CSV download button |

## Query params

| Endpoint | Param | Format | Default |
|---|---|---|---|
| `/insights` | `year` | `2026` | current year |
| `/calendar` | `month` | `2026-07` | current month |
| `/reports` · `/reports/export` | `month` | `2026-07` | current month |
| `/search` | `q` | text | khaali = empty results |

> **`prev` / `next` fields ready-made aate hain** — arrow buttons pe seedha wahi value
> agli request me bhej do, khud date calculate mat karo.

## Response fields

**`/insights`** → `year`, `prev`, `next`, `summary`, `by_month[]`, `by_category[]`
**`/calendar`** → `month`, `label`, `prev`, `next`, `weekdays`, `days[]`
**`/reports`** → `month`, `label`, `prev`, `next`, `summary`, `by_category[]`, `user_name`
**`/search`** → `query`, `results` { `transactions`, `debts`, `bills`, `assets` }, `count`
**`/achievements`** → `achievements[]` (har ek me `earned` bool), `earned`, `total`
**`/notifications`** → `notifications[]`

`/reports/export` **CSV file** deta hai (JSON nahi) — share sheet me do.

## Demo data

**Insights (6 mahine):**

| | |
|---|---|
| Income | **₹5,46,000** |
| Expense | ₹2,59,658 |
| Net | ₹2,86,342 |
| Savings rate | **52%** |
| Avg monthly savings | ₹47,723 |
| Transactions | 75 |

**Calendar:** entries 1, 3, 4, 5, 6, 8, 9, 10, 12, 14, 16 tarikh pe (dots dikhne chahiye)

**Reports (is mahine):** income ₹97,000 · expense ₹63,718 · net ₹33,282 · 18 transactions

**Achievements — 4/8 earned:**

| Achievement | Status |
|---|---|
| First Step | ✅ |
| In the Green | ✅ |
| Big Saver | ✅ |
| Vault Keeper | ✅ |
| Debt Slayer | ❌ |
| Emergency Ready | ❌ |
| Goal Getter | ❌ |
| Card Master | ❌ |

**Notifications — 5:** Payment overdue · Due soon · High card usage · Budget exceeded · On track

**Search:** `q=Netflix` → `count: 7` — 6 transactions (6 mahine ka Netflix kharcha) +
1 bill (Netflix subscription reminder).
