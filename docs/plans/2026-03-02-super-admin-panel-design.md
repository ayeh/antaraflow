# Super Admin Panel Design

**Date:** 2026-03-02
**Status:** Approved

## Overview

A dedicated Super Admin panel at `/admin` with separate authentication, providing platform-level management for the AntaraFlow SaaS application. Covers subscription plan CRUD, user/org management, platform branding (full white-label), SMTP configuration (global + per-org), email templates, and full analytics dashboard.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Scope | All features in one go | Complete platform management from day one |
| Auth | Separate `/admin/login` | Full isolation from regular users |
| Plans | Full CRUD | Super Admin controls all plan configuration |
| Branding | Full white-label | Custom CSS, email templates, custom domain |
| SMTP | Per-org + global fallback | White-label orgs can use own email sender |
| Monitoring | Full analytics | Growth charts, revenue, churn, engagement |
| Tech stack | Blade + Tailwind + Alpine.js + Chart.js | Consistent with main app, zero new PHP deps |

## Architecture

### Directory Structure

```
app/Domain/Admin/
  Models/
    Admin.php
  Controllers/
    Auth/LoginController.php
    DashboardController.php
    SubscriptionPlanController.php
    UserController.php
    OrganizationController.php
    BrandingController.php
    SmtpController.php
    EmailTemplateController.php
    SystemController.php
  Middleware/
    AdminAuthenticated.php
  Services/
    AnalyticsService.php
    BrandingService.php
    SmtpService.php

resources/views/admin/
  layouts/app.blade.php
  auth/login.blade.php
  dashboard.blade.php
  plans/ (index, create, edit, show)
  users/ (index, show)
  organizations/ (index, show)
  branding/index.blade.php
  smtp/ (index, org)
  email-templates/ (index, edit)
  system/index.blade.php

routes/admin.php
```

### Authentication

- Separate `admins` table with `Admin extends Authenticatable` model
- Separate `admin` guard in `config/auth.php`
- Session-based authentication
- First admin created via `AdminSeeder`
- `AdminAuthenticated` middleware protects all `/admin` routes (except login)

### Routes

All under `/admin` prefix with `admin` guard middleware:

```
GET|POST  /admin/login                 Auth
POST      /admin/logout                Auth
GET       /admin/dashboard             Dashboard

GET       /admin/plans                 Plan index
GET       /admin/plans/create          Plan create form
POST      /admin/plans                 Plan store
GET       /admin/plans/{plan}/edit     Plan edit form
PUT       /admin/plans/{plan}          Plan update
DELETE    /admin/plans/{plan}          Plan delete

GET       /admin/users                 User index
GET       /admin/users/{user}          User detail
POST      /admin/users/{user}/suspend  Suspend user
POST      /admin/users/{user}/unsuspend Unsuspend user
POST      /admin/users/{user}/impersonate Impersonate

GET       /admin/organizations                     Org index
GET       /admin/organizations/{org}               Org detail
POST      /admin/organizations/{org}/suspend       Suspend org
POST      /admin/organizations/{org}/unsuspend     Unsuspend org
PUT       /admin/organizations/{org}/plan          Change plan

GET       /admin/branding              Branding form
PUT       /admin/branding              Save branding

GET       /admin/smtp                  Global SMTP config
PUT       /admin/smtp                  Save global SMTP
POST      /admin/smtp/test             Test global SMTP
GET       /admin/smtp/org              Per-org SMTP list
PUT       /admin/smtp/org/{org}        Save org SMTP
POST      /admin/smtp/org/{org}/test   Test org SMTP

GET       /admin/email-templates              Template list
GET       /admin/email-templates/{template}   Edit template
PUT       /admin/email-templates/{template}   Save template
POST      /admin/email-templates/{template}/preview  Preview

GET       /admin/system                System monitoring
POST      /admin/system/retry-job/{job}  Retry failed job
DELETE    /admin/system/failed-job/{job}  Delete failed job
```

## Database Schema

### New Tables

#### `admins`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK, auto-increment |
| name | string | |
| email | string | unique |
| password | string | hashed |
| remember_token | string(100) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `platform_settings`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| key | string | unique, indexed |
| value | text | nullable, JSON for complex values |
| created_at | timestamp | |
| updated_at | timestamp | |

Settings keys: `app_name`, `logo_url`, `favicon_url`, `primary_color`, `secondary_color`, `footer_text`, `support_email`, `custom_css`, `login_background_url`, `email_header_html`, `email_footer_html`, `custom_domain`

#### `smtp_configurations`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| organization_id | bigint | nullable FK to organizations (null = global) |
| host | string | |
| port | integer | |
| username | string | encrypted |
| password | string | encrypted |
| encryption | string | tls/ssl/none |
| from_address | string | |
| from_name | string | |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique constraint on `organization_id` (one config per org, one global).

#### `email_templates`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| slug | string | unique (welcome, password-reset, meeting-invite, etc.) |
| name | string | display name |
| subject | string | supports {{variable}} placeholders |
| body_html | text | Blade-compatible template content |
| variables | json | list of available placeholders |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

### Modified Tables

#### `subscription_plans` — add columns

| Column | Type | Notes |
|--------|------|-------|
| sort_order | integer | default 0, display ordering |
| description | text | nullable |

#### `organizations` — add columns

| Column | Type | Notes |
|--------|------|-------|
| is_suspended | boolean | default false |
| suspended_at | timestamp | nullable |
| suspended_reason | text | nullable |

## Feature Specifications

### Analytics Dashboard

**Stat cards:** Total users, total orgs, total meetings, active subscriptions, MRR.

**Growth charts (Chart.js):**
- User registrations over time (daily/weekly/monthly toggle)
- Organization growth over time
- Meeting creation trend
- Revenue breakdown by plan

**Operational metrics:**
- Churn rate
- User activity heatmap (meetings per day-of-week)
- Org engagement scores (meetings, action items, active users)
- Storage and AI usage across platform

**Tables:**
- Recent registrations (last 10)
- Top organizations by activity
- Subscription distribution (pie chart)

`AnalyticsService` aggregates all metrics with configurable date ranges and caching.

### Subscription Plan CRUD

- Sortable table with drag-to-reorder (`sort_order`)
- Create/edit form: name, slug, description, prices (monthly/yearly), features (checkboxes), limits with "Unlimited" toggle (sets `-1`)
- Activate/deactivate toggle
- Delete restricted to plans with zero active subscribers
- Subscriber count displayed per plan

### User Management

- Searchable, filterable, paginated table
- Columns: name, email, org(s), role(s), registered date, last login, status
- Actions: view detail, impersonate, suspend/unsuspend
- Bulk export CSV
- Detail page: activity log, org memberships, meeting participation

### Organization Management

- Searchable, filterable, paginated table
- Columns: name, owner, plan, member count, meeting count, storage used, created date, status
- Actions: view detail, suspend/unsuspend (with reason), change plan
- Detail page: member list, meeting stats, usage breakdown, SMTP config status

### Platform Branding

Single form with live preview sidebar:
- App name, logo upload, favicon upload
- Primary/secondary color pickers
- Footer text, support email
- Custom CSS editor (textarea with monospace font)
- Login page background image upload
- Email header/footer HTML editors
- Custom domain field

`BrandingService` resolves settings throughout the app, with aggressive caching. Replaces hardcoded values (app name, colors) with dynamic values from `platform_settings`.

### SMTP Configuration

**Global tab:** Form for host, port, username (encrypted), password (encrypted), encryption, from address, from name. "Test Connection" sends test email. `SmtpService` overrides Laravel mail config at runtime.

**Per-org tab:** Table of organizations with custom SMTP. Assign/edit per org. Toggle active/inactive. Test connection per org. Falls back to global when org has no config or config is inactive.

### Email Templates

- List all templates with status badge
- Edit: subject + body HTML with variable placeholder reference
- Available variables shown per template (e.g., `{{user_name}}`, `{{org_name}}`)
- Preview button renders with sample data
- Activate/deactivate (inactive falls back to default hardcoded template)
- Default templates seeded: welcome, password-reset, meeting-invite, action-item-reminder, meeting-finalized

### System Monitoring

- Queue: pending jobs count, failed jobs count, processing rate
- Failed jobs table with retry/delete actions
- Recent errors from Laravel log (last 20)
- System info: disk usage, PHP version, Laravel version, database size
- Cache status
- Scheduler: last run timestamps for scheduled commands

## Services

### AnalyticsService

Aggregates metrics from User, Organization, MinutesOfMeeting, ActionItem, and SubscriptionPlan models. Supports date range filtering. Results cached (15-minute TTL) to avoid heavy queries on every dashboard load.

### BrandingService

Reads from `platform_settings` table with cache-through pattern. Provides `get(key, default)` method. Bound in service container as singleton. Blade views access via `@inject('branding', BrandingService::class)` or a view composer.

### SmtpService

Resolves SMTP config for a given organization (or global). Integrates with Laravel's mail system via a custom transport configuration. Encrypted credentials decrypted at runtime using Laravel's `Crypt` facade.

## UI/Layout

Admin panel uses a separate layout (`resources/views/admin/layouts/app.blade.php`) with:
- Dark sidebar navigation (distinct from main app)
- Top bar with admin name and logout
- Breadcrumbs
- Tailwind + Alpine.js for interactivity
- Chart.js for analytics graphs
- Dark mode support (consistent with main app's dark mode toggle approach)

## Security

- Separate session guard prevents cross-authentication
- Admin credentials never stored in `users` table
- SMTP passwords encrypted at rest via `Crypt`
- Impersonation logs the event and provides a "return to admin" banner
- All admin actions should be logged (future: audit log table)
- CSRF protection on all forms
- Rate limiting on admin login
