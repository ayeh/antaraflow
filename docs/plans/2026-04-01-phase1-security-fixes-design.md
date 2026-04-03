# Phase 1 Security Fixes Design

**Date:** 2026-04-01
**Scope:** 9 critical/quick-win security fixes from pentest report
**Approach:** Minimal targeted patches — no refactoring, no new abstractions

## Fixes

1. **Remove hard-coded OpenAI API key** — `.env.example:93` → empty placeholder
2. **Fix stored XSS in guest view** — `meetings/guest.blade.php:113` → escape with `e()`
3. **Fix double password hashing** — `RegisterController.php:32`, `SecuritySettingsController.php:23` → remove `Hash::make()`, let `hashed` cast handle it
4. **Add rate limiting** — `throttle:5,1` on login, `throttle:3,1` on register, `throttle:10,1` on guest/QR routes
5. **Add MIME validation on document upload** — `UploadDocumentRequest.php` → add `mimes:` rule
6. **Fix client MIME type trust** — `DocumentController.php:30` → `getMimeType()` instead of `getClientMimeType()`
7. **Add `$hidden` to WebhookEndpoint** — hide `secret` from JSON serialization
8. **Fix `.env.example` defaults** — `APP_DEBUG=false`, placeholder Reverb creds, `LOG_LEVEL=warning`
9. **Fix weak password policy** — `RegisterRequest.php:22` → `Password::defaults()`
