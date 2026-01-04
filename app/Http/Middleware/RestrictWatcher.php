<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictWatcher
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->isInvestor()) {
            // Block sensitive GET routes for investors
            $blockedGetRoutes = [
                'exchanges.create',
                'exchanges.edit',
                'futures.order.create',
                'futures.order.edit',
                'spot.order.create.view',
                'account-settings.index',
                'settings.index',
            ];

            foreach ($blockedGetRoutes as $route) {
                if ($request->routeIs($route)) {
                    return redirect()->route('futures.orders')->with('error', 'کاربر سرمایه‌گذار اجازه دسترسی به این بخش را ندارد.');
                }
            }

            // Allow GET requests (viewing)
            if ($request->isMethod('GET')) {
                return $next($request);
            }

            // Allow logout
            if ($request->routeIs('logout')) {
                return $next($request);
            }

            $allowedRoutes = [
                'exchanges.switch',
                'password.change',
                'profile.update-name',
            ];

            foreach ($allowedRoutes as $route) {
                if ($request->routeIs($route)) {
                    return $next($request);
                }
            }

            // Block everything else for investors
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'کاربر سرمایه‌گذار اجازه انجام این عملیات را ندارد.'
                ], 403);
            }

            return redirect()->back()->with('error', 'کاربر سرمایه‌گذار اجازه انجام این عملیات را ندارد.');
        }

        return $next($request);
    }
}
