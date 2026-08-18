# MoneyCoach — Screen-wise API docs

Har screen ki apni file hai: **kaun si API call karni hai**, response me **kya milta hai**,
aur demo account se **kya data dikhega**.

- **Base URL:** `https://projects-money-coch.rmsiry.easypanel.host/api`
- **Auth header:** `Authorization: Bearer <token>` + `Accept: application/json`
- **Demo login:** `test@example.com` / `password` · **Vault PIN:** `1234`
- **Partner login:** `priya@example.com` / `password` (family me role = partner)

Screen ids `../MoneyCoach-iOS-app.html` ke hain. Endpoint-level details (request bodies,
validation) `../MoneyCoach-API.md` me hain.

> Files me jo numbers likhe hain wo demo account ke **actual seeded values** hain — app me
> yahi dikhna chahiye. Alag dikhe to pehle API response check karo.

## Index

| # | File | Screens | Main API |
|---|---|---|---|
| 01 | [auth.md](01-auth.md) | welcome · login · register · forgot | `POST /auth/login` |
| 02 | [onboarding.md](02-onboarding.md) | onbCurrency · onbGoal · onbNotif | `GET+POST /onboarding` |
| 03 | [home.md](03-home.md) | home (tab 1) | **`GET /dashboard`** — bas yahi ek |
| 04 | [transactions.md](04-transactions.md) | transactions · addExpense · addIncome | `GET /transactions` |
| 05 | [debts.md](05-debts.md) | debts · addLoan · addCard · loanDetail · cardDetail · debtCoach | `GET /debts` |
| 06 | [net-worth.md](06-net-worth.md) | networth | `GET /net-worth` |
| 07 | [budgets-goals.md](07-budgets-goals.md) | budgets · emergency | `GET /planning` |
| 08 | [reminders.md](08-reminders.md) | reminders · addReminder · subs | `GET /reminders` |
| 09 | [family.md](09-family.md) | family | `GET /family` |
| 10 | [insights.md](10-insights.md) | insights · calendar · search · achievements · notifications | `GET /insights` |
| 11 | [challenges.md](11-challenges.md) | challenges | `GET /challenges` |
| 12 | [vault.md](12-vault.md) | vaultLock · vault | `GET /vault/gate` |
| 13 | [profile-settings.md](13-profile-settings.md) | profile · editProfile · setRegion · setNotif · dataPrivacy · legal | `GET /profile` |
| ✅ | [reported-issues.md](98-reported-issues.md) | — | **Reported issues ka status** + file/attachment URL contract |
| ⚠️ | [missing-apis.md](99-missing-apis.md) | coach (AI chat) · subscription · setSecurity | **API abhi nahi hai** |

## Common baatein

**Har response me `user` object same shape ka hota hai:**
`id, name, email, avatar_url, currency, locale, country, primary_goal, onboarded,
notifications_enabled, notification_prefs, has_vault_pin`

**Amounts rupees me aate hain** (paise/cents me nahi) — jaise `97000` ka matlab ₹97,000.

**Error codes:**

| Code | Matlab | App kya kare |
|---|---|---|
| 401 | Token invalid/expire | Login screen pe bhejo, token clear karo |
| 403 | Permission nahi (ya vault locked) | Vault ho to gate pe wapas, warna button hide karo |
| 404 | Record nahi mila / kisi aur ka hai | List refresh karo |
| 422 | Validation fail | `errors` object me field-wise messages hain, form pe dikhao |
| 429 | Rate limit (login, PIN, password = 6/min) | "Thodi der baad try karein" |
