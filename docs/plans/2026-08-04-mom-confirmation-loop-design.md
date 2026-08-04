# MOM Confirmation Loop Design

**Date:** 2026-08-04
**Problem:** Sharing is one-way. Attendees and clients receive a finished MOM with no way to check it, correct it, or confirm it — so the document carries no evidence that anyone agreed to it.
**Approach:** Turn sharing into a lifecycle stage. A finalized MOM is circulated to individually-tokenised attendees who confirm or request anchored amendments; silence past a deadline confirms by default, objections hold it.

## Context

antaraNote already has most of the parts and none of the wiring:

- `MeetingStatus` runs `Draft → InProgress → Finalized → Approved`, but `approve()` is an internal button. Attendees never enter the equation.
- `Comment` is polymorphic, threaded, has `client_visible` and reactions — but `comments.user_id` is `NOT NULL` with an FK, so a guest cannot comment. The blocker is schema, not policy.
- `MomAttendee` already stores `email`. We already know who to send to.
- `MomEmailDistribution` already blasts email — but it attaches a PDF instead of linking to a live page. Mail goes out; nothing comes back.
- `VersionService` and `AuditService` already exist and are the backbone an audit trail needs.

Competitive research across Fireflies, Otter, Fellow, Sembly, Fathom, tl;dv and Notion found **none** of them offer guest acknowledgement, e-approval on a shared page, or live action-item status. Every one is one-way. This is open ground, not catch-up.

Malaysian meeting practice has a standing agenda item — *Pengesahan Minit Mesyuarat Yang Lalu*. US-centric tools never modelled it. This feature digitises a ritual the target market already performs monthly.

## Decisions taken

| Decision | Choice | Rejected |
|---|---|---|
| Where the review round sits | On **Finalized**, before Approved | On draft before finalize; both stages |
| How Approved is reached | **Deadline-driven** — silence is consent | Advisory (secretary decides); threshold engine |
| Remark granularity | **Anchored** to topic / action item | Single free-text box; suggestion-mode replacement |
| Data model | **New `mom_circulations` + `mom_circulation_recipients`** | Extend `MomGuestAccess`; single merged table |
| Priorities | Governance/trust **and** viral acquisition | Live action-item status (separate feature) |

Two systems currently serve share links: `share/{token}` (`MeetingShare`, Collaboration) and `guest/{token}` (`MomGuestAccess`, Meeting). This design builds only on the Meeting path. The Collaboration path is pre-existing debt — do not extend it, do not refactor it here.

## Data model

### `mom_circulations`

A circulation is a **round**, an event — not a property of a link. Round 2 after amendments then has a natural home, and the deadline is stored once.

| Column | Notes |
|---|---|
| `organization_id`, `minutes_of_meeting_id` | FK cascade |
| `sent_by` | FK users |
| `round` | tinyint, default 1 |
| `subject`, `body_note` | accompanying email |
| `deadline_at` | nullable timestamp (see traps) |
| `status` | `open` / `awaiting_secretary` / `closed_approved` / `closed_amended` / `cancelled` |
| `closed_at` | nullable timestamp |

### `mom_circulation_recipients`

| Column | Notes |
|---|---|
| `mom_circulation_id` | FK cascade |
| `mom_attendee_id` | FK **nullable** |
| `name`, `email` | **snapshot at send time** |
| `token` | string 64, unique |
| `response` | `null` / `confirmed` / `amendment_requested` |
| `responded_at` | nullable — explicit response only |
| `deemed_confirmed_at` | nullable — set by the deadline job only |
| `first_opened_at`, `last_opened_at`, `open_count` | chase list, and the seat-expansion signal |
| `delivery_failed_at` | nullable — excluded from tally entirely |
| `responded_ip`, `responded_user_agent` | audit evidence |

**Name and email are snapshotted, not read through `MomAttendee`.** If an attendee is deleted or renamed six months later, the record *"Ahmad confirmed on 5 August"* must stay true. An audit record cannot shift when live data shifts — which is why `mom_attendee_id` is nullable.

**`deemed_confirmed_at` is separate from `responded_at`.** One column means *"they opened it and pressed Confirm"*; the other means *"they stayed silent until the deadline"*. When a MOM is disputed, that distinction is what determines the document's weight. Collapsing them into one flag destroys the most important fact the feature produces.

### `comments` alteration

- `user_id` → **nullable**
- add `mom_circulation_recipient_id` nullable FK
- invariant: exactly one of the two is set, enforced in the model (SQLite in tests cannot express this as a CHECK)

This buys guest remarks for free: threading, `client_visible`, morphTo onto `MomTopic` / `MomActionItem`, and reactions all already exist.

### Status flow

Add `PendingConfirmation = 'pending_confirmation'` to `MeetingStatus`.

```
Finalized ──[Circulate]──> PendingConfirmation
                                   │
        ┌──────────────────────────┤
        │                          │
  deadline passed,          open amendment
  no open amendment         request exists
        │                          │
        ▼                          ▼
    Approved            AUTO-APPROVE HELD
                        status = awaiting_secretary
                                   │
                     ┌─────────────┴─────────────┐
               accept amendment            dismiss remark
               → new version               → deadline may pass
               → round 2 if material
```

Direct edits are locked while `PendingConfirmation`. People are in the middle of confirming a document — if the text shifts under them, their confirmation means nothing. Amendments must travel through the accept-amendment flow, which creates a version. The secretary retains manual approve-early and cancel.

## Guest experience

New route `/mom/confirm/{token}`, separate from `/guest/{token}` — this token identifies **a person**, not merely a document.

```
┌──────────────────────────────────────────────────┐
│ ⏳ Please confirm before Fri, 8 Aug · 5:00 PM     │  ← sticky
│    No response = these minutes are confirmed     │
├──────────────────────────────────────────────────┤
│ You: Nor Khamisah (khamisah@rw.my)   Not you?    │
├──────────────────────────────────────────────────┤
│   [existing document rendering]                  │
│   Topics ── each row exposes [💬 Remark]         │
│   Action Items ── each row exposes [💬 Remark]   │
│   ➕ Something missing? [free-text, unanchored]  │
├──────────────────────────────────────────────────┤
│  [📤 Send 1 Amendment]   [✅ Confirm anyway]     │  ← sticky
└──────────────────────────────────────────────────┘
```

**The deadline is the hero, not fine print.** We auto-approve on silence, so the guest must understand that not replying means agreeing — stated large at the top. If someone later says *"I never agreed"*, our defence is that the warning was impossible to miss. A design that buries this condition destroys the audit value the whole feature exists to create.

**Identity is shown at the top.** Token equals identity. If a link is forwarded and the wrong person opens it, they must notice immediately. *"Not you?"* gives an honest exit and gives us a leaked-link signal.

**Buttons reflect state rather than sitting fixed.** No remarks yet → Confirm is primary. Remarks pending → *Send N Amendments* becomes primary, and confirming anyway requires acknowledging the dangling remarks.

**Withdrawal is allowed until the deadline.** Confirmation that cannot be undone makes people hesitate to press the button, and hesitation kills the response rate the entire feature depends on.

**Mobile-first, not responsive as an afterthought.** These links arrive by email and WhatsApp and are opened on phones. Research found Fellow and Sembly still awkward at 390px. Sticky bottom action bar, remarks as a sheet, action items as stacked cards.

**Language follows the organisation locale.** All seven competitors force English on shared pages. antaraNote already has i18n and the `<details>` language switcher.

### Post-response states

| State | What the guest sees |
|---|---|
| Confirmed | Green panel, timestamp, withdraw link, signup CTA |
| Amendment sent | *"3 amendments sent to Aiman Hakim"* — can still add until deadline |
| Deadline passed while silent | *"Confirmation period closed. Minutes confirmed without objection."* |
| Round closed | Document stays readable plus the round outcome |
| Invalid token | Plain 404 — never reveal whether a token existed |

### Attendees without email

`MomAttendee.email` is nullable. Those attendees cannot be circulated to, and staying silent about it would let the secretary believe everyone was reached. They are listed explicitly before sending, and are **excluded from the tally and from silence-is-consent**. Someone who never received anything cannot be deemed to agree.

## Secretary experience

**Circulate screen** (on `meetings.show` when `Finalized`): recipient list pre-filled from attendees with email, no-email attendees listed and greyed, deadline picker defaulting to 3 working days, subject and note, and a clear warning that circulating locks edits.

**Monitoring panel:** progress bar (confirmed / amendments / unopened), countdown, per-person state, and a reminder button.

The most important column is **not** "confirmed" — it is **"never opened"**. Someone who read it and stayed quiet is a soft yes; someone who never opened it is the real liability for silence-is-consent. They sort to the top.

**Amendment queue**, grouped by anchor. *Accept & correct* opens inline editing of that topic only → save → `VersionService` creates a version, audit logs, remark resolved, and the guest who raised it is notified that the minutes were updated. That closes the loop and doubles as re-engagement.

### Minor vs material — the heaviest decision

If seven people have confirmed and the secretary then accepts an amendment, are those confirmations still valid? Strictly, no: they confirmed a different document. But forcing a new round for every typo would make people abandon the feature.

The secretary chooses explicitly when accepting:

| Choice | Effect |
|---|---|
| **Minor correction** (typo, date, spelling) | Prior confirmations stand. New version recorded. Recipients notified, no re-confirmation needed. |
| **Material amendment** (figures, decisions, scope) | All prior confirmations **voided**. Round 2 circulated automatically with a fresh deadline. |

The choice itself is logged with the secretary's name. If challenged later, the record shows a person made that judgement deliberately — rather than the system quietly deciding on their behalf. The system should not guess what counts as material; the accountable human should say so.

**Overrides:** approve early, extend deadline (logged), cancel circulation (returns to `Finalized`, unlocks edits, kills tokens).

## Deadline, reminders and auto-approve

### Scheduled jobs

**`SendCirculationReminders`** — hourly, acts at T-24h. Different copy for never-opened (*"You haven't opened these minutes"*) versus read-but-silent (*"24 hours left to respond"*). The secretary can also fire it manually.

**`CloseExpiredCirculations`** — hourly. Finds `open` circulations past `deadline_at` and evaluates the hold conditions before doing anything.

### Conditions that HOLD auto-approve

| Condition | Why |
|---|---|
| An unresolved amendment request exists | An objection is not silence |
| **Nobody opened it at all** | If 0/10 opened, silence means the mail hit spam. Auto-approving a document nobody read is indefensible. |
| Delivery failed for that recipient | Same principle as attendees without email |

All three express one principle: **nobody can be deemed to have agreed to something that never reached them.** Recipients whose delivery failed drop out of the tally entirely — neither counted as silent-consent nor blocking the round.

On hold: status becomes `awaiting_secretary`, the secretary is notified, the meeting stays `PendingConfirmation`. The system never makes that call itself.

### When all conditions pass

Inside a locked transaction: silent recipients get `deemed_confirmed_at = now()` (the moment we actually decided, not the deadline — if the scheduler stalls six hours, both facts stay separate and honest); circulation closes as `closed_approved`; the meeting becomes `Approved`; all recipients receive a closing email with the link and PDF.

### Integration obstacle

`MeetingService::approve()` **requires a `User`**, and this job runs with nobody logged in. `MeetingApproved` declares `readonly User $approvedBy`, consumed by `WebhookEventSubscriber` and `NotifyMeetingApproved`.

Passing the `sent_by` user as a stand-in is **not** acceptable. The secretary did not approve these minutes — the deadline did. Faking the actor in an audit trail to dodge a small refactor would corrupt the one thing this feature sells.

Make `approvedBy` nullable, add `?MomCirculation $circulation` to the event, and have the three listeners render null as *"Automatic confirmation · Round N"*. Contained change across three files.

## Known traps

All three have bitten this project before and all three land squarely on this feature.

1. **`OrganizationScope` kills queries for guests.** The confirmation route is unauthenticated; the tenant scope resolves to `1=0` and every query returns empty. Every query on the guest path needs `withoutGlobalScopes()`, as `GuestAccessController::show` already does.

2. **`organization_id` is not mass-assignable.** `create()` drops it silently and the auth hook substitutes the actor's org — which is NULL inside a queued job. The deadline job runs with no user, so `organization_id` must be set on the instance, taken from the meeting.

3. **A bare `timestamp()` gains `ON UPDATE CURRENT_TIMESTAMP` on production MySQL.** This is the most dangerous one here: `responded_at` and `deemed_confirmed_at` would silently rewrite themselves whenever the row is touched — corrupting precisely the audit data the feature exists to produce. SQLite in tests cannot see it. Every timestamp column must be explicitly `->nullable()`.

**Timezone.** A deadline of *"Friday 5:00 PM"* must mean 5pm Malaysian time. A deadline off by 8 hours approves minutes before people have had a chance to read them. This needs explicit testing against MySQL, not just the SQLite suite.

**Idempotency.** The job must check status inside a locked transaction. Two colliding workers must not produce two `MeetingApproved` events and two sets of email to ten people.

**Scheduler.** Confirm `schedule:run` actually runs on production (DirectAdmin box, PHP 8.4 at `/usr/local/php84/bin/php`). If it does not, the entire feature goes silent and minutes never pass. This is the first check before anything is built.

## Audit artifact, PDF and verification

`PdfExportService` appends a confirmation page when a meeting is approved via circulation: circulation date, round, deadline, recipient count, then a table of every recipient with **method of confirmation** — *explicitly confirmed* versus *no response* — and timestamp, plus amendment history and a verification QR.

That distinction is preserved rather than flattened into a single ✓. This is the artifact corporate secretaries and auditors actually need, and no competitor produces it.

**Public verification page** at `/mom/verify/{hash}` shows **integrity facts only** — MOM number, title, date, approval status, confirmation counts, version hash. It does not show meeting content. Anyone holding the PDF can verify it is genuine without the page becoming a door into the meeting's contents for strangers. A verification page that leaked content would turn every forwarded PDF into a breach.

**Print CSS.** Research found every competitor breaks when printed. `@media print` drops the banner, action bar and remark buttons, keeping the document. Cheap, and secretaries do print.

## Conversion

| Moment | Strength |
|---|---|
| **Immediately after pressing Confirm** | Strongest — they just experienced the product working |
| **When their remark is accepted** | Strong — they saw their input change the document |
| Email footer | Weak — keep it small |

Name and email are already in the recipient snapshot, so the signup form can be pre-filled. One boundary holds regardless: **no accounts are auto-created and no marketing list is joined without their action.** That email was given to the meeting organiser, not to us. Pre-filling a form they choose to submit is fine; anything beyond that is not, even though it would raise the numbers.

**Partial white-label:** organisation logo prominent, *"Powered by antaraNote"* small but present. Competitors gate this at Enterprise. The sender looks professional and we keep the loop. Full white-label removes the loop entirely — gate it behind a paid tier if ever.

### Metrics to instrument

Response rate, time-to-first-open, amendment rate, guest→signup conversion, and how often the safety valve (held circulations) fires.

**Amendment rate is the honest one.** If it sits at 0%, either the AI is perfect or nobody is really reading — and we need to know which, because the whole feature rests on review being real rather than a rubber stamp.

## Appendix — growth implications (not implementation scope)

Recorded here because these decisions constrain how the feature is built, even though they ship separately.

**Every circulation puts antaraNote in front of ~10 outsiders who must act.** Twenty meetings a month is 200 engaged interactions with no salesperson. That makes one rule structural: **do not meter reach.** If the free plan cannot circulate, no guest ever sees the product and the loop dies.

**Wedge for non-users: the action item, not the trial.** A guest who was *assigned* something has ongoing stake. The offer is *"track your 3 tasks"* — a free account doing one thing: showing my action items across every meeting I attend. Near-zero cost to us. Once tasks arrive from three different organisations, antaraNote becomes their personal accountability inbox, and the question *"why aren't my own meetings on this?"* arrives from them rather than from us. This is why live action-item status should be the next feature after this one — the two feed each other.

**Monetise the artifact, not the reach.** Circulation and unlimited recipients stay free. Paid tiers cover the *evidence*: the PDF confirmation page, verification QR, version history, multiple rounds, the never-opened chase report, and org branding. The people who need an audit trail are exactly the people with budget — government, PBT, corporate secretaries, consulting firms. They are not buying AI meeting notes; they are buying a document that survives an audit.

**Seat expansion driven by recorded data.** We already log per-recipient opens and remarks, so the admin can be shown *"3 outsiders active across 8 circulations; Hasimah sent 11 amendments — add them as members?"* Evidence, not a guess, surfacing exactly when the need is real. This is why `open_count` and per-recipient remark attribution are worth storing now.

**The tension to protect against.** The guest page is the customer's document shown to *their* client. Every CTA we add there borrows their professional credibility for our marketing. Push too hard and they will be embarrassed to send it, then demand full white-label, and the loop is gone. Growth pressure runs directly against the product's professional positioning here. Subtle-after-action wins; banner-before-action loses.
