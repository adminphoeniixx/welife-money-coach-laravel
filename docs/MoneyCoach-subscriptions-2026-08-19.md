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
| Subscriptions kaise aa rahi hain? | 🟢 **Dono tarah** — dedicated top-level `subscriptions` array **aur** status lists ke andar bhi |
| `kind: "subscription"` hai? | 🟢 Haan, har item pe, exact lowercase `"subscription"` |
| `subscription_monthly` aata hai? | 🟢 Aata tha — par **math galat tha**, ab fix hai |
| Per-subscription reminder on/off? | 🔴 **Backend me support nahi hai.** Frontend se hataya, sahi kiya |
| Kuch aur? | 🟡 Ek list-overlap hai jispe **tumhara decision chahiye** — neeche Part 4 |

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
user ki **saari** subscriptions hoti hain — status kuch bhi ho.

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

## 🟡 Part 4 — List overlap (yahan decision chahiye)

Ye **abhi change nahi kiya** kyunki isse app ka UI badal jayega — pehle tum batao kya chahiye.

Filhaal chaaron lists ka rule **consistent nahi** hai:

| List | Subscriptions include hoti hain? |
|---|---|
| `overdue` | ✅ Haan |
| `upcoming` | ❌ **Nahi** (explicitly filter out hoti hain) |
| `done` | ✅ Haan |
| `subscriptions` | ✅ Saari, status kuch bhi ho |

Iska matlab: ek **overdue** subscription `overdue` aur `subscriptions` **dono** me aati
hai, par ek **upcoming** subscription sirf `subscriptions` me. Agar tum dono lists render
kar rahe ho to overdue subscription **do baar** dikhegi.

**Do options:**

- **Option A — status lists sirf bills/EMI ki hon.** `overdue` aur `done` se bhi
  subscriptions nikaal di jayein. Saari subscriptions ka ek hi ghar: `subscriptions`
  array (uske andar `status` field se overdue/paid pata chal jayega).
  *App-side:* overdue subscriptions ko `subscriptions.where(status == 'overdue')` se
  khud merge karna padega.

- **Option B — status lists complete hon.** `upcoming` me bhi subscriptions aane lagein.
  Teeno status lists poori, aur `subscriptions` ek convenience view. Duplication har
  jagah uniform ho jayega, `kind` se filter kar lena.
  *App-side:* kuch todna nahi padega, sirf duplication handle karna hoga.

Mera suggestion **Option A** hai — subscriptions ka apna screen hai, to unka ek hi source
of truth rakhna saaf rahega. Par ye product call hai, isliye chhoda hua hai. Bata do,
ek line ka change hai.

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

Ye sab **204 → 207 passing tests** ke saath cover hai. Naye tests:

- `subscription_monthly` yearly aur weekly cycles ko sahi normalise karti hai
- chaaron lists ke har item pe `kind` hai, aur `subscriptions` items pe exact
  `"subscription"` string hai
- `DELETE /bills/{id}` recalculated `subscription_monthly` deta hai

---

## Deploy status

⚠️ **Ye changes abhi live pe nahi hain.** Code commit ho chuka hai, par Easypanel deploy
manual hai. Deploy ke baad `subscription_monthly` ki value badal jayegi (upar Part 3 wali
table dekho) — agar app abhi purani value pe koi assertion kar raha hai to dhyaan rakhna.

Is change me koi migration nahi hai, to deploy ke baad `php artisan migrate` ki zaroorat
nahi.
