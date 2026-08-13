<?php

/**
 * Every option list the mobile app renders in a dropdown, chip row or picker.
 *
 * The app must not hardcode any of these: they are served as-is by
 * GET /api/meta/options and repeated on the screen endpoints that need them
 * (see App\Support\Options). Keep the keys stable — the app stores them.
 */
return [

    'transactions' => [
        'income_categories' => [
            'Salary', 'Business', 'Freelance', 'Rent', 'Interest',
            'Dividends', 'Bonus', 'Gift', 'Refund', 'Other',
        ],

        'expense_categories' => [
            'Food', 'Housing', 'Transport', 'Utilities', 'Loans', 'Insurance',
            'Healthcare', 'Education', 'Shopping', 'Entertainment', 'Investments',
            'Car', 'Other',
        ],

        'payment_methods' => [
            'Cash', 'UPI', 'Debit Card', 'Credit Card', 'Bank Transfer', 'Auto Debit',
        ],
    ],

    'assets' => [
        // key => label
        'types' => [
            'bank' => 'Bank',
            'cash' => 'Cash',
            'gold' => 'Gold',
            'fixed_deposit' => 'Fixed Deposit',
            'mutual_fund' => 'Mutual Fund',
            'stocks' => 'Stocks',
            'property' => 'Property',
            'other' => 'Other',
        ],
    ],

    'debts' => [
        'loan_categories' => ['home', 'vehicle', 'gold', 'personal', 'education', 'business', 'custom'],
        'kinds' => ['loan', 'credit_card'],
    ],

    'planning' => [
        'goal_types' => [
            'emergency_fund' => 'Emergency Fund',
            'savings' => 'Savings',
        ],
    ],

    'reminders' => [
        'kinds' => ['bill', 'subscription', 'emi'],
        // `one_time` is stored and behaves like `none`; both mean "does not repeat".
        'repeat_options' => ['none', 'one_time', 'weekly', 'monthly', 'yearly'],
        'remind_days_before' => [0, 1, 3, 5, 7],
    ],

    'family' => [
        'categories' => [
            'Groceries', 'Housing', 'Utilities', 'Education',
            'Healthcare', 'Transport', 'Entertainment', 'Other',
        ],
    ],

    'onboarding' => [
        'goals' => [
            'get_out_of_debt' => 'Get out of debt',
            'build_emergency_fund' => 'Build an emergency fund',
            'save_for_goal' => 'Save for a big goal',
            'track_spending' => 'Track my spending',
            'grow_wealth' => 'Grow my wealth',
        ],
    ],

    'notifications' => [
        // key => label, shown as toggles on the notification settings screen.
        'channels' => [
            'bill_reminders' => 'Bill reminders',
            'budget_alerts' => 'Budget alerts',
            'goal_milestones' => 'Goal milestones',
            'weekly_summary' => 'Weekly summary',
            'debt_tips' => 'Debt payoff tips',
        ],
    ],

    'region' => [
        // key => label. Which grouping style amounts are written in.
        'number_formats' => [
            'indian' => '1,00,000 (Indian)',
            'international' => '100,000 (International)',
        ],

        // Timezones offered when a country has none of its own.
        'fallback_timezones' => ['UTC', 'Asia/Kolkata'],
    ],

];
