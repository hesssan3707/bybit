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

        if ($user && $user->isWatcher()) {
            // Block sensitive GET routes for watchers (forms and management)
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
                    return redirect()->route('futures.orders')->with('error', 'کاربر ناظر اجازه دسترسی به این بخش را ندارد.');
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

            // Allow order cancellation (Future feature, but keeping it for now if needed)
            // For now, let's stick to "only view" as per current requirement
            $allowedRoutes = [
                'exchanges.switch',
            ];

            foreach ($allowedRoutes as $route) {
                if ($request->routeIs($route)) {
                    return $next($request);
                }
            }

            // Block everything else for watchers
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'کاربر ناظر اجازه انجام این عملیات را ندارد.'
                ], 403);
            }

            return redirect()->back()->with('error', 'کاربر ناظر اجازه انجام این عملیات را ندارد.');
        }

        return $next($request);
    }
}
