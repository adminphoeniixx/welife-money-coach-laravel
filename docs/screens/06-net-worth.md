# 06 · Net Worth / Assets 📈

**Screen:** `networth`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/net-worth` | Screen open |
| 2 | POST | `/assets` | Add asset |
| 3 | PUT | `/assets/{id}` | Edit |
| 4 | DELETE | `/assets/{id}` | Delete |

## GET /net-worth

Koi query param nahi.

| Key | Kya hai |
|---|---|
| `types[]` | 8 asset types — add form ka dropdown (`key` + `label`) |
| `summary` | `assets`, `liabilities`, `net_worth` |
| `breakdown[]` | type-wise total + **percent** — pie/donut chart ke liye ready |
| `accounts[]` | individual assets ki list |

**Asset types:** bank · cash · gold · fixed_deposit · mutual_fund · stocks · property · other

> `liabilities` debts se automatically aata hai — user isse edit nahi karta.
> Net worth = assets − liabilities.

## POST / PUT body

| Field | Required |
|---|---|
| `name` | ✅ |
| `type` | ✅ (upar wali 8 keys me se) |
| `balance` | ✅ (rupees) |

## Demo data

| | |
|---|---|
| Assets | **₹16,77,850** |
| Liabilities | ₹3,83,600 |
| **Net worth** | **₹12,94,250** |

**6 assets:**

| Asset | Type | Amount | % |
|---|---|---|---|
| SBI Fixed Deposit | fixed_deposit | ₹5,00,000 | 30% |
| Nippon Mutual Fund | mutual_fund | ₹3,85,000 | 23% |
| Gold (sovereign) | gold | ₹3,20,000 | 19% |
| HDFC Savings | bank | ₹2,40,350 | 14% |
| Zerodha Stocks | stocks | ₹2,17,500 | 13% |
| Cash in hand | cash | ₹15,000 | 1% |
