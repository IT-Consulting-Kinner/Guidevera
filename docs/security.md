# Security

## Authentication

### Password Hashing

Passwords are hashed using a two-layer approach:

1. **HMAC-SHA256**: `hash_hmac('sha256', password, SALT)` — normalizes password length and adds a secret
2. **bcrypt**: `password_hash(hmac_result, PASSWORD_DEFAULT)` — provides adaptive hashing

This two-layer design protects against:
- Rainbow table attacks (bcrypt's per-hash salt)
- Length-extension attacks on very long passwords (HMAC pre-hash)
- Timing attacks (bcrypt's constant-time comparison)

The HMAC salt is a static string defined in `UsersController::PASSWORD_SALT`.

### Session Management

- Sessions are managed by PHP's native session handler
- Session ID is regenerated after successful login (`$session->renew()`)
- No "remember me" feature — sessions expire with the browser
- Session data includes: id, username, fullname, role, gender, email, page_tree, status

### Rate Limiting

Login attempts are rate-limited per IP address:
- **Storage**: Filesystem-based JSON files in `storage/ratelimit/`
- **Threshold**: 5 failed attempts within 5 minutes triggers a lockout
- **Key**: MD5 hash of `"login_" + client_ip`
- **Reset**: Successful login clears the rate limit counter

## CSRF Protection

CakePHP's built-in `CsrfProtectionMiddleware` is active for all POST requests.

- **Forms**: Include a hidden `_csrfToken` field
- **AJAX**: The layout configures `$.ajaxSetup` to send the token as `X-CSRF-Token` header on every POST request

## HTML Sanitization

User-generated content (page body) is sanitized through a whitelist-based DOMDocument sanitizer (`PagesService::sanitizeHtml()`):

### Allowed Tags (42)

`p, br, hr, div, span, blockquote, pre, code, h1-h6, b, strong, i, em, u, s, sub, sup, small, mark, font, ul, ol, li, table, thead, tbody, tfoot, tr, th, td, caption, a, img, figure, figcaption, details, summary`

### Sanitization Steps

1. Remove null bytes
2. Strip dangerous tags and their content: `<script>`, `<style>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, `<input>`, `<textarea>`, `<select>`, `<button>`, `<applet>`, `<meta>`, `<link>`, `<base>`
3. Remove all `on*` event handler attributes
4. Neutralize `javascript:`, `data:`, `vbscript:` URIs in href/src/action attributes
5. DOMDocument pass: Remove disallowed tags (preserving children), strip disallowed attributes
6. Sanitize CSS `style` attributes (block `expression()`, `behavior`, `-moz-binding`, malicious `url()`)
7. Validate href/src against allowed URL schemes: `http`, `https`, `mailto`, `tel`, `//`
8. Force `rel="noopener noreferrer"` on `target="_blank"` links

## Host Header Injection Protection

The `HostHeaderMiddleware` validates the HTTP `Host` header against the configured `App.fullBaseUrl` in production. This prevents:
- Password reset link manipulation
- Cache poisoning attacks
- Web cache deception

In debug mode, the middleware is bypassed.

## File Upload Security

- **Filename validation**: Only `[a-zA-Z0-9_\-\.]` characters allowed
- **Hidden file rejection**: Filenames starting with `.` are blocked
- **Storage isolation**: Files are stored outside webroot in `storage/media/`
- **Controlled serving**: Downloads go through `FilesController::download()`, not direct file access
- **Contributor+**: Upload and delete require contributor role or higher

## Input Validation

- All POST-only endpoints use `$this->request->allowMethod(['post'])`
- Integer IDs are cast with `(int)` before use
- Status values are validated against `['active', 'inactive']`
- Search queries are stripped of non-alphanumeric characters
