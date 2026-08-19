# MoneyCoach API — Subscriptions & Reminders (19 Aug 2026)

App developer ke subscriptions wale sawaalon ka jawab. Ye file **self-contained** hai —
isko akela bhej sakte ho.

**Base URL:** `https://projects-money-coch.rmsiry.easypanel.host/api`
**Auth:** `Authorization: Bearer <token>` + `Accept: application/json`
**Endpoint:** `GET /reminders`

---

## TL;DR

| Sawaal | Jawab |
|---|---|
| Subscriptions kaise aa rahi hain? | 🟢 Dedicated top-level `subscriptions` array me — **sirf wahin** (Option A ke baad) |
| `kind: "subscription"` hai? | 🟢 Haan, har item pe, exact lowercase `"subscription"` |
| `subscription_monthly` aata hai? | 🟢 Aata tha — par **math galat tha**, ab fix hai |
| Per-subscription reminder on/off? | 🔴 **Backend me support nahi hai.** Frontend se hataya, sahi kiya |
| List overlap? | 🟢 **Option A implement ho gaya** — subscriptions ab sirf `subscriptions` array me |

---

## 🟢 Part 1 — `subscriptions` array

`GET /reminders` ka pura top-level shape:

```json
{
  "kinds": [...],
  "repeat_options": [...],
  "remind_days_before_options": [...],
  "overdue": [...],
  "upcoming": [...],
  "done": [...],
  "subscriptions": [...],
  "subscription_monthly": 757.25,
  "unread": 3,
  "unread_count": 3
}
```

`subscriptions` **hamesha present hai** (kabhi missing nahi, worst case `[]`), aur usme
user ki **saari** subscriptions hoti hain — status kuch bhi ho. Option A ke baad ye
subscriptions ka **ekmaatra** source hai.

Ek item ka pura shape:

```json
{
  "id": 12,
  "title": "Netflix",
  "name": "Netflix",
  "kind": "subscription",
  "category": "Entertainment",
  "amount": 199,
  "due_date": "2026-08-24",
  "due_on": "2026-08-24",
  "label": "Sun, 24 Aug",
  "when": "Due in 5 days",
  "repeat": "monthly",
  "remind_days_before": 3,
  "days": 5,
  "overdue": false,
  "status": "upcoming",
  "paid_on": null,
  "read": false
}
```

**Khali kab hoga:** sirf tab jab user ne koi subscription banayi hi na ho. Ek
subscription banane ke liye `POST /bills` me `kind: "subscription"` bhejo.

---

## 🟢 Part 2 — `kind` field

`kind` **har item pe hai** — `overdue`, `upcoming`, `done` aur `subscriptions`, chaaron
lists me. Sirf teen values possible hain:

```
"bill" | "subscription" | "emi"
```

Exact lowercase strings. Ye backend pe `Rule::in` se validate hote hain, to koi doosri
spelling (`"Subscription"`, `"subs"`) kabhi nahi aa sakti. Safe hai `kind == 'subscription'`
pe exact match karna.

`GET /reminders` ka `kinds` field bhi yahi list `key` + `label` ke saath deta hai, agar
UI me labels chahiye.

---

## 🟢 Part 3 — `subscription_monthly` (⚠️ value badal gayi hai)

Ye field **pehle se aa rahi thi**, par **galat calculate ho rahi thi**. Isko app-side
calculate mat karo — server ki value use karo, kyunki app bhi wahi galti dohrayegi.

### Kya galat tha

Purana code seedha saari subscriptions ka `amount` jod deta tha — chahe wo **yearly** ho
ya **weekly**. Yaani ek ₹1,499/saal wali Prime subscription `subscription_monthly` me
poore ₹1,499 add kar rahi thi.

### Ab kya hota hai

Har subscription ka amount uske `repeat` cycle ke hisaab se **monthly me normalise** hota hai:

| `repeat` | Monthly contribution |
|---|---|
| `weekly` | `amount × 52 ÷ 12` |
| `monthly` | `amount` |
| `yearly` | `amount ÷ 12` |
| `none` / `one_time` | `0` (recurring cost hai hi nahi) |

### Example

| Subscription | Amount | Repeat | Pehle | Ab |
|---|---|---|---|---|
| Netflix | ₹199 | monthly | 199.00 | 199.00 |
| Prime | ₹1,499 | yearly | 1499.00 | 124.92 |
| Locker | ₹100 | weekly | 100.00 | 433.33 |
| One-off charge | ₹5,000 | one_time | 5000.00 | 0.00 |
| **`subscription_monthly`** | | | **6798.00** ❌ | **757.25** ✅ |

`DELETE /bills/{id}` bhi response me refreshed `subscription_monthly` bhejti hai — delete
ke baad dobara `GET /reminders` maarne ki zaroorat nahi.

---

## 🟢 Part 4 — List overlap (Option A ho gaya)

Pehle chaaron lists ka rule consistent nahi tha: `overdue` aur `done` me subscriptions
aa jaati thi, `upcoming` me nahi. Isse ek overdue subscription do jagah dikh sakti thi.

**Ab Option A lagu hai.** Subscriptions ka ek hi ghar hai:

| List | Kya hota hai |
|---|---|
| `overdue` | Sirf `bill` + `emi` |
| `upcoming` | Sirf `bill` + `emi` |
| `done` | Sirf `bill` + `emi` |
| `subscriptions` | **Saari** subscriptions, status kuch bhi ho |

Yaani `overdue` / `upcoming` / `done` me ab kabhi `kind: "subscription"` nahi aayega —
dedupe ki zaroorat hi nahi padegi (tumhara id-based dedupe safety net ke taur pe rehne
do, koi nuksaan nahi).

Overdue ya paid subscriptions dikhani hon to `subscriptions` array me har item ka
`status` field padho:

```dart
final overdueSubs = subscriptions.where((s) => s['status'] == 'overdue');
final paidSubs    = subscriptions.where((s) => s['status'] == 'paid');
```

**`unread` / `unread_count` pe koi asar nahi.** Wo abhi bhi subscriptions ko count karta
hai — ek due subscription bhi ek notification hai. Use read mark karne ke liye wahi
`POST /bills/{id}/read` chalta hai, subscriptions array ke item ki `id` ke saath.

---

## 🔴 Part 5 — Per-subscription renewal reminder on/off

**Backend me ye feature hai hi nahi.** Frontend se fake toggle hatana bilkul sahi tha.

### Abhi kya hai

`bills` table me sirf ye hai:

```
remind_days_before   integer, 0–30, default 3
```

Ye "kitne din pehle yaad dilana hai" hai — **on/off nahi**. `0` ka matlab "due wale din
yaad dilao" hai, "band karo" nahi. Yaani per-subscription reminder disable karne ka koi
tareeka maujood nahi.

### Jo hai wo global hai

`GET /settings` (aur `GET /me`) me `notification_prefs` aata hai — **poore account ke
liye** channel-level toggles:

```json
{
  "notification_prefs": {
    "bill_reminders": true,
    "budget_alerts": true,
    "goal_milestones": true,
    "weekly_summary": true,
    "debt_tips": true
  }
}
```

`bill_reminders: false` **saare** bill/subscription/EMI reminders band kar dega — sirf ek
subscription ka nahi.

### Chahiye to kya lagega

Per-subscription toggle ke liye backend change chahiye:

1. `bills` table me `reminders_enabled` boolean column (default `true`) — migration
2. `POST /bills` + `PUT /bills/{id}` ke validation aur payload me wo field
3. `present()` me response me bhejna
4. Jo bhi job/service reminders bhejta hai usme is flag ka check

Chhota kaam hai (~1 ghanta), par **abhi exist nahi karta** — jab tak add na ho, app me
toggle mat dikhana.

---

## Verification

Ye sab **208 passing tests** ke saath cover hai. Naye tests:

- `subscription_monthly` yearly aur weekly cycles ko sahi normalise karti hai
- chaaron lists ke har item pe `kind` hai, aur `subscriptions` items pe exact
  `"subscription"` string hai
- `overdue` / `upcoming` / `done` me koi subscription nahi aati, aur har subscription ka
  `status` `subscriptions` array me readable rehta hai
- `DELETE /bills/{id}` recalculated `subscription_monthly` deta hai

---

## Deploy status

| Change | Status |
|---|---|
| `subscription_monthly` normalisation (Part 3) | Commit `51c7911` — deploy ho chuka hai, verify pending |
| Option A list separation (Part 4) | Commit `c6d43d6` — **abhi deploy nahi hua** |

Is release me koi migration nahi hai, to deploy ke baad `php artisan migrate` ki
zaroorat nahi.

**Deploy ke baad ye verify karna:**

```bash
curl -s -H "Authorization: Bearer <token>" \
     -H "Accept: application/json" \
     https://projects-money-coch.rmsiry.easypanel.host/api/reminders \
  | python3 -m json.tool
```

Check karo:

1. `subscription_monthly` — yearly subscription ab `amount ÷ 12` contribute kar rahi ho
2. `overdue` / `upcoming` / `done` — inme koi `"kind": "subscription"` item na ho
3. `subscriptions` — saari subscriptions yahan hon, apne `status` ke saath
