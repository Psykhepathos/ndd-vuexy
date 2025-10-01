<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para adicionar headers de segurança em todas as respostas da API
 *
 * SECURITY HEADERS:
 * - X-Content-Type-Options: Previne MIME type sniffing
 * - X-Frame-Options: Previne clickjacking
 * - X-XSS-Protection: Ativa proteção XSS em navegadores antigos
 * - Referrer-Policy: Controla informações de referrer
 * - Content-Security-Policy: Restringe recursos carregados
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Previne navegadores de "adivinhar" MIME type (MIME sniffing)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Previne a página de ser renderizada em um frame/iframe (clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Ativa proteção XSS em navegadores antigos (Edge, IE)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Controla quanto de informação de referrer é enviado
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - restringe recursos que podem ser carregados
        // Para API JSON, mantemos simples
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        // Força HTTPS em produção (HSTS)
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Remove header que expõe tecnologia usada
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
