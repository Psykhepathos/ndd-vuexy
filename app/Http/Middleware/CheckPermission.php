<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Nome da permissão no formato "modulo.acao"
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }

        // Admin do sistema legado tem acesso total
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Verifica permissão pelo novo sistema de roles
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Você não tem permissão para realizar esta ação.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
