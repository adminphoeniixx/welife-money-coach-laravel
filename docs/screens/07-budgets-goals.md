# 07 · Budgets, Goals & Emergency Fund 🎯

**Screens:** `budgets` · `emergency`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/planning` | **Dono screens isi ek API se** |
| 2 | POST | `/budgets` | Add budget |
| 3 | PUT | `/budgets/{id}` | Edit budget |
| 4 | DELETE | `/budgets/{id}` | Delete budget |
| 5 | POST | `/goals` | Add goal |
| 6 | PUT | `/goals/{id}` | Edit goal |
| 7 | DELETE | `/goals/{id}` | Delete goal |
| 8 | POST | `/goals/{id}/contribute` | "Add money" button |

## GET /planning

Koi query param nahi. Response me **sirf 2 keys**:

```json
{ "budgets": [...], "goals": [...] }
```

**`budgets[]`** — har budget me: `id`, `category`, `limit`, `spent`, `percent`, `exceeded`
**`goals[]`** — har goal me: `id`, `name`, **`type`**, `target`, `saved`, `progress`, `target_date`

## ⚠️ Emergency Fund screen ka alag API nahi hai

`goals[]` me se **`type == "emergency_fund"`** wala goal filter karke Emergency screen pe dikhao.
Baaki goals (`type == "savings"`) normal goals list me.

```
emergency screen  ->  goals.first { $0.type == "emergency_fund" }
goals list        ->  goals.filter { $0.type != "emergency_fund" }
```

## POST / PUT body

**Budget:** `category` (✅), `limit` (✅, rupees)
**Goal:** `name` (✅), `type` (`savings` / `emergency_fund`), `target` (✅), `target_date`
**Contribute:** `amount` (✅, rupees)

## Demo data

**5 budgets:**

| Category | Spent / Limit | % |
|---|---|---|
| Entertainment | 5,149 / 4,000 | **129% — exceeded, red** |
| Utilities | 5,169 / 6,000 | 86% |
| Transport | 6,000 / 8,000 | 75% |
| Food | 5,260 / 15,000 | 35% |
| Shopping | 2,890 / 10,000 | 29% |

**2 goals:**

| Goal | Type | Saved / Target | Progress |
|---|---|---|---|
| Emergency Fund | `emergency_fund` | ₹1,75,000 / ₹3,00,000 | **58.3%** |
| Goa Vacation | `savings` | ₹45,000 / ₹1,20,000 | **37.5%** |
