# 04 · Transactions 💸

**Screens:** `transactions` (tab 2) · `addExpense` · `addIncome`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/transactions` | List screen + segment (All/Income/Expense) badalne pe |
| 2 | POST | `/entries` | Add expense / add income save |
| 3 | PUT | `/entries/{id}` | Edit |
| 4 | DELETE | `/entries/{id}` | Swipe to delete |
| 5 | GET | `/transactions/{id}` | Detail screen |
| 6 | POST | `/entries/{id}/attachments` | Baad me proof (receipt) jodna |
| 7 | DELETE | `/entries/{id}/attachments/{attachmentId}` | Galat proof hatana |

## GET /transactions

**Query param — sirf ek:**

| Param | Values | Default |
|---|---|---|
| `type` | `all` · `income` · `expense` | `all` |

> ⚠️ **Ye endpoint sirf CURRENT MONTH ka data deta hai.**
> Koi `from` / `to` / `category` / `search` param **nahi** hai — month picker mat banao.
>
> - Purane mahine chahiye → `GET /reports?month=2026-06` ya `GET /calendar?month=2026-06`
> - Search chahiye → alag endpoint `GET /search?q=`

**Response:**

| Key | Kya hai |
|---|---|
| `filter` | applied type (string) |
| `categories` | dropdown ke liye income + expense category lists |
| `totals` | `income`, `expense`, `net` |
| `groups[]` | **din ke hisaab se grouped** entries — section headers seedha inhi se bana lo |

## POST / PUT body

| Field | Required | Note |
|---|---|---|
| `type` | ✅ | `income` / `expense` |
| `category` | ✅ | |
| `amount` | ✅ | rupees me (paise nahi) |
| `description` | ❌ | |
| `payee` | ❌ | |
| `method` | ❌ | UPI / Bank transfer / Credit Card etc |
| `occurred_on` | ✅ | `YYYY-MM-DD` |
| `repeat` | ❌ | `none` · `one_time` · `weekly` · `monthly` · `yearly` (default `none`) |
| `repeat_until` | ❌ | `YYYY-MM-DD` — schedule kab tak chale |
| `attachments[]` | ❌ | Proof files (multipart) — neeche dekho |

## 🔁 Recurring transactions

`repeat` bhejne pe response me teen fields aate hain:

```json
{ "repeat": "monthly", "recurring": true, "next_occurrence": "2026-09-03" }
```

- `recurring` — boolean. Isi se "🔁 Repeats monthly" badge dikhao
- `next_occurrence` — agli date, ya `null` (agar repeat nahi hai / schedule khatam)

Recurring entries **Calendar screen pe bhi** project hoti hain — `GET /calendar` me
inka `source` `recurring_income` / `recurring_expense` hota hai.

## 📎 Proof attachments (receipt photo / PDF)

Proof **entry banate waqt** bhi bhej sakte ho aur **baad me** bhi jod sakte ho.

| Kab | Endpoint | Field |
|---|---|---|
| Entry banate waqt | `POST /entries` | `attachments[]` (ya single `attachment`) |
| Baad me | `POST /entries/{id}/attachments` | `attachments[]` |
| Hatana | `DELETE /entries/{id}/attachments/{attachmentId}` | — |

**jpg / jpeg / png / webp / pdf**, max **8 MB**, ek baar me max **10 files**.

Har entry ke response me `attachments[]` aur `attachment_count` aata hai:

```json
{
  "id": 12,
  "name": "receipt.jpg",
  "mime_type": "image/jpeg",
  "size": 84213,
  "is_image": true,
  "url": "https://.../api/files/entry-attachments/12?expires=...&signature=...",
  "view_url": "...same as url...",
  "download_url": "...same, +download=1..."
}
```

`url` / `view_url` **signed aur token-free** hain — seedha `Image.network(...)` me daal
do, koi `Authorization` header nahi chahiye. Ye links **6 ghante me expire** hote hain,
isliye inhe cache ya store mat karo — har response fresh links deta hai.

> Poora contract (delete response, expiry behaviour, debt + vault documents) yahan hai →
> [98-reported-issues.md](98-reported-issues.md#-part-3--file-urls-ka-naya-contract)

> ⚠️ `PUT` ke saath multipart body nahi jaata. Edit me file bhejni ho to
> `POST /entries/{id}` use karo — wo `update` ka hi alias hai.

## Demo data

Is mahine **18 entries · 14 day-groups**:

| | |
|---|---|
| Income | **₹97,000** (Salary ₹85,000 + Freelance ₹12,000) |
| Expense | **₹63,718** |
| Net | ₹33,282 |

Entries me Swiggy, BigBasket, Netflix, Amazon, HP Petrol, Jio, EMIs waghairah hain.

> Poora **6 mahine ka history (75 entries)** Insights aur Calendar screens pe dikhega,
> is screen pe nahi.
