# MoneyCoach API — curl collection

Base URL: `https://projects-money-coch.rmsiry.easypanel.host/api`

Test account: `test@example.com` / `password`

Run the **Login** curl first, copy the `token` from the response, and paste it into `TOKEN` below.

```bash
BASE="https://projects-money-coch.rmsiry.easypanel.host/api"
TOKEN="68|EriqPtxr0oNq0YWsADxsLtnJXr3TgLKU2nvn41az04ee7e78"
```


## Auth

### Login
> Response me `token` aata hai — usko baaki sab requests me Bearer token ki tarah use karo

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password", "device_name": "iPhone 15"}'
```

### Register

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name": "New User", "email": "new@example.com", "password": "password", "password_confirmation": "password", "device_name": "iPhone 15"}'
```

### Forgot password

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/auth/forgot-password" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com"}'
```

### Reset password

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/auth/reset-password" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"token": "RESET_TOKEN", "email": "test@example.com", "password": "newpass123", "password_confirmation": "newpass123"}'
```

### Me

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/user" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Logout

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/auth/logout" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Logout all devices

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/auth/logout-all" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Public

### Legal - privacy
> No auth needed

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/legal/privacy" \
  -H "Accept: application/json"
```

### Legal - terms
> No auth needed

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/legal/terms" \
  -H "Accept: application/json"
```


## Onboarding

### Get onboarding

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/onboarding" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Save onboarding

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/onboarding" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"currency": "INR", "locale": "en-IN", "country": "IN", "primary_goal": "get_out_of_debt", "notifications_enabled": true}'
```


## Home

### Dashboard

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/dashboard" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Debt payoff coach

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/coach" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Transactions

### List (current month)
> type = all | income | expense. No month filter.

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/transactions?type=all" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create entry

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/entries" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type": "expense", "amount": 1250.5, "category": "Food", "description": "Dinner", "payee": "Swiggy", "method": "UPI", "occurred_on": "2026-08-11"}'
```

### Update entry

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/entries/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type": "expense", "amount": 999, "category": "Shopping", "description": "Updated", "occurred_on": "2026-08-11"}'
```

### Delete entry

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/entries/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Debts

### List

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/debts" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Show

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/debts/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create
> kind = loan | credit_card

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/debts" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "HDFC Millennia", "institution": "HDFC", "kind": "credit_card", "interest_rate": 42, "balance": 84000, "credit_limit": 200000, "min_due": 4200, "due_day": 5, "statement_day": 20}'
```

### Update

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/debts/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "HDFC Millennia", "kind": "credit_card", "interest_rate": 38, "balance": 75000}'
```

### Delete

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/debts/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Record payment

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/debts/1/payment" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 5000}'
```

### Upload document
> multipart/form-data: file=@/path/loan.pdf

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/debts/1/documents" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### View document

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/debt-documents/1/view" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Download document

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/debt-documents/1/download" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Delete document

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/debt-documents/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Net Worth

### Net worth + assets

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/net-worth" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create asset
> type = bank|cash|gold|fixed_deposit|mutual_fund|stocks|property|other

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/assets" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "ICICI Savings", "type": "bank", "balance": 250000, "note": "Primary account"}'
```

### Update asset

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/assets/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "ICICI Savings", "type": "bank", "balance": 300000}'
```

### Delete asset

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/assets/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Planning

### Budgets + goals
> spent is calculated for CURRENT month only

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/planning" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create budget
> category must be unique per user

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/budgets" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"category": "Groceries", "limit": 9000}'
```

### Update budget

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/budgets/13" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"category": "Food", "limit": 18000}'
```

### Delete budget

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/budgets/13" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create goal
> type = emergency_fund | savings

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/goals" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "New Laptop", "type": "savings", "target": 150000, "saved": 10000, "target_date": "2027-01-31"}'
```

### Update goal

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/goals/7" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Goa Vacation", "type": "savings", "target": 140000, "target_date": "2026-12-29"}'
```

### Delete goal

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/goals/7" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Contribute to goal

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/goals/7/contribute" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 5000}'
```


## Reminders

### List

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/reminders" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create bill
> kind = bill|subscription|emi, repeat = none|weekly|monthly|yearly

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/bills" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Netflix", "kind": "subscription", "category": "Entertainment", "amount": 649, "due_date": "2026-08-20", "repeat": "monthly", "remind_days_before": 2}'
```

### Update bill

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/bills/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Netflix", "kind": "subscription", "amount": 799, "due_date": "2026-09-20", "repeat": "monthly", "remind_days_before": 3}'
```

### Delete bill

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/bills/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Mark paid

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/bills/1/paid" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Family

### Overview

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/family" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Create household

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/family" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Sharma Family"}'
```

### Delete household

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/family" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Leave household

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/family/leave" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Invite member

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/family/invite" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"email": "priya@example.com"}'
```

### Cancel invitation

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/family/invitations/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Show join (by token)

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/family/join/INVITE_TOKEN" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Accept join

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/family/join/INVITE_TOKEN" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Remove member

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/family/members/2" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Add shared expense

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/family/expenses" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 2400, "category": "Groceries", "description": "Big Basket", "occurred_on": "2026-08-11"}'
```

### Delete shared expense

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/family/expenses/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Add shared budget

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/family/budgets" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"category": "Groceries", "limit": 12000}'
```

### Delete shared budget

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/family/budgets/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Insights

### Yearly analytics
> year defaults to current year

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/insights?year=2026" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Calendar
> month format YYYY-MM

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/calendar?month=2026-08" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Search

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/search?q=swiggy" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Achievements

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/achievements" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Notifications

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/notifications" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Reports

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/reports?month=2026-08" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Export CSV
> Returns a CSV file

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/reports/export?month=2026-08" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Challenges

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/challenges" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Join challenge
> key = save_5000|save_10000|no_spend_7|cut_fuel_10|cut_dining_3000

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/challenges" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"key": "save_10000"}'
```

### Update progress

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/challenges/1/progress" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 2500}'
```

### Leave challenge

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/challenges/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Vault

### Gate status
> Tells you if a PIN is set / vault unlocked

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/vault/gate" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Set PIN
> 4-6 digits

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/vault/pin" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pin": "1234", "pin_confirmation": "1234"}'
```

### Unlock
> Required before the endpoints below

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/vault/unlock" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pin": "1234"}'
```

### Lock

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/vault/lock" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### List documents
> 401/403 if locked

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/vault" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Upload document
> multipart/form-data: file=@/path/aadhaar.pdf, title=Aadhaar

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/vault/documents" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### View document

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/vault/documents/1/view" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Download document

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/vault/documents/1/download" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Update document

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/vault/documents/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "Aadhaar Card"}'
```

### Delete document

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/vault/documents/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```


## Profile

### Show

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/profile" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Update

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/profile" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test User", "email": "test@example.com"}'
```

### Upload photo
> multipart/form-data: photo=@/path/avatar.jpg

```bash
curl -X POST "https://projects-money-coch.rmsiry.easypanel.host/api/profile/photo" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Delete photo

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/profile/photo" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Change password

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/password" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"current_password": "password", "password": "newpass123", "password_confirmation": "newpass123"}'
```

### Delete account
> Destructive

```bash
curl -X DELETE "https://projects-money-coch.rmsiry.easypanel.host/api/account" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "password"}'
```


## Settings

### Get region

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/settings/region" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Update region

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/settings/region" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"currency": "INR", "locale": "en-IN", "country": "IN"}'
```

### Get notifications

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/settings/notifications" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Update notifications

```bash
curl -X PUT "https://projects-money-coch.rmsiry.easypanel.host/api/settings/notifications" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"notifications_enabled": true, "bill_reminders": true, "budget_alerts": true, "goal_milestones": true, "weekly_summary": false, "debt_tips": true}'
```

### Data & privacy

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/settings/data-privacy" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Export my data
> Returns a JSON download

```bash
curl -X GET "https://projects-money-coch.rmsiry.easypanel.host/api/settings/data-privacy/export" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```
