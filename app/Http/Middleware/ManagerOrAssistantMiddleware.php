<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ManagerOrAssistantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
         $manager = Auth::guard('manager')->user();
        $assistant = Auth::guard('assistant')->user();

        if ($manager || $assistant) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
    }

