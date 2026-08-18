# ✅ App developer ki reported issues — status

App developer ne 18 Aug 2026 ko jo list bheji thi, uska point-by-point jawab.
Har cheez **live server pe real data seed karke** verify ki gayi hai — guess nahi.

**TL;DR:** list ka zyada hissa **pehle se hi kaam kar raha tha**. 3 cheezein sach me
tooti hui thi, wo fix ho gayi hain.

---

## 🔴 Part 1 — Jo sach me toota tha (ab fix hai)

### 1. Recurring transaction pe 500 error

`POST /entries` **500 deta tha** jab bhi `repeat` `none` ke alawa kuch ho
(`weekly` / `monthly` / `yearly`).

```
App\Models\Entry::nextOccurrenceAfter(): Return value must be of type
?Illuminate\Support\Carbon, Carbon\CarbonImmutable returned
```

**Ek hi root cause ne teen alag-alag symptoms banaye the:**

| Symptom | Kya dikhta tha |
|---|---|
| Recurring transaction save | 500 |
| Transaction detail / list | recurring entry ho to 500 |
| `GET /calendar` | koi bhi bill ya card due ho to 500 |

Ab theek hai. Response me schedule ke teen fields aate hain:

```json
{ "repeat": "monthly", "recurring": true, "next_occurrence": "2026-09-03" }
```

- `recurring` — boolean, ise UI me "🔁 Repeats monthly" badge dikhane ke liye use karo
- `next_occurrence` — agli date (`null` agar repeat nahi hai ya schedule khatam)

### 2. Proof / document ka `url` app se fetch nahi ho raha tha

`url` pehle **Sanctum token maangta tha**, isliye seedha image widget me daalne pe
401 aata tha. Ab sab file links **signed aur token-free** hain — dekho [Part 3](#-part-3--file-urls-ka-naya-contract).

### 3. Preview endpoint download bhej raha tha

`/view` pe `Content-Disposition: attachment` aata tha, yaani inline preview ke bajaye
download trigger hota tha. Ab sahi `inline` aata hai (Laravel ke `streamDownload()` me
disposition header array me nahi, 4th argument me jaana chahiye tha).

### 4. Vault / debt documents me file ke fields hi nahi the

Vault list me sirf metadata tha — `url`, `view_url`, `size`, `download_url`
**bilkul missing** the. Ab teeno jagah (proof attachment, debt document, vault document)
**ek jaisa shape** aata hai.

---

## 🟢 Part 2 — Jo pehle se kaam kar raha tha

Ye sab live pe test kiya gaya. Agar app me issue dikh raha hai to **app side** dekho,
ya purana build / stale deploy ho sakta hai.

| Reported issue | Asli status |
|---|---|
| `GET /transactions/{id}` detail | ✅ Kaam karta hai — `id`, `type`, `amount`, `category`, `description`, `payee`, `method`, `occurred_on`, `repeat`, `attachments` sab aate hain |
| Dashboard me sample/hardcoded data | ✅ Koi fake data nahi. Naya user = sab `0` aur khali arrays. Health-score factors real numbers se bante hain |
| `POST /bills/{id}/snooze` | ✅ Maujood hai — `days` **ya** `date` bhejo (dono me se ek zaroori) |
| Reminders unread count | ✅ Tha, bas naam `unread` hai. Ab `unread_count` bhi milta hai — dono keys |
| `GET /coach?q=...` real data se | ✅ Real numbers quote karta hai (actual income, top category, actual amount) |
| `GET /search?q=...` | ✅ Transactions, debts, bills, assets, goals, categories — sab me real results |
| Family invite + shared expenses | ✅ Kaam karta hai — **pehle household banao** (`POST /family`), tabhi invite chalega |
| `POST /notifications/read-all` + individual read | ✅ Dono maujood |
| `GET /reports/export?month=` | ✅ Real CSV deta hai, placeholder nahi |
| Vault categories backend se | ✅ Backend se hi aati hain (14 categories, har ek pe `count`) — koi fallback list nahi |
| Settings: password / vault PIN / data-export | ✅ Teeno kaam karte hain |
| List responses me stable `id` | ✅ Har list row me hai |
| Khali data pe empty array / 0 | ✅ Real empty values aati hain |

> ⚠️ **Family invite ka 403 bug nahi hai.** `POST /family/invite` tab tak `403 "You are
> not in a family"` dega jab tak user kisi household me na ho. Pehle `POST /family`
> se family banao — uske baad invite `201` deta hai.

> ℹ️ **Dashboard ka `shortcuts` array static "demo data" nahi hai** — wo backend-driven
> **navigation config** hai (kaun sa tile dikhana hai + kaun sa endpoint). Ise
> hardcode mat karo, backend se hi padho.

---

## 📎 Part 3 — File URLs ka naya contract

Teeno file types ab **bilkul ek jaisa** shape dete hain:

- Transaction proof → `entry.attachments[]`
- Loan / card document → `debt.documents[]`
- Vault document → `documents[]`

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
| `url` / `view_url` | **Same link.** Seedha `Image.network(...)` ya browser me daalo — **koi header nahi chahiye** |
| `download_url` | Wahi file, par save-to-disk disposition ke saath |
| `authenticated_view_url` | Purana token-wala route. Sirf tab use karo jab Bearer token bhejna ho |
| `size` | Bytes me (`size_bytes` bhi milta hai, same value) |
| `is_image` | `true` → image preview dikhao, `false` → PDF icon |

### ⏱️ Signed links 6 ghante me expire hote hain

**Inhe cache mat karo, database me store mat karo.** Har list/detail response fresh links
deta hai. Agar user screen pe 6 ghante se zyada baitha rahe aur image load fail ho jaye,
to bas screen refresh kar do — nayi links aa jayengi.

Expired ya tampered link pe **403** aata hai (404 nahi).

### Delete

```
DELETE /entries/{entryId}/attachments/{attachmentId}
```

```json
{ "message": "Proof removed.", "deleted_id": 2, "entry_id": 634 }
```

Ye row + disk pe encrypted blob dono hata deta hai. Galat `entryId` bhejo to **404**
aata hai (safety check — attachment usi entry ka hona chahiye).

Transaction delete karne pe uske saare proofs apne aap hat jaate hain.

### Upload

| Kya | Endpoint | Field |
|---|---|---|
| Entry banate waqt proof | `POST /entries` | `attachments[]` (ya single `attachment`) |
| Baad me proof jodna | `POST /entries/{id}/attachments` | `attachments[]` |
| Debt document | `POST /debts/{id}/documents` | `documents[]` |
| Vault document | `POST /vault/documents` | `file` |

Sab jagah: **jpg / jpeg / png / webp / pdf**, max **8 MB**, ek baar me max **10 files**.

> ⚠️ `PUT` ke saath multipart body nahi jaata. Edit ke waqt file bhejni ho to
> `POST /entries/{id}` use karo — wo `update` ka hi alias hai.

---

## 🚀 Deploy note

Ye fixes **naye deploy ke baad hi** live pe aayenge. Is baar koi nayi migration nahi hai,
isliye normal deploy kaafi hai — `RUNNING_MIGRATIONS_AND_SEEDERS` set karne ki zaroorat
nahi.
