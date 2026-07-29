# 01 · Auth — Welcome / Login / Register / Forgot


**Screens:** `welcome` · `login` · `register` · `forgot`

## APIs

| # | Method | Endpoint | Kab |
|---|---|---|---|
| 1 | POST | `/auth/register` | Register button |
| 2 | POST | `/auth/login` | Login button — **rate limit 6/min** |
| 3 | POST | `/auth/forgot-password` | "Forgot password" — 6/min |
| 4 | POST | `/auth/reset-password` | Reset link se aayi screen pe — 6/min |
| 5 | GET | `/user` | App start pe — saved token valid hai ya nahi check karo |
| 6 | POST | `/auth/logout` | Logout (sirf ye device) |
| 7 | POST | `/auth/logout-all` | Sab devices se logout |

## Request

**Login:**
```
email, password, device_name
```

**Register:**
```
name, email, password, password_confirmation, device_name
```

**Forgot password:** `email` → email pe reset link jaata hai
**Reset password:** `token`, `email`, `password`, `password_confirmation`

## Response

```json
{ "token": "12|abcd...", "user": { ... } }
```

Token ko **Keychain** me save karo aur har request me `Authorization: Bearer <token>` bhejo.

`user` object:
`id, name, email, avatar_url, currency, locale, country, primary_goal, onboarded,
notifications_enabled, notification_prefs, has_vault_pin`

## Login ke baad kahan bhejein

```
user.onboarded == false  ->  Onboarding screens (02)
user.onboarded == true   ->  Home (03)
```

Demo user me `onboarded` **true** hai — seedha Home khulega.

## Notes

- **401 aaye** to token expire ho gaya — clear karke login screen pe bhejo.
- **429 aaye** to rate limit — "Thodi der baad try karein" dikhao.
- Legal screens (`legalPrivacy` / `legalTerms`) **public** hain, token ke bina bhi chalti hain →
  `GET /legal/privacy`, `GET /legal/terms`. Register screen ke "Terms" link pe kaam aayengi.

## Demo data

| Account | Login |
|---|---|
| Test User | `test@example.com` / `password` |
| Priya Sharma (partner) | `priya@example.com` / `password` |
