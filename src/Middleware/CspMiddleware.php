<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Content Security Policy Middleware.
 *
 * script-src uses nonce-based policy. All inline event handlers (onclick etc.)
 * have been refactored to use data-action attributes with delegated listeners.
 * 'unsafe-hashes' is required because jQuery internally uses setAttribute()
 * to set event handler attributes on DOM elements.
 *
 * style-src 'unsafe-inline':
 *   Required by Summernote WYSIWYG editor which dynamically injects inline styles.
 *   Low risk: inline styles cannot execute JavaScript.
 */
class CspMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $nonce = base64_encode(random_bytes(16));
        $request = $request->withAttribute('cspNonce', $nonce);

        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json') || str_contains($contentType, 'text/xml')) {
            return $response;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);

        return $response
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
