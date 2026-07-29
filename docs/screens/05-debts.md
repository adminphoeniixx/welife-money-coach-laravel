# 05 · Debts 💳

**Screens:** `debts` (tab 3) · `addLoan` · `addCard` · `loanDetail` · `cardDetail` · `debtCoach`

## APIs

| # | Method | Endpoint | Screen |
|---|---|---|---|
| 1 | GET | `/debts` | debts (list) |
| 2 | GET | `/debts/{id}` | loanDetail / cardDetail |
| 3 | POST | `/debts` | addLoan / addCard |
| 4 | PUT | `/debts/{id}` | Edit |
| 5 | DELETE | `/debts/{id}` | Delete |
| 6 | POST | `/debts/{id}/payment` | "Record payment" button |
| 7 | GET | `/coach` | **debtCoach** (Avalanche/Snowball) |

## GET /debts

Koi query param nahi.

| Key | Kya hai |
|---|---|
| `loan_categories` | addLoan form ka dropdown |
| `summary` | total debt, monthly EMI, progress |
| `loans[]` | loan cards |
| `cards[]` | credit card cards |
| `payoff_order[]` | kis order me chukana hai |

## GET /debts/{id} — detail screen

| Key | Kya hai |
|---|---|
| `debt` | poori detail (balance, rate, EMI, due day, utilisation) |
| `payments[]` | **payment history** — `amount`, `balance_after`, `emi_number`, `paid_on`, `label` |
| `documents[]` | **attachments** — `id`, `name`, `is_image` |

## POST /debts/{id}/payment

Body: `amount` (rupees)
Response: `message`, `closed` (bool — poora chuk gaya to `true`), updated `debt`

`closed: true` aaye to 🎉 "Paid off!" celebration dikhao.

## GET /coach — Debt Coach screen

| Key | Demo value |
|---|---|
| `plan.strategy` | `avalanche` |
| `plan.summary` | total ₹3,83,600 · EMI ₹31,100 · progress 60% · **avg APR 19.1%** |
| `plan.base` | bina extra payment ke: **Sep 2027 · 1 yr 2 mo** · interest ₹29,153 |
| `plan.projected` | extra payment ke saath (slider) |
| `plan.interest_saved` / `months_saved` | comparison ke liye |
| `plan.order[]` | `position`, `name`, `kind`, `balance`, `interest_rate`, `emi`, **`focus`** |

`focus: true` wale debt ko highlight karo — wahi pehle chukana hai.

## Attachments (loanDetail / cardDetail)

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | POST | `/debts/{id}/documents` | Attach — multipart, field **`documents[]`**, max 10 files |
| 2 | GET | `/debt-documents/{id}/view` | Inline preview |
| 3 | GET | `/debt-documents/{id}/download` | Download |
| 4 | DELETE | `/debt-documents/{id}` | Remove |

File types: jpg, jpeg, png, webp, pdf.

## Demo data

**4 debts:**

| Debt | Type | Balance | Rate | EMI |
|---|---|---|---|---|
| Home Loan (HDFC) | loan | ₹2,10,000 | 8.4% | ₹15,600 |
| Car Loan (SBI) | loan | ₹48,000 | 9.2% | ₹5,200 |
| HDFC Millennia | credit card | ₹84,000 | 42% | ₹4,200 |
| Amazon ICICI | credit card | ₹41,600 | 38% | ₹6,100 |

- Har debt pe **6 mahine ki payment history** (Home Loan me EMI number 180 → 175)
- **Attachments:** Home Loan pe 2 PDF, Car Loan 1 PDF, HDFC card 1 image, ICICI 1 PDF
- Coach order: HDFC Millennia → Amazon ICICI → Car Loan → Home Loan
