<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    /**
     * Static legal copy for the in-app legal screens.
     * (legalPrivacy / legalTerms screens)
     */
    public function show(string $document): JsonResponse
    {
        $content = match ($document) {
            'privacy' => [
                'title' => 'Privacy Policy',
                'updated_at' => '2026-07-17',
                'body' => 'MoneyCoach stores your financial data securely and never sells it. '
                    .'Documents in your vault are encrypted at rest. You can export or delete '
                    .'your data at any time from Settings → Data & Privacy.',
            ],
            'terms' => [
                'title' => 'Terms of Service',
                'updated_at' => '2026-07-17',
                'body' => 'MoneyCoach provides personal financial coaching for informational '
                    .'purposes only and is not financial advice. Use the app responsibly and '
                    .'keep your login credentials safe.',
            ],
            default => abort(404),
        };

        return response()->json($content);
    }
}
