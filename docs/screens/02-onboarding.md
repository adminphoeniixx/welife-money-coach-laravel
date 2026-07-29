# 02 · Onboarding

**Screens:** `onbCurrency` · `onbGoal` · `onbNotif`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | GET | `/onboarding` | **Ek hi baar** — teeno screens ke options isi me aa jaate hain |
| 2 | POST | `/onboarding` | **Ek hi baar** — aakhri screen ("Done") pe sab answers ek saath |

> Har screen pe alag API call mat karo. Shuru me ek GET, aakhir me ek POST.

## GET /onboarding

```json
{
  "currencies": ["INR", "USD", "EUR", "GBP", "AED", "SGD", "AUD", "CAD"],
  "goals": [
    { "key": "get_out_of_debt",      "label": "Get out of debt" },
    { "key": "build_emergency_fund", "label": "Build an emergency fund" },
    { "key": "save_for_goal",        "label": "Save for a big goal" },
    { "key": "track_spending",       "label": "Track my spending" },
    { "key": "grow_wealth",          "label": "Grow my wealth" }
  ],
  "user": { ... }
}
```

- `currencies` → **onbCurrency** screen ka list
- `goals` → **onbGoal** screen ke options (`key` bhejna hai, `label` dikhana hai)

## POST /onboarding

| Field | Required | Values |
|---|---|---|
| `currency` | ✅ | upar wali 8 me se ek |
| `primary_goal` | ❌ | upar wali 5 keys me se ek |
| `notifications_enabled` | ✅ | `true` / `false` — **onbNotif** screen ka toggle |
| `locale` | ❌ | jaise `en-IN` |
| `country` | ❌ | 2-letter code, jaise `IN` |

Ye call **`onboarded` ko `true`** kar deti hai — iske baad user dubara onboarding me nahi aayega.

## Demo data

INR · en-IN · IN · goal = `get_out_of_debt` · notifications ON
