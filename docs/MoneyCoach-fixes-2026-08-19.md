# MoneyCoach API — Fixes & Status (19 Aug 2026)

App developer ki reported issues ka jawab. Ye file **self-contained** hai — isko akela
bhej sakte ho, repo access ki zaroorat nahi.

**Base URL:** `https://projects-money-coch.rmsiry.easypanel.host/api`
**Auth:** `Authorization: Bearer <token>` + `Accept: application/json`

> Ye file 18 Aug wali `MoneyCoach-subscriptions-2026-08-19.md` ko **replace** karti hai —
> uska saara content isme hai, plus file-links aur goals wale sections.

---

## TL;DR

| # | Kya | Status |
|---|---|---|
| 1 | File links pe 500 "could not be decrypted" | 🟢 Fix — ab saaf **404**, aur asli wajah pata chal gayi |
| 2 | `/planning` goals me `id` + `emergency_fund` | 🟢 Pehle se theek tha — ab guarantee bhi hai |
| 3 | `subscription_monthly` ki galat value | 🟢 Fix — yearly/weekly ab sahi normalise hote hain |
| 4 | Subscriptions overdue/done me duplicate | 🟢 Fix — subscriptions ab sirf `subscriptions` array me |
| 5 | Per-subscription renewal reminder toggle | 🔴 Backend me exist hi nahi karta |
| 6 | Paid item pe `overdue: true` aa raha tha | 🟡 Fix ready — **deploy baaki hai** |

**Live pe verify kiya gaya:** #1, #2, #3, #4 ✅ — #6 abhi live nahi hai.

---

## 🟢 1. File links: 500 → 404

### Symptom

```
GET /api/files/entry-attachments/1?expires=...&signature=...
500  "This attachment could not be decrypted."
```

### Asli wajah (error message jhooth bol raha tha)

Decryption fail hui hi nahi thi — **file disk pe thi hi nahi.**

Laravel ka private disk `throw => false` pe chalta hai, to missing file pe `null` milta
hai (exception nahi). Wo `null` decrypt function me pahunch kar "payload invalid" error
deta tha, jo 500 "could not be decrypted" ban jaata tha. Bilkul galat diagnosis.

File gayab isliye thi kyunki uploaded files container ke andar rehti hain aur unke liye
persistent volume nahi tha — har redeploy pe files ud jaati hain, jabki database rows
(alag service) bache rehte hain.

### Ab kya hota hai

| Situation | Pehle | Ab |
|---|---|---|
| File disk pe nahi hai | `500` "could not be decrypted" | **`404`** "This file is no longer available on the server." |
| File hai par sach me decrypt fail | `500`, bina kisi detail ke | `500`, aur server log me path + size + asli reason |

### App ko kya karna hai

`404` ko "ye file ab maujood nahi" treat karo — retry mat karo, user ko dobara upload
karne ka option do. `500` ab genuinely server-side problem hai, report kar dena.

Ye teeno file endpoints pe lagu hai: proof attachments, debt documents, vault documents.

**⚠️ Purani files:** jo files pehle ud chuki hain wo wapas nahi aayengi. Un par ab 500 ki
jagah 404 aayega. Attachment id=1 unhi me se ek hai.

---

## 🟢 2. `/planning` goals — `id` aur `type`

**Dono cheezein pehle se sahi thi.** Live pe verify kiya gaya:

```json
{ "id": 17, "name": "Emergency Fund", "type": "emergency_fund",
  "target": 300000, "saved": 175000, "progress": 58.3, "target_date": null }
```

- `id` hamesha aata hai, hamesha **integer** — kabhi `goal_id` ya `_id` nahi. `POST /goals/{id}/contribute` isi se chalega.
- `type` hamesha exact lowercase `"emergency_fund"` ya `"savings"`.

### Jo hardening add ki gayi

`goals.type` column pe koi DB-level constraint nahi hai, to theory me purana row koi aur
spelling rakh sakta tha. Ab:

- `"Emergency Fund"`, `"emergency-fund"`, `"EMERGENCY_FUND"`, `" emergency_fund "` — sab
  read pe fold hokar `emergency_fund` aate hain
- Ek migration ne existing rows normalise kar diye (live pe chalayi gayi — **koi bhi row
  galat nahi thi**, no-op rahi)
- Goal ka JSON shape ab ek hi jagah se banta hai, to `/planning`, `/goals` mutations aur
  coach payload teeno **bilkul same** shape dete hain

**Live pe test:** ek row me jaan-boojh kar `"Emergency Fund"` daala gaya — API ne phir
bhi `"emergency_fund"` return kiya. ✅

Tumne frontend me jo fallback lagaya hai wo rehne do, koi nuksaan nahi — par ab uski
zaroorat nahi padegi.

---

## 🟢 3. `subscription_monthly` (⚠️ value badal gayi hai)

Field pehle se aa rahi thi, par **galat calculate ho rahi thi**. Isko app-side calculate
mat karo — server ki value trust karo.

### Kya galat tha

Purana code saari subscriptions ka amount seedha jod deta tha — chahe wo **yearly** ho ya
**weekly**. Yaani ₹1,499/**saal** wali Prime `subscription_monthly` me poore ₹1,499 add
kar rahi thi.

### Ab

| `repeat` | Monthly contribution |
|---|---|
| `weekly` | `amount × 52 ÷ 12` |
| `monthly` | `amount` |
| `yearly` | `amount ÷ 12` |
| `none` / `one_time` | `0` (recurring cost hai hi nahi) |

### Live pe verify kiya gaya

| Subscription | Amount | Repeat | Purana | **Live ab** |
|---|---|---|---|---|
| Netflix | ₹199 | monthly | 199.00 | 199.00 |
| Prime | ₹1,499 | yearly | 1499.00 | 124.92 |
| Spotify | ₹119 | monthly | 119.00 | 119.00 |
| **`subscription_monthly`** | | | **1817.00** ❌ | **442.92** ✅ |

`DELETE /bills/{id}` bhi response me refreshed `subscription_monthly` deta hai — delete ke
baad dobara `GET /reminders` maarne ki zaroorat nahi.

---

## 🟢 4. Subscriptions ab sirf `subscriptions` array me (Option A)

> **Live pe verify ho chuka hai.**

### Pehle

| List | Subscriptions? |
|---|---|
| `overdue` | ✅ aati thi |
| `upcoming` | ❌ filter out |
| `done` | ✅ aati thi |
| `subscriptions` | ✅ saari |

Rule consistent nahi tha — ek **overdue** subscription `overdue` **aur** `subscriptions`
dono me aati thi, par **upcoming** wali sirf `subscriptions` me.

### Ab (Option A)

| List | Kya hai |
|---|---|
| `overdue` | Sirf `bill` + `emi` |
| `upcoming` | Sirf `bill` + `emi` |
| `done` | Sirf `bill` + `emi` |
| `subscriptions` | **Saari** subscriptions, status kuch bhi ho |

`overdue` / `upcoming` / `done` me ab kabhi `kind: "subscription"` nahi aayega. Tumhara
id-based dedupe safety net ke taur pe rehne do — koi nuksaan nahi.

Overdue ya paid subscriptions dikhani hon to `subscriptions` array me `status` padho:

```dart
final overdueSubs = subscriptions.where((s) => s['status'] == 'overdue');
final paidSubs    = subscriptions.where((s) => s['status'] == 'paid');
```

**`unread` / `unread_count` pe koi asar nahi** — wo abhi bhi subscriptions count karta
hai. Ek due subscription bhi notification hai. Read mark karne ke liye wahi
`POST /bills/{id}/read` chalta hai, subscriptions array wale item ki `id` ke saath.

---

## 🔴 5. Per-subscription renewal reminder on/off

**Backend me ye feature hai hi nahi.** Frontend se fake toggle hatana bilkul sahi tha.

### Abhi kya hai

`bills` table me sirf:

```
remind_days_before   integer, 0–30, default 3
```

Ye "kitne din pehle yaad dilana hai" hai — **on/off nahi**. `0` ka matlab "due wale din
yaad dilao" hai, "band karo" nahi.

### Jo hai wo account-wide hai

`GET /settings` (aur `GET /me`) me:

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

`bill_reminders: false` **saare** bill/subscription/EMI reminders band karega — ek
subscription ka nahi.

### Banane me kya lagega

1. `bills` me `reminders_enabled` boolean column (default `true`) + migration
2. `POST /bills` aur `PUT /bills/{id}` ke validation + payload me wo field
3. Reminder response shape me wo field
4. Reminder bhejne wale job me flag ka check

~1 ghanta ka kaam. Bolo to bana denge — tab tak app me toggle mat dikhana.

---

## 🟡 6. Paid item pe `overdue: true` (Option A ne isko expose kiya)

> **Ye deploy hona baaki hai.** Live abhi purana behaviour de raha hai.

### Kya galat hai

Ek **paid** reminder jiski due date beet chuki ho, use bhi `overdue: true` mila tha:

```json
{ "title": "Old Sub", "status": "paid", "overdue": true, "when": "30 days overdue" }
```

Flag sirf ye dekhta tha ki due date beet gayi hai ya nahi — `status` `paid` hai ya nahi,
ye check hi nahi karta tha.

### Ye ab zyada zaroori kyun hai

Pehle paid subscriptions `done` list me aati thi, to app list membership se pehchaan leta
tha aur galat flag chhup jaata tha. **Option A ke baad** subscriptions sirf
`subscriptions` array me hain, aur app ko `status` / `overdue` field hi padhni padegi —
yaani cancelled/paid subscriptions par red "overdue" badge lag jaata.

Ye bug bills pe bhi tha (`done` list ke items me), bas wahan dikhta nahi tha.

### Fix ke baad

| Field | Paid item pe pehle | Paid item pe ab |
|---|---|---|
| `overdue` | `true` ❌ | `false` ✅ |
| `when` | `"30 days overdue"` ❌ | `"Paid"` ✅ |
| `days` | `-30` | `-30` (badla nahi — ye due date se relative hai) |
| `status` | `"paid"` | `"paid"` |

Genuinely overdue reminders par koi asar nahi — wo abhi bhi `overdue: true` aur
`"2 days overdue"` dete hain.

### Deploy hone tak app ko kya karna hai

`overdue` flag ke bajaye `status` pe bharosa karo:

```dart
final isOverdue = item['status'] != 'paid' && item['overdue'] == true;
```

Deploy ke baad ye check bhi safe rahega, to isko permanently rakh sakte ho.

---

## 📋 `GET /reminders` ka pura shape (reference)

```json
{
  "kinds": [...],
  "repeat_options": [...],
  "remind_days_before_options": [...],
  "overdue": [...],
  "upcoming": [...],
  "done": [...],
  "subscriptions": [...],
  "subscription_monthly": 442.92,
  "unread": 3,
  "unread_count": 3
}
```

`subscriptions` **hamesha present hai** — kabhi missing nahi, worst case `[]` (jab user ne
koi subscription banayi hi na ho).

Ek item:

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

Ek settled item (#6 deploy hone ke baad):

```json
{ "title": "Old Sub", "status": "paid", "overdue": false,
  "when": "Paid", "days": -30, "paid_on": "2026-07-20" }
```

`kind` **har item pe hai**, chaaron lists me. Sirf teen values possible hain —
`"bill"`, `"subscription"`, `"emi"` — exact lowercase, backend pe validated. Exact match
safe hai.

---

## Deploy status

| Fix | Live? |
|---|---|
| File links 404 (#1) | ✅ verify kiya |
| Goal contract (#2) | ✅ verify kiya |
| `subscription_monthly` (#3) | ✅ verify kiya |
| Option A (#4) | ✅ verify kiya |
| Paid item `overdue` flag (#6) | ❌ **deploy baaki** |

Kisi bhi pending change me migration nahi hai — deploy ke baad `php artisan migrate` ki
zaroorat nahi. (Goals wali migration pehle hi live pe chalayi ja chuki hai.)

### Deploy ke baad ye verify karna

```bash
curl -s -H "Authorization: Bearer <token>" \
     -H "Accept: application/json" \
     https://projects-money-coch.rmsiry.easypanel.host/api/reminders \
  | python3 -m json.tool
```

Ek paid reminder par `overdue` `false` hona chahiye aur `when` `"Paid"`.

---

## ⚠️ Ek infra cheez jo abhi bhi khuli hai

Uploaded files ke liye persistent volume Easypanel me set karna baaki hai
(`/var/www/html/storage/app/private`). **Jab tak wo nahi lagti, har deploy uploaded
proofs, debt documents aur vault documents mita dega** — rows bache rehte hain, files
nahi. App ko un links pe 404 milega.

App-side isse handle karne ka koi tareeka nahi hai; ye server config ka kaam hai.
