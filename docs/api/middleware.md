# Middleware API Reference

## CspMiddleware

Adds Content-Security-Policy header to all responses. Generates a random nonce per request, stored as request attribute `cspNonce`. Templates use this nonce on all `<script>` tags.

Policy includes: `default-src 'self'`, `script-src 'self' 'nonce-{n}' 'unsafe-inline' 'unsafe-hashes'`, `style-src 'self' 'unsafe-inline'`, `img-src 'self' data: blob:`, `font-src 'self'`.

## HostHeaderMiddleware

Validates the HTTP `Host` header against a configurable whitelist. Returns 400 for invalid hosts. Prevents host header injection attacks that could affect password reset links or other host-dependent URLs.
