# 13 · Profile & Settings ⚙️

**Screens:** `profile` (tab 5) · `editProfile` · `setRegion` · `setNotif` · `dataPrivacy` ·
`legalPrivacy` · `legalTerms`

## APIs

| # | Method | Endpoint | Screen |
|---|---|---|---|
| 1 | GET | `/profile` | profile |
| 2 | PUT | `/profile` | editProfile save |
| 3 | POST | `/profile/photo` | Photo upload (multipart) |
| 4 | DELETE | `/profile/photo` | Photo remove |
| 5 | PUT | `/password` | Password change — **6/min** |
| 6 | DELETE | `/account` | Delete account |
| 7 | GET | `/settings/region` | setRegion |
| 8 | PUT | `/settings/region` | setRegion save |
| 9 | GET | `/settings/notifications` | setNotif |
| 10 | PUT | `/settings/notifications` | setNotif save |
| 11 | GET | `/settings/data-privacy` | dataPrivacy |
| 12 | GET | `/settings/data-privacy/export` | "Download my data" |
| 13 | GET | `/legal/privacy` · `/legal/terms` | legalPrivacy / legalTerms — **public** |
| 14 | POST | `/auth/logout` · `/auth/logout-all` | Logout |

## Profile

`GET /profile` → `{ "user": {...} }`
`PUT /profile` body: `name`, `email`
`POST /profile/photo` — multipart, field `photo` (image)

`user.avatar_url` **null** ho to initials wala circle dikhao.

## Region — `/settings/region`

**GET:** `currencies` (8 options), `currency`, `locale`, `country`
**PUT:** `currency` (✅), `locale`, `country`

## Notifications — `/settings/notifications`

**GET:**
```json
{
  "notifications_enabled": true,
  "channels": {
    "bill_reminders": true, "budget_alerts": true, "goal_milestones": true,
    "weekly_summary": false, "debt_tips": true
  }
}
```

**PUT:** `notifications_enabled` (✅) + `channels.<key>` har toggle ke liye.

**5 channels:** `bill_reminders` · `budget_alerts` · `goal_milestones` · `weekly_summary` · `debt_tips`

> Master toggle OFF ho to sab channels greyed-out dikha do.

## Data & Privacy — `/settings/data-privacy`

| Key | Kya hai |
|---|---|
| `counts` | har type ka count — transactions, debts, assets, budgets, goals, reminders, documents, challenges |
| `account_created` | ISO date |
| `privacy_url` · `terms_url` | legal pages ke links |

`/settings/data-privacy/export` → poora data **JSON attachment** ke roop me (GDPR export).

## Delete account

`DELETE /account` — irreversible. Confirmation dialog zaroori. Success pe token clear
karke welcome screen pe bhejo.

## Demo data

| | |
|---|---|
| Name / email | Test User · test@example.com |
| Region | INR · en-IN · IN |
| Primary goal | Get out of debt |
| Notifications | master **ON** · **weekly_summary OFF**, baaki 4 ON |
| `avatar_url` | **null** — photo set nahi ki, initials dikhega (expected) |

**Data & privacy counts:** transactions 75 · debts 4 · assets 6 · budgets 9 · goals 2 ·
reminders 10 · documents 12 · challenges 3
