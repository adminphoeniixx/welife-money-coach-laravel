<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SearchController extends Controller
{
    /**
     * Global search across transactions, debts, bills and assets. (search screen)
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $results = [
            'transactions' => [], 'debts' => [], 'bills' => [],
            'assets' => [], 'goals' => [], 'categories' => [],
        ];

        if ($q !== '') {
            // whereLike(caseSensitive: false) — a plain `like` is case-sensitive
            // on PostgreSQL, so "netflix" would miss a "Netflix" payee.
            $like = "%{$q}%";

            $results['transactions'] = $user->entries()
                ->where(fn ($w) => $w->whereLike('description', $like)->orWhereLike('payee', $like)->orWhereLike('category', $like))
                ->latest('occurred_on')->limit(10)->get()
                ->map(fn ($e) => [
                    'id' => $e->id, 'title' => $e->description ?? $e->payee ?? $e->category,
                    'subtitle' => ucfirst($e->type).' · '.$e->occurred_on->format('d M'),
                    'amount' => Money::toRupees($e->amount_cents), 'type' => $e->type, 'href' => '/transactions',
                ])->all();

            // Banks, loans and cards all live in debts — institution is the
            // bank name, so searching "HDFC" finds them.
            $results['debts'] = $user->debts()
                ->where(fn ($w) => $w->whereLike('name', $like)
                    ->orWhereLike('institution', $like)
                    ->orWhereLike('category', $like)
                    ->orWhereLike('card_network', $like))
                ->limit(10)->get()
                ->map(fn ($d) => [
                    'id' => $d->id, 'title' => $d->name, 'subtitle' => ($d->kind === 'credit_card' ? 'Credit card' : 'Loan').' · '.$d->interest_rate.'%',
                    'amount' => Money::toRupees($d->balance_cents), 'kind' => $d->kind,
                    'institution' => $d->institution, 'href' => '/debts',
                ])->all();

            $results['bills'] = $user->bills()
                ->where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('category', $like))
                ->limit(10)->get()
                ->map(fn ($b) => [
                    'id' => $b->id, 'title' => $b->name, 'subtitle' => ucfirst($b->kind).' · due '.$b->due_date->format('d M'),
                    'amount' => Money::toRupees($b->amount_cents), 'kind' => $b->kind,
                    'due_date' => $b->due_date->format('Y-m-d'), 'href' => '/reminders',
                ])->all();

            $results['assets'] = $user->financeAccounts()
                ->where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('type', $like))
                ->limit(10)->get()
                ->map(fn ($a) => [
                    'id' => $a->id, 'title' => $a->name, 'subtitle' => 'Asset',
                    'amount' => Money::toRupees($a->balance_cents), 'href' => '/net-worth',
                ])->all();

            $results['goals'] = $user->goals()
                ->whereLike('name', $like)->limit(10)->get()
                ->map(fn ($g) => [
                    'id' => $g->id, 'title' => $g->name,
                    'subtitle' => 'Goal · '.(int) round($g->progress()).'% funded',
                    'amount' => Money::toRupees($g->saved_cents), 'href' => '/planning',
                ])->all();

            $results['categories'] = $this->categories($request, $q);
        }

        return response()->json([
            'query' => $q,
            'results' => $results,
            'count' => array_sum(array_map('count', $results)),
        ]);
    }

    /**
     * Matching spend categories with this month's total for each, so tapping
     * one goes straight to a filtered view that has real numbers behind it.
     *
     * @return list<array<string, mixed>>
     */
    private function categories(Request $request, string $q): array
    {
        $now = Carbon::now();
        $user = $request->user();

        $known = collect(array_merge(Options::incomeCategories(), Options::expenseCategories()))
            ->merge($user->entries()->distinct()->pluck('category'))
            ->merge($user->budgets()->whereNull('household_id')->pluck('category'))
            ->filter(fn ($c) => is_string($c) && $c !== '')
            ->unique()
            ->filter(fn (string $c) => str_contains(mb_strtolower($c), mb_strtolower($q)))
            ->take(10);

        if ($known->isEmpty()) {
            return [];
        }

        $spent = $user->entries()->where('type', 'expense')
            ->whereBetween('occurred_on', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->whereIn('category', $known->values()->all())
            ->selectRaw('category, sum(amount_cents) as s')
            ->groupBy('category')->pluck('s', 'category');

        return $known->values()->map(fn (string $c) => [
            'id' => $c,
            'title' => $c,
            'subtitle' => 'Category · this month',
            'amount' => Money::toRupees((int) ($spent[$c] ?? 0)),
            'href' => '/transactions',
        ])->all();
    }
}
