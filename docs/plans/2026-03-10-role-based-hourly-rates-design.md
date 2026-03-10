# Role-Based Hourly Rate Configuration

**Date:** 2026-03-10
**Status:** Approved

## Problem

Meeting cost in Governance Analytics uses a hardcoded default of $50/hr applied uniformly to all attendees. Organizations need to configure different hourly rates per role (Admin, Manager, Member) to reflect actual staff cost structures.

## Approach

Store role-based rates in the existing `organizations.settings` JSON column. No new migration required. The analytics service resolves each attendee's org role and applies the corresponding rate.

## Data Storage

Key stored in `organizations.settings`:

```json
{
  "hourly_rates": {
    "admin": 150.0,
    "manager": 100.0,
    "member": 75.0
  }
}
```

Fallback: if org has no `hourly_rates` configured, default $50/hr is applied to all attendees (preserving existing behaviour).

## Cost Calculation Logic

Current: `total_cost = SUM(duration_hours × 50.0 × attendee_count)`

New: for each attendee in a meeting:
1. If attendee has `user_id` → look up their role in `organization_user` pivot
2. Map role (`admin`/`manager`/`member`) → rate from org settings
3. If attendee is external (no `user_id`) → use `member` rate as fallback
4. `total_cost = SUM(duration_hours × rate_for_attendee)`

## UI — Organization Settings

New **"Cost Analytics"** section added to `organizations/settings/edit.blade.php`, below Integrations:

- Admin Rate ($/hr) — number input, min 0
- Manager Rate ($/hr) — number input, min 0
- Member Rate ($/hr) — number input, min 0
- Saved via existing Settings form + `OrganizationService::updateSettings()`

## Files Changed

| File | Change |
|------|--------|
| `UpdateOrganizationSettingsRequest` | Add validation for `settings.hourly_rates.{admin,manager,member}` — nullable, numeric, min:0 |
| `organizations/settings/edit.blade.php` | Add Cost Analytics section with 3 rate inputs |
| `GovernanceAnalyticsController` | Read `hourly_rates` from org settings, pass to service |
| `GovernanceAnalyticsService` | Replace `float $hourlyRate` with `array $hourlyRates`; resolve per-attendee rate with fallback |

## Fallback Hierarchy

1. Role-specific rate from org settings
2. `member` rate from org settings (for external attendees)
3. Default $50.0 (if org has no `hourly_rates` configured at all)
