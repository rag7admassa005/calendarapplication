<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class ManagerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   public function handle(Request $request, Closure $next): Response
    {
        // جرب التوثيق كمدير
        if (Auth::guard('manager')->check()) {
            return $next($request);
        }

        // جرب التوثيق كمساعد
        if (Auth::guard('assistant')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
