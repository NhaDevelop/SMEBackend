<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   public function handle(Request $request, Closure $next, $role)
{
    if (!auth('api')->check() || auth('api')->user()->role !== $role) {
        return response()->json(['error' => 'Forbidden Access'], 403);
    }
    return $next($request);
}

}
