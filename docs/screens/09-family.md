# 09 · Family Finance 👨‍👩‍👧

**Screen:** `family`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/family` | Screen open |
| 2 | POST | `/family` | Create family (`name`) |
| 3 | DELETE | `/family` | Delete family — **sirf owner** |
| 4 | POST | `/family/leave` | Leave — **owner nahi kar sakta** |
| 5 | POST | `/family/invite` | Invite (`email`, `role`) — **sirf owner** |
| 6 | DELETE | `/family/invitations/{id}` | Invite cancel |
| 7 | GET | `/family/join/{token}` | Invite link kholne pe preview |
| 8 | POST | `/family/join/{token}` | Join confirm |
| 9 | DELETE | `/family/members/{id}` | Member remove — **sirf owner** |
| 10 | POST | `/family/expenses` | Shared expense add |
| 11 | DELETE | `/family/expenses/{id}` | Shared expense delete |
| 12 | POST | `/family/budgets` | Family budget add — **sirf owner** |
| 13 | DELETE | `/family/budgets/{id}` | Family budget delete — **sirf owner** |

## GET /family

| Key | Kya hai |
|---|---|
| `categories` | 8 shared-expense categories (dropdown) |
| **`can_manage`** | bool — owner hai ya nahi |
| **`my_role`** | `owner` / `partner` / `member` |
| `household` | `id`, `name`, `members[]`, `invitations[]` |
| `summary` | `income`, `expense`, `net`, `education` (is mahine ka) |
| `expenses[]` | shared expenses (max 20) — har ek me `by` (kisne kiya) + `mine` (bool) |
| `budgets[]` | family budgets — `limit`, `spent`, `percent`, `exceeded` |

## ⚠️ Do zaroori checks

**1. `household` `null` bhi aa sakta hai** — matlab user kisi family me nahi hai.
Tab **"Create family" empty state** dikhao, screen crash mat karo.

```json
{ "household": null, "categories": [...] }
```

**2. `can_manage == false`** ho to ye buttons **hide** karo:
invite · member remove · family budget add/delete · family delete
(Member sirf shared expense add kar sakta hai aur leave kar sakta hai.)

## Invite flow

```
owner: POST /family/invite  ->  { invitation: { token, link } }
        link share karo (WhatsApp etc)

doosra user: GET  /family/join/{token}  ->  preview ("Sharma Family me join karein?")
             POST /family/join/{token}  ->  joined
```

## Categories

Groceries · Housing · Utilities · Education · Healthcare · Transport · Entertainment · Other

## Demo data

**"Sharma Family"**

| | |
|---|---|
| Members | Test User (**owner**) · Priya Sharma (**partner**) |
| Pending invite | arjun@example.com (role: member) |
| Shared expenses | 9 |
| Family budgets | 4 |

**Summary:** income ₹62,000 · expense ₹37,070 · net ₹24,930 · education ₹15,500

**Budgets:**

| Category | Spent / Limit | % |
|---|---|---|
| Education | 15,500 / 14,000 | **111% — exceeded** |
| Groceries | 10,170 / 12,000 | 85% |
| Utilities | 2,680 / 4,000 | 67% |
| Healthcare | 1,540 / 3,000 | 51% |

> **Role test:** `priya@example.com` / `password` se login karo — wahi family dikhegi
> par `my_role: "partner"` aur `can_manage: false` aayega. Manage buttons chhupne chahiye.
