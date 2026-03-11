<?php

declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Content Security Policy Middleware.
 *
 * Uses nonce-based CSP for script-src to eliminate 'unsafe-inline'.
 * style-src still requires 'unsafe-inline' because Summernote WYSIWYG
 * editor injects inline styles at runtime. This is a known limitation
 * documented as a trade-off for WYSIWYG functionality.
 */
class CspMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $nonce = base64_encode(random_bytes(16));
        $request = $request->withAttribute('cspNonce', $nonce);

        $response = $handler->handle($request);

        // Skip CSP for JSON/XML API responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json') || str_contains($contentType, 'text/xml')) {
            return $response;
        }

        if (Configure::read('debug')) {
            // Development: relaxed for debugging
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self'
                'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; frame-ancestors 'self'";
        } else {
            // Production: nonce-based script-src, no unsafe-inline for scripts
            // Summernote still needs unsafe-inline for style-src (inline styles on contenteditable)
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "font-src 'self'",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
        }

        return $response
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
