# Security

## Authentication

### Password Hashing

Two-layer hashing: `password_hash(hash_hmac('sha256', $password, $salt), PASSWORD_DEFAULT)`. The HMAC pre-hash uses the CakePHP Security salt, followed by bcrypt. This protects against extremely long passwords that could cause bcrypt DoS.

### Session Management

- `session_regenerate_id()` via `$session->renew()` on login (called before writing Auth data to prevent session fixation)
- Session destroyed on logout
- `Cache-Control: no-cache, no-store, must-revalidate` on all responses to prevent stale authenticated pages

### Rate Limiting

Login attempts are rate-limited per IP. After 5 failed attempts within 15 minutes, further attempts are blocked. Rate limit data is stored in `storage/ratelimit/`.

## Authorization

Role-based access control with hierarchical levels: guest < editor < contributor < admin. `AppController::hasRole()` checks `userLevel >= minLevel`. See [Architecture](architecture.md) for the full permission matrix.

Self-protection: admins cannot change their own role or status via the user management interface.

## Content Security Policy

`CspMiddleware` adds CSP headers with nonce-based script execution:

```
default-src 'self'; script-src 'self' 'nonce-{random}' 'unsafe-inline' 'unsafe-hashes';
style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self';
```

All inline `<script>` tags include the nonce attribute.

## CSRF Protection

CakePHP's built-in `CsrfProtectionMiddleware` is active. All POST requests require a valid CSRF token. AJAX requests include the token via `jQuery.ajaxSetup` with `X-CSRF-Token` header.

## Host Header Validation

`HostHeaderMiddleware` validates the `Host` header against a whitelist to prevent host header injection attacks.

## HTML Sanitization

All user-generated HTML content is sanitized via `PagesService::sanitizeHtml()` before display. This removes `<script>` tags, `on*` event handlers, `javascript:` URLs, and adds `rel="noopener noreferrer"` to external links.

## File Access Control

Each file has per-role visibility flags (`visible_guest`, `visible_editor`, `visible_contributor`, `visible_admin`). The download endpoint checks the requesting user's role against these flags and returns 403 if access is denied.

## Soft Deletes

Pages use soft delete (`deleted_at` timestamp). Users are set to `status='deleted'` to preserve referential integrity. Trash auto-purge is configurable via `trashRetentionDays`.
