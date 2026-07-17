<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across transactions, debts, bills and assets. (search screen)
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $results = ['transactions' => [], 'debts' => [], 'bills' => [], 'assets' => []];

        if ($q !== '') {
            $like = "%{$q}%";

            $results['transactions'] = $user->entries()
                ->where(fn ($w) => $w->where('description', 'like', $like)->orWhere('payee', 'like', $like)->orWhere('category', 'like', $like))
                ->latest('occurred_on')->limit(10)->get()
                ->map(fn ($e) => [
                    'id' => $e->id, 'title' => $e->description ?? $e->payee ?? $e->category,
                    'subtitle' => ucfirst($e->type).' · '.$e->occurred_on->format('d M'),
                    'amount' => Money::toRupees($e->amount_cents), 'type' => $e->type, 'href' => '/transactions',
                ])->all();

            $results['debts'] = $user->debts()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('institution', 'like', $like))
                ->limit(10)->get()
                ->map(fn ($d) => [
                    'id' => $d->id, 'title' => $d->name, 'subtitle' => ($d->kind === 'credit_card' ? 'Credit card' : 'Loan').' · '.$d->interest_rate.'%',
                    'amount' => Money::toRupees($d->balance_cents), 'href' => '/debts',
                ])->all();

            $results['bills'] = $user->bills()
                ->where('name', 'like', $like)->limit(10)->get()
                ->map(fn ($b) => [
                    'id' => $b->id, 'title' => $b->name, 'subtitle' => ucfirst($b->kind).' · due '.$b->due_date->format('d M'),
                    'amount' => Money::toRupees($b->amount_cents), 'href' => '/reminders',
                ])->all();

            $results['assets'] = $user->financeAccounts()
                ->where('name', 'like', $like)->limit(10)->get()
                ->map(fn ($a) => [
                    'id' => $a->id, 'title' => $a->name, 'subtitle' => 'Asset',
                    'amount' => Money::toRupees($a->balance_cents), 'href' => '/net-worth',
                ])->all();
        }

        return response()->json([
            'query' => $q,
            'results' => $results,
            'count' => collect($results)->flatten(1)->count(),
        ]);
    }
}
