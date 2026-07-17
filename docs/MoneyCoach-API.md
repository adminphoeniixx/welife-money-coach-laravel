# MoneyCoach API

Mobile REST API for the MoneyCoach iOS app. Every screen in
`docs/MoneyCoach-iOS-app.html` maps to endpoints documented here.

- **Base URL:** `https://<your-host>/api` (local: `http://localhost:8000/api`)
- **Auth:** [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens (Bearer).
- **Format:** JSON in, JSON out. Send `Accept: application/json` on every request.
- **Money:** all amounts in requests/responses are in **major units (₹ rupees)**, e.g. `640.50`. Stored internally as integer paise.
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

Mutations return a human `message` (already emoji-flavoured where the UI celebrates, e.g. `🎉 Paid off!`).

---

## 1. Authentication & Onboarding

### `POST /auth/register` — _public_
Create an account, get a token. (register screen)
```json
{ "name": "Rahul Sharma", "email": "rahul@example.com",
  "password": "Password!234", "password_confirmation": "Password!234",
  "device_name": "Rahul's iPhone" }
```
→ `201` `{ "token": "1|abc...", "user": { ... } }`

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
Options + current answers → `{ currencies, goals[], user }`.

### `POST /onboarding`
(onbCurrency / onbGoal / onbNotif)
```json
{ "currency": "INR", "primary_goal": "get_out_of_debt",
  "notifications_enabled": true, "locale": "en-IN", "country": "IN" }
```
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
Full coach snapshot: `health` score, net worth, AI tips, priority payment, upcoming dues, spending, 6-month trend.

### `GET /coach?strategy=avalanche&extra=2000` — (debtCoach screen)
Debt payoff plan. `strategy` ∈ `avalanche · snowball`; `extra` = extra monthly payment (₹). → `{ "plan": { ... } }`

---

## 3. Transactions (income & expenses)

### `GET /transactions?type=all` — (transactions screen)
`type` ∈ `all · income · expense`. → `{ filter, categories, totals:{income,expense,net}, groups[] }`

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
→ `{ loan_categories[], summary:{total,monthly,avg_apr,count}, loans[], cards[], payoff_order[] }`

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
→ `{ budgets[], goals[] }` (goals include `emergency_fund` + `savings`).

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
→ `{ overdue[], upcoming[], subscriptions[], subscription_monthly }`

### `POST /bills` — (addReminder)
```json
{ "name": "Netflix", "kind": "subscription", "category": "Entertainment",
  "amount": 649, "due_date": "2026-07-20", "repeat": "monthly", "remind_days_before": 2 }
```
`kind` ∈ `bill · subscription · emi`; `repeat` ∈ `none · weekly · monthly · yearly`.
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
### `GET /vault?search=&category=all` — (vault screen) → `{ filters, categories[], total, documents[] }` _(requires unlocked)_
### `POST /vault/documents` — **multipart** _(requires unlocked)_
`category`, `title`, optional `side` (`front|back`), optional `notes`, `file` (jpg/png/webp/pdf ≤8 MB).
`category` ∈ `debit_atm_card · credit_card · aadhaar · pan · driving_license · passport · voter_id · insurance · vehicle_rc · loan · property · medical · passport_photo · other`.
### `GET /vault/documents/{id}/view` · `GET /vault/documents/{id}/download`
### `POST /vault/documents/{id}` — update metadata / replace file (multipart; `file` optional).
### `DELETE /vault/documents/{id}`

---

## 11. Settings & Profile

### `GET /profile` · `PUT /profile` — (editProfile) `{ "name": "...", "email": "..." }`
### `POST /profile/photo` — **multipart** `photo` (image ≤4 MB).
### `DELETE /profile/photo`
### `PUT /password` — (setSecurity) throttle 6/min
`{ "current_password": "...", "password": "...", "password_confirmation": "..." }` — keeps this device signed in, drops other tokens.
### `DELETE /account` — (dataPrivacy) `{ "password": "..." }` — permanent.

### `GET /settings/region` · `PUT /settings/region` — (setRegion)
`{ "currency": "INR", "locale": "en-IN", "country": "IN" }`
### `GET /settings/notifications` · `PUT /settings/notifications` — (setNotif)
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

Demo account (from `FinanceDemoSeeder`): **test@example.com / password**.
