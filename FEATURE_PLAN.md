# MoneyCoach — Feature Plan

## 1. Product summary

MoneyCoach is a personal-finance and debt-management platform. It helps people record income and expenses, track bills, loans, EMIs and credit cards, receive timely reminders, and follow a clear debt-payoff plan.

The name, logo and final brand identity are placeholders and must be configurable before launch.

## 2. User roles

| Role | Main access |
| --- | --- |
| User | Own financial data, reminders, insights and subscription |
| Admin | User support, category templates, content, subscriptions and platform monitoring |

## 3. MVP (launch-ready core)

### A. Account and onboarding

- Email/password and social sign-in
- Currency, country, timezone and monthly income setup
- Optional financial goals: reduce debt, track spending, never miss a due date
- Consent-based notification permissions
- Demo/empty-state guidance for first-time users

### B. Dashboard

- Current month income, expenses, cash flow and savings
- Total outstanding debt and total monthly EMI/payment commitment
- Upcoming due dates and overdue alerts
- Highest-priority debt action
- Spending breakdown and category trend charts
- Quick add: income, expense, loan, credit card and bill

### C. Income tracking

- Add, edit, delete and search income entries
- Categories: salary, business, freelance, rent, interest, dividends, investments, pension, benefits, bonus, gifts, cashback, refund and custom
- Fields: category, amount, received date, payment method, received from, notes and attachment
- Monthly income history and category totals

### D. Expense tracking

- Add, edit, delete, filter and search expenses
- Category groups from the supplied list: housing, utilities, food, transport, loans/cards, insurance, healthcare, education, personal, entertainment, investments, taxes, business and miscellaneous
- Fields: category, amount, date, payment method, paid to, notes, attachment, recurring flag and due-date reminder
- Monthly budget per category and spend-versus-budget progress
- Recurring expenses automatically create the next expected payment

### E. Loans and money owed

- Create personal, home, vehicle, gold, education, business and custom loans
- Track money borrowed from individuals and money lent to others
- Fields: lender/borrower, purpose, original amount, outstanding balance, interest rate, EMI/payment amount, due date, start/end date and status
- Payment history and balance adjustment after a payment
- Loan list with active/closed status and next due date

### F. Credit cards

- Store bank, card name, credit limit, available credit and used/outstanding credit
- Capture statement date, current bill, minimum due, interest, due date and payment status
- Record payments and update last-payment information
- Configurable reminders: 1, 3, 5 or 7 days before due date
- Credit utilisation indicator with high-utilisation warning

### G. Bills, recurring commitments and reminders

- One-time and recurring bill/EMI reminders
- In-app and push notifications; email optional
- Reminder schedule for due soon, due today and overdue
- Mark paid, snooze, edit or skip from reminder view
- Dedicated calendar/list of upcoming commitments

### H. Debt coach (rules-based MVP)

- Consolidated debt view: cards + loans + personal borrowing
- Two payoff strategies:
  - **Snowball:** smallest outstanding balance first
  - **Avalanche:** highest interest rate first
- Recommended priority debt, reason for recommendation and next action
- Estimated debt-free date using current monthly payments
- Estimate interest saved for an extra payment / early closure
- Show total debt, total interest rate exposure and monthly payment burden

### I. Reports and data controls

- Monthly income/expense/cash-flow report
- Spending-by-category and debt progress charts
- Export user data to CSV/PDF
- Secure attachment access and account-data deletion request

## 4. Premium features (post-MVP)

- Unlimited accounts, cards, loans, attachments and budgets
- Advanced debt scenarios: choose extra-payment amount and compare payoff plans
- Smart spending alerts and custom budget rules
- Multi-currency wallets and exchange-rate conversion
- Shared household/family finances with roles and private items
- Bank/SMS/email transaction import where legally and technically supported
- Receipt scanning and automatic category suggestions
- Advanced reports, scheduled exports and accountant-ready summaries
- Priority support and ad-free experience

## 5. Globalisation requirements

- Store money as integer minor units with ISO currency codes; never assume INR only
- Timezone-aware due dates, reminder scheduling and date formatting
- Localised language, number/date formats and category labels
- Configurable tax/income labels by country
- Privacy controls, encryption, clear consent and data export/deletion workflows
- Region-aware notification channels and compliance review before bank integrations

## 6. Main navigation

1. Home — financial snapshot and urgent actions
2. Transactions — income and expenses
3. Debts — loans, credit cards and payoff coach
4. Reminders — upcoming, overdue and completed commitments
5. Insights — reports, trends and budgets
6. Profile — currency, notifications, subscription, security and data controls

## 7. Key user flows

### First-time setup

Sign up → select country/currency/timezone → choose goal → add first income → add loans/cards/bills → enable reminders → view dashboard recommendation.

### Record an expense

Quick add → choose category → enter amount/date/payment method → optionally add recipient, note, attachment and recurrence → save → dashboard and budget update.

### Repay a debt

Open Debt Coach → compare Snowball/Avalanche → view recommended account → record payment or extra payment → outstanding balance, payoff date and savings update.

### Pay a bill

Receive alert → open reminder → mark paid / snooze → payment status and next recurring reminder update.

## 8. Delivery phases

| Phase | Scope | Outcome |
| --- | --- | --- |
| 0. Foundation | Brand tokens, auth, data model, onboarding, privacy/security baseline | Usable secure shell |
| 1. Finance core | Income, expenses, categories, recurring items, dashboard | Daily expense tracker |
| 2. Commitments | Loans, credit cards, bills, payments and reminders | Never-miss-due-date product |
| 3. Debt coach | Consolidated debt, Snowball/Avalanche, payoff and savings calculations | Differentiating core value |
| 4. Insights & monetisation | Budgets, reports, exports, paywall/subscriptions | Launchable business product |
| 5. Scale | Localisation, multi-currency, imports, family sharing, advanced AI | Global expansion |

## 9. MVP acceptance criteria

- A user can add income, expense, loan and credit-card records with the required fields.
- The dashboard shows correct monthly totals, debt total and upcoming commitments.
- The user receives configurable reminders before every eligible due date.
- Payments update the related loan/card balance and payment history.
- The debt coach ranks debts correctly for both Snowball and Avalanche methods.
- The app calculates a projected debt-free date and clearly states its assumptions.
- Users can export and delete their own data.

## 10. Important product decisions to confirm before development

1. Initial launch countries, currencies and languages.
2. Notification channels for MVP: push only, or push plus email/WhatsApp/SMS.
3. Whether bank/SMS transaction import is in scope for the first release.
4. Freemium limits and premium price model.
5. Exact debt payoff calculation rules, especially for variable interest and credit-card minimum payments.
