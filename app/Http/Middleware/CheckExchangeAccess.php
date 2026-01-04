<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckExchangeAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredAccess = 'any'): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        $currentExchange = $user->getCurrentExchange();
        
        // If still no current exchange and user is an investor, try parent's default exchange
        if (!$currentExchange && $user->isInvestor()) {
            $currentExchange = $user->parent->defaultExchange;
        }
        
        // If still no current exchange, try user's default exchange
        if (!$currentExchange) {
            $currentExchange = $user->defaultExchange;
        }

        // Always allow page to load, but add access info to request
        $request->attributes->set('exchange_access', [
            'current_exchange' => $currentExchange,
            'spot_access' => $currentExchange ? $currentExchange->canAccessSpot() : false,
            'futures_access' => $currentExchange ? $currentExchange->canAccessFutures() : false,
            'validation_summary' => $currentExchange ? $currentExchange->getValidationSummary() : null,
            'required_access' => $requiredAccess
        ]);

        // Check specific access requirements and mark as restricted if needed
        if (!$currentExchange) {
            $request->attributes->set('access_restricted', true);
            $request->attributes->set('restriction_reason', 'no_exchange');
        } else {
            $hasRequiredAccess = match ($requiredAccess) {
                'spot' => $currentExchange->canAccessSpot(),
                'futures' => $currentExchange->canAccessFutures(),
                'any' => $currentExchange->canAccessSpot() || $currentExchange->canAccessFutures(),
                default => true
            };

            if (!$hasRequiredAccess) {
                $request->attributes->set('access_restricted', true);
                $request->attributes->set('restriction_reason', 'insufficient_access');
                $request->attributes->set('required_access', $requiredAccess);
            }
        }

        return $next($request);
    }
}
