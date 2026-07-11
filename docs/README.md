# MoneyCoach — iOS App Prototype (HTML)

A self-contained, interactive **iOS-style HTML prototype** of the MoneyCoach mobile app.
Covers every planned user-facing feature. Design uses the brand logo and colours from
`Money Coach App - Google Sheets.pdf`.

## How to open

Just open **`index.html`** in any browser (double-click, or `file://`).
On a phone it fills the screen; on desktop it renders inside an iPhone frame.
No build step, no internet — everything is inline. Logo is at `assets/logo.png`.

> Tip: In Chrome, open DevTools → toggle device toolbar → iPhone for a true-to-size preview.

## Brand

| Token | Value |
| --- | --- |
| Magenta / Pink | `#CC1D79` |
| Teal / Green | `#06B7AD` |
| Gradient | `135deg, #CC1D79 → #06B7AD` |

## Screens included (every feature)

**Auth & onboarding**
- Welcome / splash
- Log in (email, Apple, Google, remember me, forgot)
- Register (name, email, password, terms)
- Forgot password
- Onboarding: country / currency / timezone / income
- Onboarding: goal (reduce debt / track spending / never miss due date)
- Onboarding: notification permission

**Main app (tab bar: Home · Money · ➕ · Debts · Alerts)**
- Home dashboard (net balance, income/expense, total debt, EMIs, priority debt action, upcoming due, spending donut, quick add)
- Transactions (income/expense segments, category filters, grouped by day)
- Add expense (category, paid to, date, method, note, recurring, reminder, attach)
- Add income (category, source, date, account, attach)
- Debts (total outstanding, debt-free coach, loans list, cards list)
- Debt Coach (Snowball / Avalanche, payoff order, extra-payment simulator, interest saved)
- Loan detail (progress, EMI, next due, payment history, record payment / close)
- Add loan
- Credit card detail (card visual, utilisation, bill, min due, APR)
- Add credit card
- Reminders (upcoming / overdue / done, mark paid / snooze, calendar-style list)
- Add reminder / bill (repeat, remind-before)
- Insights (income vs expense chart, savings rate, budgets, export)

**Profile & settings**
- Profile (plan badge, Go Premium, menu)
- Subscription / paywall (Free vs Premium, yearly/monthly, trial)
- Currency & region
- Notifications settings (reminders, channels)
- Security (Face ID, 2FA, passkeys, change password)
- Data & privacy (export, download attachments, delete account)
- Privacy Policy · Terms of Service

## Notes

- This is a **prototype** — navigation, sheets, toggles and toasts are interactive,
  but data is sample/demo (₹ INR) and forms don't persist.
- The real backend/admin lives in the Laravel app; the **web version is planned next**.
