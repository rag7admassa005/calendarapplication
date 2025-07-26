<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class AuthenticateAssistant
{
   public function handle(Request $request, Closure $next): Response
    {
         $assistant = Auth::guard('assistant')->user();

        if (!$assistant) 
         {
             return response()->json([
                'message' => 'Unauthorized. Token is missing or invalid.'
            ], 401);
        }
        return $next($request);
    }
}
