# 11 · Challenges 🏆

**Screen:** `challenges`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/challenges` | Screen open |
| 2 | POST | `/challenges` | Preset join (body: `key`) |
| 3 | POST | `/challenges/{id}/progress` | Progress log (body: `amount`) |
| 4 | DELETE | `/challenges/{id}` | Leave challenge |

## GET /challenges

Koi query param nahi.

| Key | Kya hai |
|---|---|
| `active[]` | joined challenges — `id`, `title`, `description`, `target`, `progress`, **`percent`**, `status`, **`days_left`** |
| `presets[]` | jo abhi join nahi kiye — `key`, `title`, `description`, `target` |

> `presets[]` me se joined wale **automatically hat jaate hain** — app me filter karne ki
> zaroorat nahi.
>
> `active[]` me **completed challenges bhi aate hain** (`status: "completed"`) — inhe
> trophy/tick ke saath dikhao, progress bar 100% pe.

## 5 presets

| key | Title | Target |
|---|---|---|
| `save_5000` | Save ₹5,000 this month | ₹5,000 |
| `save_10000` | Save ₹10,000 this month | ₹10,000 |
| `no_spend_7` | No unnecessary spending for 7 days | 7 (din) |
| `cut_fuel_10` | Cut fuel spending by 10% | ₹1,000 |
| `cut_dining_3000` | Trim dining by ₹3,000 | ₹3,000 |

> `no_spend_7` ka target **din** hai, rupees nahi — us card pe "4 / 7 days" dikhao,
> "₹4 / ₹7" nahi.

## POST /challenges/{id}/progress

Body: `amount`
Response: `message`, **`completed`** (bool), `percent`

`completed: true` aaye to 🏆 celebration dikhao.

## Demo data

**`active[]` — 3:**

| Challenge | Progress | % | Status |
|---|---|---|---|
| Save ₹10,000 this month | ₹6,200 / ₹10,000 | **62%** | active |
| No unnecessary spending for 7 days | 4 / 7 days | **57.1%** | active |
| Save ₹5,000 this month | ₹5,000 / ₹5,000 | **100%** | **completed** 🏆 |

**`presets[]` — 2 bache:** Cut fuel spending by 10% · Trim dining by ₹3,000
