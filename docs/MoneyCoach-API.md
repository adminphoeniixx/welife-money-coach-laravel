# MoneyCoach API

Mobile REST API for the MoneyCoach iOS app. Every screen in
`docs/MoneyCoach-iOS-app.html` maps to endpoints documented here.

- **Base URL:** `https://<your-host>/api` (local: `http://localhost:8000/api`)
- **Auth:** [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens (Bearer).
- **Format:** JSON in, JSON out. Send `Accept: application/json` on every request.
- **Money:** all amounts in requests/responses are plain **numbers in major units**, e.g. `640.50` — never formatted strings. Stored internally as integer minor units.
- **Currency:** each user has exactly one, chosen from their country at sign-up. Nothing is converted; `user.currency` / `user.currency_symbol` say what the numbers mean.
- **Dates:** `YYYY-MM-DD` in date fields, ISO-8601 in timestamps. Display copy is always a separate key (`label`, `when`, `display_date`).
- **Options:** every dropdown list comes from the API — see `GET /meta/options`. The app must hardcode none of them.
- **Ids:** anything the app can edit, delete or mark paid carries an `id`.
- **Empty lists** are `[]`, never `null`, and every documented key is present even when empty.
- **Auth header:** `Authorization: Bearer <token>` for every endpoint except the public ones below.

## Conventions

| Situation | HTTP status | Body |
|-----------|-------------|------|
| Success (read/update) | `200` | resource / payload |
| Created | `201` | `{ "message": "...", "<resource>": {...} }` |
| Validation failed | `422` | `{ "message": "...", "errors": { "field": ["..."] } }` |
| Not authenticated | `401` | `{ "message": "Unauthenticated." }` |
| Not the owner / forbidden | `403` | `{ "message": "..." }` |
| Not found | `404` | `{ "message": "..." }` |
| Vault locked | `423` | `{ "message": "Vault is locked.", "vault": { "locked": true, "reason": "locked|no_pin" } }` |
| Rate limited | `429` | `{ "message": "Too Many Attempts." }` |

Mutations return a human `message` (already emoji-flavoured where the UI celebrates, e.g. `🎉 Paid off!`)
plus fresh data: creates/updates carry the resource, deletes carry `deleted_id` and
the screen's refreshed aggregate (`totals`, `summary`, `subscription_monthly`).
Family mutations return the entire family screen.

---

## 1. Authentication & Onboarding

### `GET /auth/regions` — _public_
Countries + currencies for the register screen's country picker.
→ `{ countries[{key,label,currency,symbol,locale}], currencies[], currency_details[],
timezones[], number_formats[], default_country, default_currency }`

### `POST /auth/register` — _public_
Create an account, get a token. (register screen)
```json
{ "name": "Rahul Sharma", "email": "rahul@example.com",
  "password": "Password!234", "password_confirmation": "Password!234",
  "country": "IN", "device_name": "Rahul's iPhone" }
```
`country` is optional but recommended: it sets the currency, locale, timezone and
number format for the account. Without it the app default (`IN` / `INR`) is used.
→ `201` `{ "token": "1|abc...", "user": { ... } }`

### `GET /meta/options`
Every picker list in one call — cache it at launch.
→ `{ transactions:{income_categories[],expense_categories[],payment_methods[]},
assets:{types[]}, debts:{loan_categories[],kinds[]},
planning:{goal_types[],budget_categories[]},
reminders:{kinds[],repeat_options[],remind_days_before[]},
vault:{categories[]}, family:{categories[]}, onboarding:{goals[]},
notifications:{channels[]},
region:{currencies[],currency_details[],countries[],timezones[],number_formats[]} }`
Entries shaped `{key,label}` are meant for pickers: store the `key`, show the `label`.

### `POST /auth/login` — _public_ · throttle 6/min
```json
{ "email": "rahul@example.com", "password": "Password!234", "device_name": "Rahul's iPhone" }
```
→ `200` `{ "token": "2|xyz...", "user": { ... } }` · `422` on bad credentials / suspended.

### `POST /auth/forgot-password` — _public_ · throttle 6/min
`{ "email": "rahul@example.com" }` → always `200` (does not leak whether the email exists).

### `POST /auth/reset-password` — _public_ · throttle 6/min
`{ "token": "<emailed>", "email": "...", "password": "...", "password_confirmation": "..." }`

### `GET /user`
Current user (used on app launch). → `{ "user": { ... } }`

### `POST /auth/logout`
Revoke the current device's token.

### `POST /auth/logout-all`
Revoke every token (all devices).

### `GET /onboarding`
Options + current answers → `{ currencies[], currency_details[], countries[], timezones[], number_formats[], goals[], user }`.
Every list here is authoritative — the app must not hardcode any of it.

### `POST /onboarding`
(onbCurrency / onbGoal / onbNotif)
```json
{ "country": "IN", "currency": "INR", "primary_goal": "get_out_of_debt",
  "notifications_enabled": true, "locale": "en-IN",
  "timezone": "Asia/Kolkata", "number_format": "indian" }
```
Only `notifications_enabled` is required. Sending `country` alone is enough —
the currency, locale, timezone and number format all follow from it.
`primary_goal` ∈ `get_out_of_debt · build_emergency_fund · save_for_goal · track_spending · grow_wealth`.

**User object** (returned by auth/profile/settings):
```json
{ "id": 1, "name": "Rahul Sharma", "email": "rahul@example.com", "avatar_url": null,
  "currency": "INR", "locale": "en-IN", "country": null, "primary_goal": null,
  "onboarded": false, "notifications_enabled": true, "notification_prefs": null,
  "has_vault_pin": false, "has_household": false }
```

---

## 2. Dashboard & Coach

### `GET /dashboard` — (home screen)
Full coach snapshot, entirely derived from the user's own data:
`{ currency, currency_symbol, user:{id,name}, health:{score,status,tone,factors[]},
kpis{}, priority:{id,...}, debt_free{}, emergency_fund:{id,...}, goals[{id,...}],
budgets[{id,...}], upcoming[{id, due_date:"2026-08-16", label, when, repeat, remind_days_before}],
spending:{total,slices[]}, trend[{month:"2026-03",label,income,expense}],
tips[{tone,icon,message,text}], debts[{id,...,credit_limit,min_due,utilisation}] }`
Tips carry both `message` and `text` with identical copy; card fields use the same
names as `GET /debts`.

### `GET /coach?strategy=avalanche&extra=2000` — (debtCoach screen)
Debt payoff plan. `strategy` ∈ `avalanche · snowball`; `extra` = extra monthly payment (₹). → `{ "plan": { ... } }`

---

## 3. Transactions (income & expenses)

### `GET /transactions?type=all` — (transactions screen)
`type` ∈ `all · income · expense`.
→ `{ filter, categories:{income[],expense[]}, custom_categories[], payment_methods[],
totals:{income,expense,net}, groups[] }`
`categories` are the clean master lists; `custom_categories` holds anything extra this
user has typed before. Each group is `{ date: "2026-08-11", label: "Tue, 11 Aug", total, items[] }`
— `date` is always `YYYY-MM-DD`.

### `POST /entries` — (addExpense / addIncome)
```json
{ "type": "expense", "amount": 640.50, "category": "Food",
  "description": "Swiggy", "payee": "Swiggy", "method": "UPI",
  "occurred_on": "2026-07-10" }
```
`type` ∈ `income · expense`. Only `type`, `amount`, `occurred_on` required.

### `PUT /entries/{id}` — same body as store.
### `DELETE /entries/{id}`

---

## 4. Debts (loans + credit cards)

### `GET /debts` — (debts screen)
→ `{ loan_categories[], kinds[], summary:{total,monthly,avg_apr,count,progress}, loans[], cards[], payoff_order[] }`
Cards carry `credit_limit` and `min_due` (never `limit`); loans carry `principal`,
`emi`, `total_emis`, `emis_paid`, `due_day`. Every debt has an `id`.

### `GET /debts/{id}` — (loanDetail / cardDetail screens)
A single loan/card with full detail + **payment history**.
→ `{ debt: {...same shape as list...}, payments: [ { id, amount, balance_after, emi_number, paid_on, label } ] }`
`payments` is newest-first; `label` is like `"Jun 2026"`, `emi_number` is null for cards.

### `POST /debts` — (addLoan / addCard)
```json
{ "name": "HDFC Home Loan", "institution": "HDFC", "kind": "loan",
  "category": "home", "interest_rate": 8.5, "balance": 2500000,
  "principal": 3000000, "emi": 24500, "total_emis": 240, "emis_paid": 60,
  "due_day": 5 }
```
Credit-card extras: `"kind": "credit_card", "credit_limit": 200000, "min_due": 2500`.
`category` ∈ `home · vehicle · gold · personal · education · business · custom`.
To attach files on create, send **multipart** with `documents[]` (jpg/png/webp/pdf, ≤8 MB, ≤10).

### `PUT /debts/{id}` — same body.
### `DELETE /debts/{id}`
### `POST /debts/{id}/payment` — `{ "amount": 24500 }` → records an EMI, closes at ₹0 or tenure end.
### `POST /debts/{id}/documents` — **multipart** `documents[]` (≥1).
### `GET /debt-documents/{id}/view` — streams decrypted file (inline).
### `GET /debt-documents/{id}/download`
### `DELETE /debt-documents/{id}`

---

## 5. Net Worth / Assets

### `GET /net-worth` — (networth screen)
→ `{ types[], summary:{assets,liabilities,net_worth}, breakdown[], accounts[] }`

### `POST /assets`
```json
{ "name": "HDFC Savings", "type": "bank", "balance": 125000, "note": "Salary account" }
```
`type` ∈ `bank · cash · gold · fixed_deposit · mutual_fund · stocks · property · other`.

### `PUT /assets/{id}` · `DELETE /assets/{id}`

---

## 6. Budgets, Goals & Emergency Fund

### `GET /planning` — (budgets / emergency screens)
→ `{ budget_categories[], goal_types[], budgets[], goals[] }` (goals include `emergency_fund` + `savings`).
`budget_categories` is the same master list as the transaction expense categories.
Budgets always carry `{ id, category, limit, spent, percent, exceeded }` — in the list
and in every mutation response.

### `POST /budgets` — `{ "category": "Food", "limit": 8000 }` (unique per category).
### `PUT /budgets/{id}` · `DELETE /budgets/{id}`

### `POST /goals`
```json
{ "name": "Emergency Fund", "type": "emergency_fund",
  "target": 300000, "saved": 50000, "target_date": "2026-12-31" }
```
`type` ∈ `emergency_fund · savings`.
### `PUT /goals/{id}` · `DELETE /goals/{id}`
### `POST /goals/{id}/contribute` — `{ "amount": 5000 }` → celebrates on reaching target.

---

## 7. Reminders (bills, EMIs, subscriptions)

### `GET /reminders` — (reminders / subs screens)
→ `{ kinds[], repeat_options[], remind_days_before_options[], overdue[], upcoming[], subscriptions[], subscription_monthly }`
Each reminder is `{ id, name, kind, category, amount, due_date: "2026-08-16",
label, when, repeat, remind_days_before, days, overdue, status, paid_on }`.

### `POST /bills` — (addReminder)
```json
{ "name": "Netflix", "kind": "subscription", "category": "Entertainment",
  "amount": 649, "due_date": "2026-07-20", "repeat": "monthly", "remind_days_before": 2 }
```
`kind` ∈ `bill · subscription · emi`; `repeat` ∈ `none · one_time · weekly · monthly · yearly`
(`none` and `one_time` both mean "does not repeat"). Read both lists from
`GET /meta/options` rather than hardcoding them.
### `PUT /bills/{id}` · `DELETE /bills/{id}`
### `POST /bills/{id}/paid` — marks paid; recurring bills roll forward to the next due date.

---

## 8. Family Finance Mode

### `GET /family` — (family screen)
→ `household: null` when not in a family, else `{ household:{members,invitations}, summary, expenses[], budgets[], can_manage, my_role }`.

### `POST /family` — `{ "name": "Sharma Family" }` (one family per user).
### `DELETE /family` — owner only; detaches shared items back to personal.
### `POST /family/leave` — members only (owner must delete instead).
### `POST /family/invite` — owner only. `{ "email": "wife@example.com", "role": "partner" }` → returns shareable `link` + `token`. `role` ∈ `partner · member`.
### `DELETE /family/invitations/{id}` — cancel a pending invite.
### `GET /family/join/{token}` — invite details (accept screen).
### `POST /family/join/{token}` — accept (email must match the invite).
### `DELETE /family/members/{userId}` — owner removes a member.
### `POST /family/expenses` — `{ "category": "Groceries", "amount": 2500, "description": "Big Bazaar", "occurred_on": "2026-07-10" }`
### `DELETE /family/expenses/{entryId}` — logger or owner only.
### `POST /family/budgets` — owner only. `{ "category": "Groceries", "limit": 15000 }`
### `DELETE /family/budgets/{budgetId}`

---

## 9. Insights

### `GET /insights?year=2026` — (insights screen)
Yearly analytics. → `{ year, prev, next, summary:{income,expense,net,savings_rate,avg_monthly_savings,avg_monthly_expense,count}, by_month[12]:{month,income,expense}, by_category[] }`
### `GET /calendar?month=2026-07` — (calendar screen) → `{ month, label, prev, next, weekdays[], days[42] }`
### `GET /search?q=netflix` — (search screen) → `{ query, results:{transactions,debts,bills,assets}, count }`
### `GET /achievements` — (achievements screen) → `{ achievements[], earned, total }`
### `GET /notifications` — (notifications screen) → `{ notifications[] }`
### `GET /reports?month=2026-07` — (reports screen) → `{ month, summary:{income,expense,net,savings_rate,count}, by_category[] }`
### `GET /reports/export?month=2026-07` — streams a CSV download.
### `GET /challenges` — (challenges screen) → `{ active[], presets[] }`
Presets are generated per user and written in their currency. Joining or logging
progress returns the full `challenge` object, not just an id.
### `POST /challenges` — `{ "key": "<preset key>" }` (join a preset).
Preset keys: `save_5000 · save_10000 · no_spend_7 · cut_fuel_10 · cut_dining_3000` (get the current list from `GET /challenges` → `presets[]`).
### `POST /challenges/{id}/progress` — `{ "amount": 500 }`
### `DELETE /challenges/{id}`

---

## 10. Secure Documents Vault

PIN-gated. Unlock is tracked per-token with a **15-minute sliding window**; a
locked/expired vault returns **`423`** on the document routes — send the user
back to the lock screen.

### `GET /vault/gate` — (vaultLock screen) → `{ mode:"setup|unlock", has_pin, unlocked }`
### `POST /vault/pin` — set/change PIN (also unlocks).
```json
{ "pin": "1234", "pin_confirmation": "1234", "current_pin": "0000" }
```
`current_pin` required only when changing an existing PIN. `pin` = 4–6 digits.
### `POST /vault/unlock` — `{ "pin": "1234" }` · throttle 6/min.
### `POST /vault/lock`
### `GET /vault?search=&category=all` — (vault screen) → `{ filters, categories[{key,label,count}], total, documents[] }` _(requires unlocked)_
Documents carry `{ id, title, category, category_label, side, file_name, mime_type,
size (bytes), size_label, notes, created_at (ISO-8601), uploaded_at }`.
### `POST /vault/documents` — **multipart** _(requires unlocked)_
`category`, `title`, optional `side` (`front|back`), optional `notes`, `file` (jpg/png/webp/pdf ≤8 MB).
`category` ∈ `debit_atm_card · credit_card · aadhaar · pan · driving_license · passport · voter_id · insurance · vehicle_rc · loan · property · medical · passport_photo · other`.
### `GET /vault/documents/{id}/view` · `GET /vault/documents/{id}/download`
### `POST /vault/documents/{id}` — update metadata / replace file (multipart; `file` optional).
### `DELETE /vault/documents/{id}`

---

## 11. Settings & Profile

### `GET /profile` — → `{ profile: {...}, user: {...} }` (identical objects; read `profile`).
The profile carries `id, name, email, phone, avatar_url, currency, currency_symbol,
locale, country, timezone, timezone_label, number_format, primary_goal, onboarded,
notifications_enabled, notification_prefs, has_vault_pin, has_household`.
### `PUT /profile` — (editProfile) `{ "name": "...", "email": "...", "phone": "+91..." }` → returns the fresh profile.
### `POST /profile/photo` — **multipart** `photo` (image ≤4 MB).
### `DELETE /profile/photo`
### `PUT /password` — (setSecurity) throttle 6/min
`{ "current_password": "...", "password": "...", "password_confirmation": "..." }` — keeps this device signed in, drops other tokens.
### `DELETE /account` — (dataPrivacy) `{ "password": "..." }` — permanent.

### `GET /settings/region` — (setRegion)
→ current `{ currency, symbol, locale, country, timezone, number_format }` plus the
options `{ currencies[], currency_details[], countries[], timezones[], number_formats[] }`.

### `PUT /settings/region`
`{ "country": "AE", "currency": "AED", "locale": "en-AE", "timezone": "Asia/Dubai", "number_format": "international" }`
Every field is optional. A `country` on its own switches the currency, locale,
timezone and number format together. Amounts are **not** converted — changing
currency only changes what the numbers are labelled as.
### `GET /settings/notifications` · `PUT /settings/notifications` — (setNotif)
Both return `{ notifications_enabled, channels{}, available_channels[{key,label}] }`;
render the toggles from `available_channels`.
```json
{ "notifications_enabled": true,
  "channels": { "bill_reminders": true, "budget_alerts": true,
    "goal_milestones": true, "weekly_summary": false, "debt_tips": true } }
```
### `GET /settings/data-privacy` — (dataPrivacy) → counts of stored data + legal URLs.
### `GET /settings/data-privacy/export` — full JSON export of the user's data (attachment).

### `GET /legal/privacy` · `GET /legal/terms` — _public_ (legalPrivacy / legalTerms screens).

---

## Quick start (cURL)

```bash
# 1. Login → grab the token
curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' \
  -d 'email=test@example.com&password=password' | jq -r .token

# 2. Use it
curl -s http://localhost:8000/api/dashboard \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer <TOKEN>" | jq
```

## Screen-wise docs (app developers ke liye)

Kaun si screen pe kaun si API call karni hai — har screen ki alag file:
**[`docs/screens/`](screens/README.md)**

## Demo data

`php artisan db:seed` populates every authenticated endpoint above with realistic
sample data, so each one can be called and inspected without creating anything first.
Re-running the seeders rebuilds the data from scratch (ids change, nothing duplicates).

| Account | Login | Role |
| --- | --- | --- |
| Rahul Sharma | **test@example.com / password** | main demo user |
| Priya Sharma | **priya@example.com / password** | partner in the same family |

**Vault PIN: `1234`** — `POST /vault/unlock` with it before calling the vault endpoints.

What gets seeded (`FinanceDemoSeeder`, `VaultDemoSeeder`, `FamilyDemoSeeder`):

- **Onboarding / settings** — onboarding completed, INR / en-IN / IN, primary goal
  `get_out_of_debt`, notification channels with `weekly_summary` deliberately off.
- **Transactions** — 6 months of income + expenses, so trends, calendar, search,
  insights and reports all have history.
- **Debts** — 2 loans + 2 credit cards, each with 6 months of payment history and
  encrypted attachments (`GET /debts/{debt}` shows both).
- **Assets** — 6 accounts (bank, cash, gold, FD, mutual fund, stocks) for net worth.
- **Budgets & goals** — 5 category budgets (Entertainment intentionally over limit),
  an emergency fund and a savings goal, both part-funded.
- **Reminders** — 10 bills / EMIs / subscriptions, including one overdue.
- **Challenges** — 2 active (one nearly done), 1 completed, 2 presets left to join.
- **Vault** — 12 documents across categories, including a front/back pair.
- **Family** — the "Sharma Family" household: 2 members, 1 pending invite, this
  month's shared expenses and 4 family budgets (Education over limit).

> **Seeding a remote database:** the vault and debt attachments are encrypted onto
> the app's `local` disk with the app's `APP_KEY`. Run the seeders **on the same
> machine that serves the app** — seeding a remote DB from a laptop leaves the
> document rows pointing at blobs the server cannot read, and every
> view/download returns "This document could not be decrypted."
