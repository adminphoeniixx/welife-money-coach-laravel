# ⚠️ Jin screens ki API abhi nahi hai

Ye screens `MoneyCoach-iOS-app.html` prototype me hain lekin **backend ready nahi hai**.
App developer inhe abhi na banaye, ya local/dummy rakhe.

## 1. `coach` — AI chat coach

Screen: "🧠 Your Financial Coach — Ask me anything about your money"

**Status:** ❌ Koi chat API nahi hai.

> ⚠️ **Naam ka confusion:** `/api/coach` naam ka endpoint **exist karta hai**, par wo
> chat **nahi** hai — wo **debt payoff plan** (avalanche/snowball) deta hai aur
> **`debtCoach`** screen ke liye hai. Dekho → [05-debts.md](05-debts.md)

Chahiye hoga: chat/LLM endpoint jo user ke real numbers ke saath jawab de.

## 2. `subscription` — Go Premium paywall

Screen: premium plan ka pricing page

**Status:** ❌ Mobile API exposed nahi hai.

Plans database me hain (`plans` table, `PlanSeeder`) par abhi sirf **admin panel** se
manage hote hain. `/api` pe koi plans-list ya checkout endpoint nahi hai.

Chahiye hoga: plans list + subscribe/checkout (payment gateway ke saath).

> ⚠️ Isse `subs` screen ke saath confuse mat karna — **`subs`** (Subscriptions: Netflix,
> Prime, Spotify) **ready hai** aur `/api/reminders` se chalti hai.
> Dekho → [08-reminders.md](08-reminders.md)

## 3. `setSecurity` — Face ID / 2FA / Passkey

Screen: Sign-in security settings

**Status:** ❌ `/api` pe nahi hai.

Two-factor aur passkey **web app** pe kaam karte hain (`routes/settings.php`,
`two_factor_*` columns, `passkeys` table) par mobile API pe expose nahi kiye gaye.

- **Face ID / biometric** — ye purely **device-side** hai, iske liye API ki zaroorat hi
  nahi. Token ko Keychain me biometric protection ke saath rakho — ye abhi bana sakte ho.
- **2FA enable/disable aur passkey register** — inke liye naye endpoints chahiye honge.

## Summary

| Screen | Kya chahiye | Abhi kya karo |
|---|---|---|
| `coach` (AI chat) | Chat/LLM endpoint | Hide ya "Coming soon" |
| `subscription` (paywall) | Plans + checkout | Hide ya "Coming soon" |
| `setSecurity` — Face ID | kuch nahi (device-side) | ✅ Bana sakte ho |
| `setSecurity` — 2FA / passkey | Naye endpoints | Hide ya "Coming soon" |
