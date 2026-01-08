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
            $routeName = $request->route() ? $request->route()->getName() : '';

            // Block sensitive GET routes and exchange management for investors
            $blockedGetRoutes = [
                'futures.order.create',
                'futures.order.edit',
                'spot.orders.view',
                'spot.order.create.view',
                'api.documentation',
                'account-settings.index',
                'settings.index',
            ];

            $isBlockedRoute = false;
            foreach ($blockedGetRoutes as $route) {
                if ($request->routeIs($route)) {
                    $isBlockedRoute = true;
                    break;
                }
            }

            // Block all exchange management routes except switching and viewing
            if (str_starts_with($routeName, 'exchanges.') || str_starts_with($routeName, 'api.v1.exchanges.')) {
                $allowedExchangeRoutes = [
                    'exchanges.switch',
                    'api.v1.exchanges.index',
                    'api.v1.exchanges.switch',
                ];
                
                $isAllowedExchange = false;
                foreach ($allowedExchangeRoutes as $allowed) {
                    if ($request->routeIs($allowed)) {
                        $isAllowedExchange = true;
                        break;
                    }
                }
                
                if (!$isAllowedExchange) {
                    $isBlockedRoute = true;
                }
            }

            if ($isBlockedRoute) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'کاربر سرمایه‌گذار اجازه دسترسی به این بخش را ندارد.'
                    ], 403);
                }
                return redirect()->route('futures.orders')->with('error', 'کاربر سرمایه‌گذار اجازه دسترسی به این بخش را ندارد.');
            }

            // Block all spot routes for investors (Web and API)
            if (str_starts_with($routeName, 'spot.') || str_starts_with($routeName, 'api.v1.spot.')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'کاربر سرمایه‌گذار اجازه دسترسی به این بخش را ندارد.'
                    ], 403);
                }
                return redirect()->route('futures.orders')->with('error', 'کاربر سرمایه‌گذار اجازه دسترسی به این بخش را ندارد.');
            }

            // Block futures order actions for investors (API)
            $blockedApiFuturesRoutes = [
                'api.v1.futures.orders.store',
                'api.v1.futures.orders.close',
                'api.v1.futures.orders.destroy',
            ];
            foreach ($blockedApiFuturesRoutes as $route) {
                if ($request->routeIs($route)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'کاربر سرمایه‌گذار اجازه انجام این عملیات را ندارد.'
                    ], 403);
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
