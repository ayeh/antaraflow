# MOM Wizard Flow Design

## Overview

Redesign the Meetings page into a unified 5-step wizard that serves as the single view for creating, editing, and viewing Minutes of Meeting (MOM). Replaces the current separate create form, show page (8 tabs), and edit form with one cohesive wizard experience.

## Key Decisions

- **Approach:** Wizard-as-Show-Page — one unified view for the entire meeting lifecycle
- **Tech stack:** Alpine.js + Blade (no Livewire)
- **Navigation:** Semi-linear — Step 1 (Setup) must complete first, then free navigation to Steps 2-5
- **Project:** New full entity (model with members, settings, meetings belong to project)

## New Concepts

| Concept | Description |
|---|---|
| Project | New entity — meetings belong to a project, project has members |
| MOM Number | Auto-generated `MOM-{YYYY}-{NNNNNN}` per organization |
| Start/End Time | Replaces `duration_minutes` — auto-calculate duration |
| Language | AI content generation language preference |
| Browser Recording | Record audio from browser via MediaRecorder API |
| QR Registration | Attendee self-registration via QR code |
| Create All Tasks | Batch convert action items to tasks |
| Issues | New MOM section alongside Summary, Decisions, Action Items |
| Share with Client | Toggle for client-facing sharing |
| Prepared By | Explicit field for MOM preparer |

## Meeting Listing Page (`/meetings`)

### Layout

- Stat cards: Total, Draft, Finalized, Approved — clickable as quick filters
- Search bar: by title or MOM number (debounced with Alpine)
- Filter dropdowns: Project, Status
- Table columns: MOM No., Meeting Title, Project, Meeting Date, Status (badge), Action Items (x/y count), Actions (View/Edit/PDF)
- Pagination: Laravel paginator

## Create Meeting Form

Lean pre-wizard form. Creates meeting record, redirects to wizard.

### Fields

| Field | Type | Required |
|---|---|---|
| Meeting Title | text | Yes |
| Project | select | No |
| Meeting Date | date | Yes |
| Start Time | time | No |
| End Time | time | No |
| Location | text | No |
| Language | select | No (default: Bahasa Melayu) |
| Prepared By | text (auto-fill current user) | Yes |
| Share with Client | checkbox | No |

### Flow

1. User clicks "+ New MOM" from listing page
2. Form appears
3. Submit -> POST creates meeting with status `Draft`, MOM number auto-generated
4. Redirect to `/meetings/{id}` — wizard Step 1

## Wizard Structure

### Stepper Bar

- 5 steps: Setup, Attendees, Inputs, Review, Finalize
- Visual states: Completed (green checkmark), Current (filled circle), Future (gray)
- Semi-linear: Step 1 auto-completes on meeting creation, Steps 2-5 freely navigable after

### State Behavior

| Meeting Status | Wizard Behavior |
|---|---|
| Draft | All steps editable, full CRUD |
| Finalized | All steps read-only, "Approve" button visible at Step 5, comments still active |
| Approved | Fully locked, export only, "Revert to Draft" for admin |

## Step 1: Setup

- **Meeting info card:** Read-only summary of create form data with small "Edit" button
- **Content:** Meeting date, project, location, prepared by, language, MOM number
- Auto-complete: always done since meeting created

## Step 2: Attendees

### Stats

Total Attendees, Present, Absent, Confirmed (RSVP)

### Left Panel — Attendees List

- Filter tabs: All, Present, Absent, Guests
- Each card: name, email, role, presence status, remove button
- "Import Project Members" — bulk import from project (only shown if project assigned)

### Right Panel — Add Attendee

Three modes via toggle pills:
- **Team Members:** Checkbox list of org users, select multiple, "Add Selected Members"
- **Organization Member:** Search/select single org member
- **Guest:** Manual entry (name + email for external attendees)

Additional options:
- "Mark as Present" checkbox
- Role dropdown: Participant, Chair, Secretary, Observer
- "Set Up QR Registration" link

All add/remove actions auto-save via AJAX.

## Step 3: Inputs

### Stats

Total Inputs, Processed, Processing, Audio Files, Documents

### Left Panel — Input List

- Tab filter: Audio, Documents, Manual Notes
- Each card: file info, duration/size, processing status, view transcript, remove

### Right Panel — Add Input

| Input Type | Details |
|---|---|
| Upload Audio | Drag-drop. MP3, WAV, M4A, OGG, WebM. Max 200MB. Auto-trigger transcription |
| Browser Recording | MediaRecorder API. Waveform visualization, pause/resume, auto-save |
| Upload Document | Drag-drop. PDF, Word, Text, Images. Max 50MB. AI extracts content |
| Manual Notes | Textarea with @ mention support. Save Notes button |

### Processing Flow

1. Upload/record -> file saved -> status "Processing"
2. Background job: Audio -> transcription, Document -> text extraction
3. Status updates to "Processed" (polling or SSE)
4. On "Next: Review" -> AI generates MOM content from all processed inputs

## Step 4: Review

### Warning Banner

Shown if no content from Step 3. Link "Go to Inputs" navigates back.

### Stats

Total action items, Completed, In Progress, Overdue, Linked to Tasks

### Left Panel — Action Items List

- Each card: checkbox, description, assignee, due date, priority badge, status, edit/delete
- "Create All Tasks" — batch convert to external/internal tasks
- AI auto-populates action items from processed inputs (user can edit/delete/add)

### Right Panel — Add Action Item

- Description (required)
- Assignee: dropdown of org users + external name text field
- Due Date: date picker
- Priority: Low, Medium, High, Critical

### Comments & Feedback

- Full comment thread (reuses existing comments feature)
- Timestamp, attach image, @ mentions
- Active in all meeting states (Draft, Finalized, Approved)

All action item operations auto-save via AJAX.

## Step 5: Finalize

### Left Panel — MOM Preview

Full formatted preview:
- Summary
- Action Items (numbered list)
- Decisions (numbered list)
- Issues (numbered list)

Export buttons: PDF, DOCX, JSON

### Right Panel — Sidebars

**Meeting Info:** Title, Date, Project, MOM Number

**AI Assistant:**
- "Generate Summary" button
- "Suggest Improvements" button
- Chat input for questions about the meeting
- Usage Statistics: Conversations count, Tokens consumed

### Finalize Bar

- Bottom bar with warning: "Review the content and finalize this MOM. Once finalized, it cannot be edited."
- "Finalize MOM" button with confirmation modal
- On finalize: creates version snapshot, status -> Finalized, all steps become read-only
- Post-finalize: "Approve" button appears, "Revert to Draft" available

## Database Changes Required

### New Tables

- `projects` — id, organization_id, name, description, settings (json), is_active, timestamps
- `project_members` — id, project_id, user_id, role, timestamps
- `project_meeting` — pivot: project_id, minutes_of_meeting_id (or FK on meetings table)

### Modified Tables

- `minutes_of_meetings` — add: `mom_number`, `start_time`, `end_time`, `language`, `prepared_by`, `share_with_client`, `project_id` (FK nullable)
- Consider: `mom_extractions` — add `issues` type to extraction types

### New: MOM Number Generation

Format: `MOM-{YYYY}-{NNNNNN}`
- Sequential per organization per year
- Generated on meeting creation
- Unique constraint: `[organization_id, mom_number]`

## URL Structure

| Route | Purpose |
|---|---|
| `GET /meetings` | Listing page with stats, search, filters |
| `GET /meetings/create` | Create meeting form |
| `POST /meetings` | Store new meeting, redirect to wizard |
| `GET /meetings/{meeting}` | Wizard view (all steps, state-aware) |
| `PUT /meetings/{meeting}` | Update meeting data |
| `POST /meetings/{meeting}/finalize` | Finalize meeting |
| `POST /meetings/{meeting}/approve` | Approve meeting |
| `POST /meetings/{meeting}/revert` | Revert to draft |

Attendee, input, and action item CRUD remain as nested AJAX endpoints.
