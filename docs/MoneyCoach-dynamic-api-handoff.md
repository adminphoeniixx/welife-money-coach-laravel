# Backend handoff — dynamic API for the Flutter app

Response to *Backend Dynamic API Requirements*. Everything below is implemented,
covered by tests (`tests/Feature/Api/DynamicOptionsTest.php`,
`tests/Feature/MultiCurrencyTest.php`) and live in `docs/MoneyCoach-API.md`.

**Rule now enforced:** the app can delete every static/mock business value it
carries. Nothing it renders needs to be hardcoded.

---

## 1. One endpoint for every option list

`GET /api/meta/options` (authenticated) returns the whole catalogue in one call:

| Group | Keys |
|---|---|
| `transactions` | `income_categories[]`, `expense_categories[]`, `payment_methods[]` |
| `assets` | `types[{key,label}]` |
| `debts` | `loan_categories[]`, `kinds[]` |
| `planning` | `goal_types[{key,label}]`, `budget_categories[]` |
| `reminders` | `kinds[]`, `repeat_options[]`, `remind_days_before[]` |
| `vault` | `categories[{key,label}]` |
| `family` | `categories[]` |
| `onboarding` | `goals[{key,label}]` |
| `notifications` | `channels[{key,label}]` |
| `region` | `currencies[]`, `currency_details[]`, `countries[{key,label,currency,symbol,locale}]`, `timezones[{key,label}]`, `number_formats[{key,label}]` |

Every screen endpoint also repeats the lists it needs, so a screen never has to
wait on a second call. All of it is defined once in `config/moneycoach.php`
(+ `config/currencies.php` for region data) and served through `App\Support\Options`
— the web app reads the same source, so the two can no longer drift.

`{key,label}` entries mean: store the `key`, show the `label`.

## 2. Screen-by-screen fixes

**Dashboard `GET /dashboard`**
- `tips[]` now carries `message` (and keeps `text` with identical copy).
- `upcoming[].due_date` is `YYYY-MM-DD`; the display strings moved to `label` and `when`.
- Cards send `credit_limit` and `min_due` — `limit` is gone everywhere.
- `id` added on `user`, `priority`, `emergency_fund`, `goals[]`, `budgets[]`, `upcoming[]`, `debts[]`.
- `trend[]` gained `month: "2026-03"` next to the short `label`.
- `goals[]`/`emergency_fund` gained `type` and `target_date`.

**Transactions `GET /transactions`**
- `payment_methods[]` added; `categories.income/expense` are the clean master lists.
- Anything a user typed themselves is kept separately in `custom_categories[]`, so
  a custom budget stays spendable without polluting the master list.
- Groups are `{ date: "2026-08-11", label: "Tue, 11 Aug", total, items[] }`.

**Debts `GET /debts`**
- `kinds[]` added, `summary.progress` added.
- Cards: `credit_limit`, `min_due`, `due_day`, `utilisation`. Loans: `principal`,
  `emi`, `total_emis`, `emis_paid`, `remaining_emis`, `due_day`. Both always have `id`.

**Net worth `GET /net-worth`** — `types[]` from the shared catalogue; `summary.assets`
equals the sum of `accounts[].balance`; every account has an `id`.

**Planning `GET /planning`** — `budget_categories[]` (identical to the transaction
expense list) and `goal_types[]` added. A budget has the same keys everywhere:
`{ id, category, limit, spent, percent, exceeded }`, including in `POST`/`PUT` responses.

**Reminders `GET /reminders`** — `kinds[]`, `repeat_options[]`,
`remind_days_before_options[]` added. `due_date` is `YYYY-MM-DD`, with `label`/`when`
for display; `id`, `repeat`, `remind_days_before`, `overdue`, `paid_on` always present.
`one_time` is accepted as a repeat value and behaves like `none`.

**Insights `GET /insights`** — always 12 months; `by_month[].month` is `YYYY-MM`
with the short name in `label`.

**Search `GET /search`** — `count` added; the four result buckets are always present.

**Challenges** — presets are generated per user (in their currency);
`POST /challenges` and `POST /challenges/{id}/progress` return the full `challenge`.

**Vault `GET /vault`** — `categories[]` carries `{key,label,count}`; documents carry
`file_name`, numeric `size` in bytes (`size_label` for display) and ISO `created_at`.

**Family `GET /family`** — with no household the response still has every key
(`summary`, `expenses: []`, `budgets: []`, `can_manage`, `my_role`, `categories`).

**Profile `GET /api/profile`** — returns `{ profile, user }` (identical objects;
read `profile`). New fields: `phone`, `timezone`, `timezone_label`, `number_format`,
`currency_symbol`. `notification_prefs` always lists every channel.

**Settings** — `GET/PUT /settings/region` handles `country`, `currency`, `locale`,
`timezone`, `number_format` and serves all four option lists.
`GET/PUT /settings/notifications` returns `available_channels[{key,label}]`.

**Onboarding `GET /onboarding`** — goals, currencies, countries, timezones and
number formats all come from the backend.

## 3. Multi-currency (not only India)

Sign-up now takes a `country`:

```
POST /api/auth/register   { name, email, password, password_confirmation, country: "AE" }
GET  /api/auth/regions    → countries + currencies for the picker (public)
```

The country sets currency, locale, timezone and number format together; the user
can change it later from `PUT /api/settings/region`. 55 currencies / 65 countries
(and 204 timezones) are supported — see `config/currencies.php`.

There is **no FX conversion** anywhere: one user, one currency. Amounts are stored
and returned as plain numbers; `currency` and `currency_symbol` say what they mean.
Changing currency relabels the user's existing records — it never rewrites amounts.

## 4. Global rules

- **Amounts** — numbers in major units (`640.5`), never formatted strings.
- **Dates** — `YYYY-MM-DD` in date fields, ISO-8601 in timestamps; display copy in
  `label` / `when` / `*_label`.
- **Errors** — Laravel's standard shape everywhere:
  `{ "message": "...", "errors": { "field": ["..."] } }` with `422` on validation.
- **Mutations** return the updated resource (`entry`, `budget`, `goal`, `debt`,
  `bill`, `challenge`, `asset`, `profile`), never `{}`. Deletes return
  `deleted_id` plus their screen's refreshed aggregate:
  entries → `totals`, assets & debts → `summary`, reminders → `subscription_monthly`,
  and every family mutation returns the **whole family screen**.
- **Lists** are `[]` when empty, never `null`, and documented keys are always present.
- **Ids** on everything editable.

## 5. Removing the `Codex…` test records

Those rows are data on the live database, not fixtures in the codebase — nothing
in the repo creates them. Clean them with:

```bash
php artisan moneycoach:purge-test-data              # dry run: reports what matches
php artisan moneycoach:purge-test-data --force      # actually deletes
php artisan moneycoach:purge-test-data --pattern="Codex" --user=42 --force
```

It scans the visible fields of entries, debts, bills, budgets, goals, assets,
documents and challenges. Run the dry run first and check the counts.

## 6. Note on the sample payloads

Two things in the requirements doc were deliberately not copied verbatim:

- `income_methods: ["HDFC Savings", …]` — those are one account's names, not an
  option list, so they are not served as options. The user's accounts come from
  `GET /net-worth` → `accounts[]` if you want them in a picker.
- `region.currencies` is served as an array of codes (`["INR","USD",…]`) as
  specified, with `currency_details[]` alongside when you need the symbol/name.
