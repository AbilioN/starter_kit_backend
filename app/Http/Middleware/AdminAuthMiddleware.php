<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;

class AdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Verificar se o usuário autenticado é um admin
        // Primeiro, verificar se o modelo é Admin
        if (!$user instanceof Admin) {
            return response()->json(['message' => 'Access denied. Admin privileges required.'], 403);
        }

        // Verificar se o admin está ativo
        if (!$user->isActive()) {
            return response()->json(['message' => 'Access denied. Admin privileges required.'], 403);
        }

        return $next($request);
    }
}
