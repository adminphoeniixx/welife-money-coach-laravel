# MoneyCoach API — Fixes & Status (18 Aug 2026)

App developer ki reported issues ka jawab. Ye file **self-contained** hai — isko akela
bhej sakte ho.

**Base URL:** `https://projects-money-coch.rmsiry.easypanel.host/api`
**Auth:** `Authorization: Bearer <token>` + `Accept: application/json`
**Status:** ✅ Deploy ho chuka hai, live pe verify kiya gaya

---

## TL;DR

Reported list ka zyada hissa **pehle se hi kaam kar raha tha**. **3 cheezein** sach me
tooti hui thi — wo fix ho gayi hain aur live pe verify ho chuki hain.

| | |
|---|---|
| 🔴 Sach me toota tha | Recurring transactions (500), Calendar (500), file `url` (401 + galat disposition), Vault/debt documents me file fields missing |
| 🟢 Pehle se theek tha | Transaction detail, dashboard, bill snooze, coach, search, family, notifications, reports export, vault categories, settings |

---

## 🔴 Part 1 — Jo toota tha (ab fix hai)

### 1. Recurring transaction pe 500

`POST /entries` **500 deta tha** jab bhi `repeat` `none` ke alawa kuch ho.

```
Entry::nextOccurrenceAfter(): Return value must be of type
?Illuminate\Support\Carbon, Carbon\CarbonImmutable returned
```

**Ek root cause, teen symptoms:**

| Symptom | Pehle | Ab |
|---|---|---|
| Recurring transaction save | 500 | ✅ 201 |
| Transaction detail / list (recurring ho to) | 500 | ✅ 200 |
| `GET /calendar` (koi bill ya card due ho to) | 500 | ✅ 200 |

### 2. File `url` app se fetch nahi ho raha tha

`url` Sanctum token maangta tha → `Image.network(...)` pe **401**. Ab signed aur
token-free hai. Details → [Part 3](#-part-3--file-url-contract).

### 3. Preview endpoint download bhej raha tha

`/view` pe `Content-Disposition: attachment` aata tha (inline preview ke bajaye
download). Ab sahi `inline` aata hai.

### 4. Vault / debt documents me file fields hi nahi the

`url`, `view_url`, `download_url`, `size`, `mime_type` — **bilkul missing** the.
Ab teeno jagah (proof attachment, debt document, vault document) **ek jaisa shape** hai.

---

## 🔁 Part 2 — Recurring transactions

`POST /entries` / `PUT /entries/{id}` body me:

| Field | Values |
|---|---|
| `repeat` | `none` · `one_time` · `weekly` · `monthly` · `yearly` (default `none`) |
| `repeat_until` | `YYYY-MM-DD` — schedule kab tak chale (optional) |

Response me teen fields aate hain:

```json
{ "repeat": "monthly", "recurring": true, "next_occurrence": "2026-09-03" }
```

- `recurring` — boolean. Isi se **"🔁 Repeats monthly"** badge dikhao
- `next_occurrence` — agli date, ya `null` (repeat nahi hai / schedule khatam)

Recurring entries **Calendar pe bhi project** hoti hain — `GET /calendar?month=YYYY-MM`
me inka `source` `recurring_income` / `recurring_expense` hota hai.

---

## 📎 Part 3 — File URL contract

Teeno file types ab **bilkul ek jaisa** shape dete hain:

| Kahan | Array |
|---|---|
| Transaction proof | `entry.attachments[]` |
| Loan / card document | `debt.documents[]` |
| Vault document | `documents[]` |

```json
{
  "id": 12,
  "name": "receipt.jpg",
  "file_name": "receipt.jpg",
  "mime_type": "image/jpeg",
  "size": 84213,
  "is_image": true,
  "url":          "https://.../api/files/entry-attachments/12?expires=...&signature=...",
  "view_url":     "https://.../api/files/entry-attachments/12?expires=...&signature=...",
  "download_url": "https://.../api/files/entry-attachments/12?download=1&expires=...&signature=...",
  "authenticated_view_url": "https://.../api/entry-attachments/12/view",
  "created_at": "2026-08-18T07:32:39+00:00"
}
```

| Field | Kaise use karo |
|---|---|
| `url` / `view_url` | **Same link.** Seedha `Image.network(...)` me daalo — **koi header nahi chahiye** |
| `download_url` | Wahi file, save-to-disk disposition ke saath |
| `authenticated_view_url` | Purana Bearer-token wala route. Sirf tab jab token bhejna ho |
| `size` | Bytes me (`size_bytes` bhi milta hai, same value) |
| `is_image` | `true` → image preview, `false` → PDF icon |

### ⏱️ Links 6 ghante me expire hote hain

> **Inhe cache mat karo, local DB me store mat karo.** Har list/detail response fresh
> links deta hai. User 6 ghante se zyada screen pe rahe aur image fail ho jaye → bas
> screen refresh kar do.

Expired ya tampered link pe **403** aata hai (404 nahi).

### Upload

| Kya | Endpoint | Field |
|---|---|---|
| Entry banate waqt proof | `POST /entries` | `attachments[]` (ya single `attachment`) |
| Baad me proof jodna | `POST /entries/{id}/attachments` | `attachments[]` |
| Debt document | `POST /debts/{id}/documents` | `documents[]` |
| Vault document | `POST /vault/documents` | `file` |

Sab jagah: **jpg / jpeg / png / webp / pdf**, max **8 MB**, ek baar me max **10 files**.

> ⚠️ `PUT` ke saath multipart body nahi jaata. Edit me file bhejni ho to
> `POST /entries/{id}` use karo — wo `update` ka hi alias hai.

### Delete proof

```
DELETE /entries/{entryId}/attachments/{attachmentId}
```

```json
{ "message": "Proof removed.", "deleted_id": 2, "entry_id": 634 }
```

Row + disk pe encrypted blob dono hat jaate hain. Galat `entryId` bhejo to **404**
(safety check — attachment usi entry ka hona chahiye).

Transaction delete karne pe uske saare proofs apne aap hat jaate hain.

---

## 🟢 Part 4 — Jo pehle se kaam kar raha tha

Ye sab live pe test kiya gaya. App me issue dikhe to **app side** ya purana build dekho.

| Reported issue | Asli status |
|---|---|
| `GET /transactions/{id}` detail | ✅ `id`, `type`, `amount`, `category`, `description`, `payee`, `method`, `occurred_on`, `repeat`, `attachments` sab aate hain |
| Dashboard me sample/hardcoded data | ✅ Koi fake data nahi. Naya user = sab `0` aur khali arrays |
| `POST /bills/{id}/snooze` | ✅ Maujood — `days` **ya** `date` bhejo (ek zaroori) |
| Reminders unread count | ✅ Tha, naam `unread` hai. Ab `unread_count` bhi — dono keys |
| `GET /coach?q=...` | ✅ Real numbers quote karta hai (actual income, top category) |
| `GET /search?q=...` | ✅ Transactions, debts, bills, assets, goals — sab me real results |
| Family invite + shared expenses | ✅ Kaam karta hai (neeche note dekho) |
| `POST /notifications/read-all` + individual read | ✅ Dono maujood |
| `GET /reports/export?month=` | ✅ Real CSV, placeholder nahi |
| Vault categories backend se | ✅ 14 categories, har ek pe `count` — koi fallback list nahi |
| Settings: password / vault PIN / data-export | ✅ Teeno kaam karte hain |
| List responses me stable `id` | ✅ Har row me hai |
| Khali data pe empty array / 0 | ✅ Real empty values |

### Do cheezein jo bug lagti hain, par bug nahi hain

> **Family invite ka 403.** `POST /family/invite` tab tak `403 "You are not in a family"`
> dega jab tak user kisi household me na ho. Pehle `POST /family` se family banao —
> uske baad invite `201` deta hai.

> **Dashboard ka `shortcuts` array.** Ye static demo data **nahi** hai — backend-driven
> **navigation config** hai (kaun sa tile, kaun sa endpoint). Hardcode mat karo,
> backend se hi padho.

---

## ✅ Live verification (deploy ke baad)

Naya user banakar end-to-end chalaya:

| Test | Result |
|---|---|
| Recurring transaction create | `201` · `recurring=true` · `next_occurrence=2026-09-03` |
| Recurring transaction detail | `200` |
| Calendar (bill + card due ke saath) | `200` |
| Calendar me recurring project | `recurring_expense` present |
| Proof response ke saare fields | koi missing nahi |
| `url` bina token fetch | `200` · `image/png` |
| `url` inline preview | `Content-Disposition: inline` |
| `download_url` | `Content-Disposition: attachment` |
| Vault document url + size | ✅ upload aur list dono me |
| Vault / debt url bina token | `200` |
| `unread_count` (reminders + notifications) | ✅ |
| Delete proof endpoint | `200` "Proof removed." |

**14/14 pass.** Baaki 21 endpoints ka regression sweep bhi **21/21 `200`**.

---

## Error codes

| Code | Matlab | App kya kare |
|---|---|---|
| 401 | Token invalid/expire | Login screen, token clear karo |
| 403 | Permission nahi · vault locked · **signed link expire** | Vault ho to gate pe, file ho to list refresh karke nayi link lo |
| 404 | Record nahi mila / kisi aur ka hai | List refresh karo |
| 422 | Validation fail | `errors` object me field-wise messages hain |
| 429 | Rate limit (login, PIN, password = 6/min) | "Thodi der baad try karein" |
