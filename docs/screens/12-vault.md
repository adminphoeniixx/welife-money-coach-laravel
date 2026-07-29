# 12 · Secure Documents Vault 🔒

**Screens:** `vaultLock` · `vault`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/vault/gate` | **Sabse pehle** — PIN set hai ya nahi |
| 2 | POST | `/vault/pin` | PIN set / change |
| 3 | POST | `/vault/unlock` | PIN enter — **6/min limit** |
| 4 | POST | `/vault/lock` | Lock button / app background me jaaye |
| 5 | GET | `/vault` | 🔓 Documents list |
| 6 | POST | `/vault/documents` | 🔓 Upload |
| 7 | GET | `/vault/documents/{id}/view` | 🔓 Preview |
| 8 | GET | `/vault/documents/{id}/download` | 🔓 Download |
| 9 | POST | `/vault/documents/{id}` | 🔓 Update — **POST hai, PUT nahi** |
| 10 | DELETE | `/vault/documents/{id}` | 🔓 Delete |

🔓 = **unlock ke baad hi chalega**, warna **403**.

## Flow

```
GET /vault/gate
  |
  +-- mode: "set"    ->  "PIN banao" screen  ->  POST /vault/pin { pin }
  |
  +-- mode: "enter"  ->  "PIN daalo" screen  ->  POST /vault/unlock { pin }
                                                        |
                                                        v
                                                  GET /vault  (list)
```

**`/vault/gate` response:** `mode` (`set` / `enter`), `has_pin`, `unlocked`

> Kahin bhi **403** mile to unlock expire ho gaya — user ko wapas `vaultLock` screen pe
> bhejo. App background se wapas aaye to `POST /vault/lock` call karke lock kar dena
> better hai.

## PIN set / change

`POST /vault/pin` body: `pin` (✅), `current_pin` (agar PIN pehle se set hai to ✅)

## Upload — `POST /vault/documents` (multipart)

| Field | Required | Note |
|---|---|---|
| `category` | ✅ | 14 options (neeche) |
| `title` | ✅ | |
| `side` | ❌ | `front` / `back` — cards ke liye |
| `notes` | ❌ | |
| `file` | ✅ | jpg, jpeg, png, webp, pdf — **max 8 MB** |

## 14 categories

`debit_atm_card` · `credit_card` · `aadhaar` · `pan` · `driving_license` · `passport` ·
`voter_id` · `insurance` · `vehicle_rc` · `loan` · `property` · `medical` ·
`passport_photo` · `other`

## GET /vault

| Param | Values |
|---|---|
| `search` | title / notes / filename me dhoondta hai |
| `category` | `all` ya koi category key |

**Response:** `filters`, `categories` (har ek me **count**), `total`, `documents[]`

`categories` me count 0 wali bhi aati hain — chips banane ke liye poori list use karo.

## Demo data

**PIN: `1234`** · **12 documents**

| Category | Documents |
|---|---|
| Aadhaar Card | 2 — **front + back pair** |
| PAN Card | 1 |
| Bank Debit / ATM Card | 1 |
| Credit Card | 1 |
| Driving License | 1 |
| Passport | 1 |
| Insurance Document | 1 |
| Vehicle RC Book | 1 |
| Loan Document | 1 |
| Property Document | 1 |
| Medical Report | 1 |
| Voter ID | 0 |

Files encrypted-at-rest hain — view/download pe server decrypt karke bhejta hai.
Images PNG hain, baaki PDF.
