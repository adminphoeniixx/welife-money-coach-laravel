# 08 · Reminders & Subscriptions 🔔

**Screens:** `reminders` · `addReminder` · `subs`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/reminders` | **Reminders aur Subscriptions dono screens** |
| 2 | POST | `/bills` | Add reminder |
| 3 | PUT | `/bills/{id}` | Edit |
| 4 | DELETE | `/bills/{id}` | Delete |
| 5 | POST | `/bills/{id}/paid` | "Mark as paid" |

## GET /reminders

Koi query param nahi. Response ki **4 keys**:

| Key | Kya hai | Screen |
|---|---|---|
| `overdue[]` | date nikal chuki | reminders — **top pe red section** |
| `upcoming[]` | aane wale bills/EMIs (**subscriptions isme nahi**) | reminders |
| `subscriptions[]` | sirf subscriptions | **subs** |
| `subscription_monthly` | subscriptions ka monthly total | **subs** ka header |

## ⚠️ Subscriptions screen ka alag API nahi hai

`subs` screen isi response ke `subscriptions[]` + `subscription_monthly` se banega.
Yearly total dikhana ho to `subscription_monthly × 12` khud calculate kar lo.

## POST / PUT body

| Field | Required | Note |
|---|---|---|
| `name` | ✅ | |
| `kind` | ✅ | `bill` · `emi` · `subscription` |
| `category` | ❌ | |
| `amount` | ✅ | rupees |
| `due_date` | ✅ | `YYYY-MM-DD` |
| `repeat` | ❌ | `monthly` etc |
| `remind_days_before` | ❌ | default 3 |
| `debt_id` | ❌ | EMI ko debt se link karne ke liye |

## Demo data

**1 overdue:**

| Bill | Amount |
|---|---|
| Vodafone Broadband | ₹499 (2 din pehle due tha) |

**6 upcoming:** HDFC Millennia ₹4,200 (1 din) · Home Loan EMI ₹15,600 (5 din) ·
Jio Postpaid ₹599 (6 din) · Amazon ICICI ₹6,100 (8 din) · Electricity ₹1,890 (9 din) ·
Car Loan EMI ₹5,200 (12 din)

**3 subscriptions — `subscription_monthly` = ₹2,267:**

| Subscription | Amount |
|---|---|
| Amazon Prime | ₹1,499 |
| Netflix | ₹649 |
| Spotify | ₹119 |
