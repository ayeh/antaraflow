# Spesifikasi API Mobile v1 — antaraFlow

**Tarikh:** 2026-08-09
**Status:** Dilaksana — API mobile lengkap dan lulus ujian (1504 ujian, 0 gagal). Aplikasi Flutter belum dibina.
**Tujuan:** Menyediakan kontrak API untuk aplikasi mobile Flutter (iOS + Android)

> Perubahan berbanding draf asal semasa pelaksanaan:
> - `/api/mobile/v1/broadcasting/auth` ditambah (guard sanctum) — laluan web memerlukan kuki sesi.
> - `POST /live/{session}/pause` dan `/resume` ditambah; `LiveSessionStatus::Paused` kini ada endpoint.
> - `GET /live/{session}/state` menerima `since_chunk` dan memulangkan `missing_chunks` + `next_chunk_number`.
> - Bug ditemui dan dibetulkan: route-model binding tidak berjalan pada laluan mobile; `live-meeting.{sessionId}`
>   dalam `routes/channels.php` membaca sifat pada null untuk penyewa lain.
> - `SubstituteBindings` didaftarkan secara eksplisit dalam `bootstrap/app.php`.

---

## 1. Skop & prinsip

### 1.1 Kenapa namespace berasingan

API sedia ada di `routes/api.php` (`/api/v1/*`) menggunakan `ApiKeyAuthentication` — bearer token **peringkat organisasi**, bukan peringkat pengguna. `ApiController` secara eksplisit menyatakan `OrganizationScope` **tidak aktif** dalam lapisan itu dan setiap query mesti di-scope manual.

Mobile app memerlukan semantik yang bertentangan: auth peringkat **pengguna**, `OrganizationScope` **aktif**, dan policy (`MinutesOfMeetingPolicy`, `ResolutionPolicy`, dll.) dikuatkuasakan. Mencampurkan dua model ini dalam prefix yang sama akan menyebabkan bug kebocoran tenant.

**Keputusan:** namespace berasingan.

| Namespace | Auth | Tenancy | Pengguna |
|---|---|---|---|
| `/api/v1/*` | `ApiKeyAuthentication` | manual `where('organization_id', ...)` | integrasi server-ke-server (kekal seperti sedia ada) |
| `/api/mobile/v1/*` | `auth:sanctum` | `OrganizationScope` aktif + policy | aplikasi Flutter |

Fail baharu: `routes/mobile.php`, didaftarkan dalam `bootstrap/app.php`.

Kelebihan: mobile boleh iterate versi tanpa memecahkan integrasi rakan kongsi; rate limit, throttle dan payload boleh ditala berasingan.

### 1.2 Prinsip reka bentuk

1. **Offline-first.** Setiap sumber utama boleh di-pull secara delta (`?since=`) dan setiap mutasi menerima `client_id` untuk idempotency.
2. **Payload gemuk untuk skrin, nipis untuk senarai.** Elak N+1 round-trip di rangkaian mudah alih. Senarai guna `*ListResource`, detail guna `*DetailResource`.
3. **Server-authoritative untuk keputusan.** Undian, kelulusan dan finalize **tidak boleh** last-write-wins. Klien hantar, server tentukan.
4. **Realtime untuk live, polling untuk selebihnya.** Reverb hanya untuk sesi live; jangan buka websocket sepanjang masa.

---

## 2. Autentikasi

### 2.1 Pemasangan Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

- Tambah `Laravel\Sanctum\HasApiTokens` pada `app/Models/User.php`.
- Tambah guard `sanctum` dalam `config/auth.php`.
- Token **tidak** stateful (bukan SPA cookie) — mobile guna bearer token tulen.

### 2.2 Interaksi dengan `OrganizationScope` — PENTING

`app/Infrastructure/Tenancy/OrganizationScope.php` bergantung pada `auth()->check()` dan `auth()->user()->current_organization_id`. Middleware `auth:sanctum` memanggil `Auth::shouldUse('sanctum')` selepas berjaya, jadi scope **akan** aktif secara automatik. Ini berbeza daripada lapisan `/api/v1` sedia ada.

Implikasi:

- Jangan tambah `where('organization_id', ...)` manual dalam controller mobile — akan jadi double-scoping.
- Pengguna berbilang organisasi perlu cara tukar konteks **tanpa** mengubah sesi web mereka.

**Middleware baharu: `ResolveMobileOrganization`**

```
1. Baca header X-Organization-Id (opsional).
2. Jika ada — sahkan user adalah ahli organisasi itu; jika bukan → 403 ORGANIZATION_FORBIDDEN.
3. Set $user->current_organization_id = $orgId secara IN-MEMORY sahaja (jangan save()).
4. Jika tiada header — guna current_organization_id sedia ada.
5. Jika current_organization_id null → 409 NO_ORGANIZATION_CONTEXT.
```

Sebab tidak `save()`: kalau disimpan, pengguna yang tukar org dalam telefon akan mendapati sesi web mereka bertukar juga tanpa amaran.

### 2.3 Endpoint auth

#### `POST /api/mobile/v1/auth/login`
Throttle: `6,1` (6 kali seminit per IP+email).

```json
// Request
{
  "email": "ariff@rocketweb.my",
  "password": "••••••••",
  "device_name": "iPhone 15 Pro",
  "device_id": "A1B2C3D4-...",        // UUID stabil per pemasangan
  "platform": "ios"                    // ios | android
}
```

```json
// 200
{
  "token": "12|xxxxxxxxxxxxxxxxxxxx",
  "expires_at": "2026-11-07T10:00:00+08:00",
  "user": {
    "id": 42,
    "name": "Ariff",
    "email": "ariff@rocketweb.my",
    "avatar_url": "https://.../avatars/42.jpg",
    "locale": "ms",
    "onboarding_completed_at": "2026-03-01T09:00:00+08:00"
  },
  "organizations": [
    { "id": 3, "name": "Antara Sdn Bhd", "role": "owner", "logo_url": "...", "is_current": true },
    { "id": 8, "name": "Rocketweb", "role": "member", "logo_url": null, "is_current": false }
  ],
  "abilities": ["meetings:read", "meetings:write", "recordings:write", "votes:cast"]
}
```

Ralat: `401 INVALID_CREDENTIALS`, `423 ACCOUNT_SUSPENDED` (guna semula logik `CheckOrganizationSuspended`).

#### `POST /api/mobile/v1/auth/social`
Untuk Google / Microsoft / MyDigital ID. Klien lakukan OAuth native, hantar token pembekal.

```json
{ "provider": "google", "access_token": "ya29...", "device_name": "...", "device_id": "...", "platform": "android" }
```

Gunakan semula `SocialAccount` dan aliran Socialite sedia ada (`auth/{provider}/callback`) melalui `Socialite::driver($provider)->userFromToken()`.

#### `POST /api/mobile/v1/auth/refresh`
Kembalikan token baharu, batalkan yang lama. Klien panggil bila `expires_at` < 7 hari.

#### `POST /api/mobile/v1/auth/logout`
Batalkan token semasa sahaja. `204`.

#### `GET /api/mobile/v1/auth/me`
Kembalikan blok `user` + `organizations` + `abilities` yang sama seperti login. Klien panggil semasa cold start untuk sahkan token masih sah.

#### `POST /api/mobile/v1/auth/organization`
```json
{ "organization_id": 8 }
```
Sahkan keahlian, kemas kini `current_organization_id` (kali ini **disimpan**, kerana ini niat eksplisit pengguna), kembalikan `me` yang dikemas kini.

#### `POST /api/mobile/v1/auth/password/forgot`
Bungkus aliran reset sedia ada. Sentiasa kembalikan `200` walau email tak wujud (elak enumerasi pengguna).

### 2.4 Peranti & biometrik

Token disimpan dalam `flutter_secure_storage`. Kunci biometrik (`local_auth`) hanya mengawal **akses ke token dalam keychain**, bukan panggilan API — jangan hantar apa-apa bukti biometrik ke server kecuali untuk approval (lihat §5.4).

---

## 3. Konvensyen

### 3.1 Header

| Header | Wajib | Nota |
|---|---|---|
| `Authorization: Bearer <token>` | ya | kecuali endpoint auth |
| `Accept: application/json` | ya | |
| `X-Organization-Id` | tidak | override konteks tenant untuk request ini |
| `X-Client-Version` | ya | cth. `ios/1.4.2 (build 210)` — untuk force-upgrade |
| `Accept-Language` | ya | `ms` atau `en`; guna semula `SetLocale` |
| `Idempotency-Key` | pada POST mutasi | UUID dijana klien |

### 3.2 Bentuk respons

Kekalkan konvensyen `MeetingApiController` sedia ada — `data` + `meta`, tanpa pembungkus tambahan.

```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "last_page": 4, "total": 78, "per_page": 20 }
}
```

Sumber tunggal: objek terus tanpa `data` (ikut `show()` sedia ada), **kecuali** bila ada `meta`.

### 3.3 Ralat

```json
{
  "message": "Mesyuarat ini telah diluluskan dan tidak boleh dikemas kini.",
  "code": "MEETING_APPROVED_IMMUTABLE",
  "errors": { "title": ["Medan tajuk diperlukan."] }
}
```

`message` sentiasa boleh dipapar terus kepada pengguna (sudah melalui `__()`). `code` untuk logik klien. `errors` hanya pada `422`.

| Kod | HTTP | Makna |
|---|---|---|
| `UNAUTHENTICATED` | 401 | token tiada/tamat |
| `INVALID_CREDENTIALS` | 401 | login gagal |
| `FORBIDDEN` | 403 | policy tolak |
| `ORGANIZATION_FORBIDDEN` | 403 | bukan ahli org |
| `NO_ORGANIZATION_CONTEXT` | 409 | user tiada current org |
| `NOT_FOUND` | 404 | |
| `VALIDATION_FAILED` | 422 | |
| `MEETING_APPROVED_IMMUTABLE` | 422 | ikut logik `MeetingApiController::update` |
| `SESSION_NOT_ACTIVE` | 409 | ikut `abort_if(! $session->isActive(), 409)` |
| `SESSION_ALREADY_ACTIVE` | 409 | ikut `LiveMeetingController::start` |
| `CHUNK_DUPLICATE` | 200 | bukan ralat — chunk sudah diterima, klien buang dari queue |
| `QUOTA_EXCEEDED` | 402 | had pelan dilanggar |
| `AI_DISABLED` | 402 | kill-switch AI aktif |
| `RATE_LIMITED` | 429 | + header `Retry-After` |
| `CLIENT_UPGRADE_REQUIRED` | 426 | versi klien terlalu lama |

### 3.4 Pagination

Cursor-based untuk senarai yang panjang dan kerap berubah (transcript chunk, notifikasi); page-based untuk selebihnya (kekalkan gaya sedia ada).

```
GET /meetings?page=2&per_page=20
GET /notifications?cursor=eyJpZCI6MTIzfQ&limit=30
```

### 3.5 Rate limit

| Kumpulan | Had |
|---|---|
| `auth/*` | 6/min per IP |
| chunk upload | 120/min per pengguna (chunk 15s = 4/min biasa; longgar untuk retry) |
| tulisan am | 60/min |
| bacaan am | 180/min |
| AI (`chat`, `extract`, `search/ai`) | 20/min |

### 3.6 Idempotency

Untuk semua `POST` yang mencipta sumber, klien hantar `Idempotency-Key`. Server simpan `key → response` dalam cache 24 jam. Ulangan mengembalikan respons asal dengan `Idempotency-Replayed: true`.

Ini **wajib** — tanpa ia, retry outbox offline akan mencipta action item pendua.

### 3.7 Force upgrade

Setiap respons membawa header `X-Min-Client-Version`. Bila `X-Client-Version` klien lebih rendah → `426 CLIENT_UPGRADE_REQUIRED` dengan `message` dan `store_url`. Nilai minimum diambil dari `PlatformSetting`.

---

## 4. Katalog endpoint — Fasa 0 (MVP)

### 4.1 Bootstrap

#### `GET /api/mobile/v1/bootstrap`
Satu panggilan semasa cold start. Menggantikan 6 panggilan berasingan.

```json
{
  "user": { ... },
  "organization": {
    "id": 3, "name": "Antara Sdn Bhd", "logo_url": "...",
    "brand_color": "#0F62FE", "locale_default": "ms"
  },
  "subscription": {
    "plan": "business",
    "features": { "transcription": true, "ai_summaries": true, "export": true, "custom_templates": true, "api_access": true },
    "limits": { "max_meetings_per_month": -1, "max_audio_minutes_per_month": 1000, "max_storage_mb": 25000 },
    "usage": { "meetings_this_month": 12, "audio_minutes_this_month": 340, "storage_mb": 1820 }
  },
  "capabilities": {
    "live_extraction": true,
    "ai_enabled": true,
    "voting": true,
    "annotations": false
  },
  "unread": { "notifications": 4, "action_items_due": 3, "pending_approvals": 1 },
  "server_time": "2026-08-09T14:03:11+08:00",
  "sync_cursor": "eyJ0cyI6MTc1..."
}
```

`capabilities` membolehkan feature flag dari server — penting supaya app tak perlu dihantar semula ke store untuk matikan feature.

`usage` diambil dari `UsageTracking`; `features`/`limits` dari `SubscriptionPlan`.

### 4.2 Mesyuarat

#### `GET /api/mobile/v1/meetings`
Query: `status`, `type`, `from`, `to`, `q`, `tag_id`, `project_id`, `assigned_to_me`, `page`, `per_page`, `since`.

```json
{
  "data": [{
    "id": 128,
    "mom_number": "MOM/2026/0128",
    "title": "Mesyuarat Lembaga Pengarah Q3",
    "meeting_type": "board_meeting",
    "status": "finalized",
    "meeting_date": "2026-08-12T10:00:00+08:00",
    "location": "Bilik Gerakan, Tingkat 12",
    "duration_minutes": 90,
    "attendee_count": 9,
    "action_item_counts": { "open": 4, "in_progress": 2, "completed": 7 },
    "has_transcription": true,
    "has_live_session": false,
    "tags": [{ "id": 5, "name": "Lembaga", "color": "#0F62FE" }],
    "updated_at": "2026-08-09T09:12:00+08:00"
  }],
  "meta": { "current_page": 1, "last_page": 4, "total": 78, "per_page": 20 }
}
```

`status` mengikut `MeetingStatus`: `draft`, `in_progress`, `finalized`, `pending_confirmation`, `approved`.
`meeting_type` mengikut `MeetingType`: `general`, `standup`, `retrospective`, `client_call`, `board_meeting`, `one_on_one`, `workshop`.

#### `GET /api/mobile/v1/meetings/{id}`
Detail penuh — satu panggilan memuatkan seluruh skrin mesyuarat.

```json
{
  "id": 128,
  "mom_number": "MOM/2026/0128",
  "title": "...",
  "summary": "...",
  "content": "<html>...",
  "status": "finalized",
  "meeting_type": "board_meeting",
  "meeting_date": "2026-08-12T10:00:00+08:00",
  "location": "...",
  "meeting_link": "https://meet.google.com/abc",
  "meeting_platform": "google_meet",
  "duration_minutes": 90,
  "created_by": { "id": 42, "name": "Ariff" },
  "series": { "id": 4, "name": "Mesyuarat Lembaga Suku Tahunan" },
  "attendees": [{
    "id": 901, "user_id": 42, "name": "Ariff", "email": "...",
    "role": "organizer", "rsvp_status": "accepted",
    "is_present": true, "is_external": false, "department": "Teknologi"
  }],
  "action_items": [ ...ActionItemResource... ],
  "extractions": [{ "id": 55, "type": "summary", "content": {...}, "created_at": "..." }],
  "resolutions": [ ...lihat 5.3... ],
  "documents": [{ "id": 12, "name": "Bajet Q3.pdf", "mime_type": "application/pdf", "size": 2400123, "download_url": "..." }],
  "transcriptions": [{ "id": 77, "status": "completed", "mode": "live", "duration_seconds": 5400 }],
  "voice_notes": [ ... ],
  "tags": [ ... ],
  "permissions": {
    "can_update": true, "can_finalize": true, "can_approve": false,
    "can_start_live": true, "can_delete": false, "can_comment": true
  },
  "updated_at": "2026-08-09T09:12:00+08:00"
}
```

Blok `permissions` adalah **wajib** — klien mesti tidak meneka policy. Ia dijana daripada `MinutesOfMeetingPolicy` (`view`, `update`, `finalize`, `approve`, `startLive`, `delete`).

#### `POST /api/mobile/v1/meetings`
Penciptaan pantas sahaja (bukan wizard 5-langkah).

```json
{
  "title": "Mesyuarat pagi",
  "meeting_type": "standup",
  "meeting_date": "2026-08-09T09:00:00+08:00",
  "location": null,
  "attendee_ids": [42, 51],
  "template_id": null,
  "client_id": "9f1c-..."
}
```
→ `201` dengan detail mesyuarat. `status` sentiasa `draft`.

#### `PATCH /api/mobile/v1/meetings/{id}`
Medan: `title`, `summary`, `content`, `location`, `meeting_date`, `duration_minutes`, `meeting_type`.
Tolak `422 MEETING_APPROVED_IMMUTABLE` jika `status === approved` (ikut logik sedia ada).

#### `POST /api/mobile/v1/meetings/{id}/finalize` · `POST .../approve`
Kosong body. Kuatkuasa `MinutesOfMeetingPolicy::finalize` / `::approve`.

#### `GET /api/mobile/v1/meetings/calendar`
Query: `from`, `to`. Kembalikan payload ringan untuk paparan kalendar (id, title, meeting_date, duration, status, type sahaja).

### 4.3 Sesi live — jantung app

Bina di atas `LiveMeetingService` sedia ada. Perbezaan penting daripada web: mobile perlu **resume**, kerana app boleh dibunuh oleh OS.

#### `POST /api/mobile/v1/meetings/{id}/live/start`
```json
{
  "chunk_interval": 15,          // saat, 10–120
  "extraction_interval": 180,    // saat, 60–600
  "live_extraction": true,
  "client_id": "..."
}
```

```json
// 201
{
  "session": {
    "id": 314,
    "status": "active",
    "started_at": "2026-08-09T10:00:00+08:00",
    "config": { "chunk_interval": 15, "extraction_interval": 180, "live_extraction": true },
    "next_chunk_number": 0
  },
  "broadcast": {
    "channel": "private-live-meeting.314",
    "events": ["TranscriptionChunkProcessed", "LiveExtractionUpdated", "LiveSessionEnded", "LiveTranscriptIncomplete"]
  },
  "upload": { "max_chunk_bytes": 52428800, "accepted_mimetypes": ["audio/mp4","audio/m4a","audio/wav","audio/ogg"] }
}
```

Jika sudah ada sesi aktif → `409 SESSION_ALREADY_ACTIVE` dengan objek `session` sedia ada supaya klien boleh **sambung semula**, bukan gagal. Ini sudah jadi tingkah laku `LiveMeetingController::start`; kekalkannya.

> **Nota mimetype:** senarai sedia ada dalam `LiveMeetingController::chunk` berorientasi browser (`audio/webm`, `video/webm`). Flutter merakam `m4a`/`aac` di kedua-dua platform. Senarai validasi **mesti** diperluas kepada `audio/mp4`, `audio/m4a`, `audio/aac` sebelum mobile boleh upload.

#### `POST /api/mobile/v1/live/{session}/chunks`
`multipart/form-data`.

| Medan | Jenis | Nota |
|---|---|---|
| `audio` | file | ≤50MB |
| `chunk_number` | int | ≥0, monotonik |
| `start_time` | float | saat dari mula sesi |
| `end_time` | float | > `start_time` |
| `checksum` | string | SHA-256 fail, opsional tapi disyorkan |

```json
// 201
{ "chunk": { "id": 8801, "chunk_number": 12, "status": "pending" }, "next_chunk_number": 13 }
```

Jika `chunk_number` sudah wujud untuk sesi itu → `200` dengan `{"code": "CHUNK_DUPLICATE", "chunk": {...}}`. Klien buang dari outbox tanpa anggap ralat. Ini kritikal: rangkaian mudah alih akan menyebabkan retry pada chunk yang sebenarnya sudah sampai.

#### `GET /api/mobile/v1/live/{session}/state`
Untuk **resume selepas app dibunuh** dan sebagai fallback bila websocket putus.

```json
{
  "session": { "id": 314, "status": "active", "started_at": "...", "total_duration_seconds": 1830, "config": {...} },
  "next_chunk_number": 122,
  "missing_chunks": [117, 119],
  "chunks": [{ "chunk_number": 120, "text": "...", "speaker": "Speaker 1", "start_time": 1785.0, "end_time": 1800.0, "confidence": 0.94, "status": "completed" }],
  "extractions": [{ "id": 55, "type": "action_items", "content": {...}, "updated_at": "..." }],
  "stats": { "chunks_total": 122, "chunks_completed": 118, "chunks_failed": 2, "chunks_pending": 2 }
}
```

Query `?since_chunk=100` untuk hanya ambil chunk baharu — jangan hantar 500 chunk setiap kali polling.

`missing_chunks` membolehkan klien menghantar semula secara terpilih. Ini menutup punca utama transcript tidak lengkap (`LiveTranscriptIncomplete`).

#### `POST /api/mobile/v1/live/{session}/pause` · `/resume`
Status `paused` sudah wujud dalam `LiveSessionStatus` tetapi tiada endpoint. Perlu ditambah — mobile memerlukannya untuk panggilan masuk dan rehat.

#### `POST /api/mobile/v1/live/{session}/extraction`
```json
{ "enabled": true }
```

#### `POST /api/mobile/v1/live/{session}/end`
```json
{ "final_chunk_number": 122 }
```
Server tunggu chunk tertunggak (had masa), gabungkan, kembalikan `{ "transcription_id": 77, "chunks_dropped": 0 }`.

#### `POST /api/mobile/v1/live/{session}/bookmarks`
Baharu — tiada dalam web. "Tandai penting" semasa merakam.
```json
{ "at_seconds": 1432.5, "label": "Keputusan bajet", "kind": "decision", "client_id": "..." }
```
`kind`: `decision` | `action` | `question` | `general`. Simpan dalam `live_meeting_sessions.config` atau jadual baharu `live_bookmarks`. **Ini feature mobile-first yang paling murah untuk nilai yang tinggi.**

### 4.4 Action items

#### `GET /api/mobile/v1/action-items`
Query: `assigned_to_me` (default `true` di mobile), `status`, `priority`, `due_before`, `due_after`, `overdue`, `meeting_id`, `q`, `since`.

Guna semula `ActionItemResource` tetapi kembangkan `assigned_to` dan `minutes_of_meeting` kepada objek (bukan ID mentah) supaya senarai boleh dipapar tanpa panggilan tambahan:

```json
{
  "id": 4501,
  "title": "Sediakan unjuran tunai Q4",
  "description": "...",
  "priority": "high",
  "status": "in_progress",
  "due_date": "2026-08-15",
  "is_overdue": false,
  "completed_at": null,
  "assigned_to": { "id": 42, "name": "Ariff", "avatar_url": "..." },
  "meeting": { "id": 128, "title": "Mesyuarat Lembaga Q3", "mom_number": "MOM/2026/0128" },
  "carried_from_id": null,
  "permissions": { "can_update": true, "can_delete": false },
  "updated_at": "..."
}
```

#### `POST /api/mobile/v1/action-items`
#### `PATCH /api/mobile/v1/action-items/{id}`
#### `PATCH /api/mobile/v1/action-items/{id}/status`
Endpoint sempit untuk swipe-to-complete — hantar `{ "status": "completed", "client_id": "..." }` sahaja. Lebih murah dan lebih selamat daripada PATCH penuh dari peranti offline.

#### `POST /api/mobile/v1/action-items/bulk`
```json
{ "ids": [4501, 4502], "action": "complete", "client_id": "..." }
```
`action`: `complete` | `cancel` | `carry_forward` | `reassign` (+ `assigned_to`).

### 4.5 Kehadiran & QR

#### `GET /api/mobile/v1/meetings/{id}/attendees`
#### `PATCH /api/mobile/v1/attendees/{id}/rsvp` — `{ "rsvp_status": "accepted" }`
#### `PATCH /api/mobile/v1/attendees/{id}/presence` — `{ "is_present": true }`

#### `POST /api/mobile/v1/attendance/scan`
Peserta imbas QR yang dipapar di pintu masuk.
```json
{ "token": "a1b2c3...", "name": "Ariff", "email": "ariff@rocketweb.my", "phone": "+6012...", "company": "Rocketweb", "position": "CTO" }
```
Sahkan `qr_registration_tokens.is_active` dan `expires_at`. → `201` dengan `{ "meeting": {...}, "attendee": {...} }`.
Ralat: `410 QR_TOKEN_EXPIRED`, `404 QR_TOKEN_INVALID`.

#### `GET /api/mobile/v1/meetings/{id}/qr-registration`
Untuk urus setia — kembalikan `token`, `qr_payload` (URL untuk dijadikan QR di skrin telefon), `expires_at`, dan senarai pendaftar terkini. Telefon urus setia jadi kaunter pendaftaran mudah alih.

#### `POST /api/mobile/v1/meetings/{id}/qr-registration` · `DELETE` (disable)

### 4.6 Notifikasi & push

#### `POST /api/mobile/v1/devices`
```json
{ "device_id": "A1B2...", "push_token": "fcm_or_apns_token", "platform": "ios", "app_version": "1.4.2", "locale": "ms" }
```
Jadual baharu `user_devices`: `user_id`, `device_id` (unique), `push_token`, `platform`, `app_version`, `locale`, `last_seen_at`, `revoked_at`.

#### `DELETE /api/mobile/v1/devices/{device_id}` — semasa logout.

#### `GET /api/mobile/v1/notifications`
Cursor-based. Guna semula `NotificationResource`, tambah `deep_link`:

```json
{
  "id": "9f8e-...",
  "type": "action_item.assigned",
  "title": "Action item baharu",
  "body": "Ariff menugaskan anda: Sediakan unjuran tunai Q4",
  "deep_link": "antaraflow://action-items/4501",
  "read_at": null,
  "created_at": "..."
}
```

#### `GET /api/mobile/v1/notifications/unread-count`
#### `PATCH /api/mobile/v1/notifications/{id}/read`
#### `POST /api/mobile/v1/notifications/read-all`

**Kerja backend:** semua kelas Notification kini `via() => ['database','mail']`. Tambah channel `fcm`. Cadangan: cipta trait `MobilePushable` yang menambah `'fcm'` secara bersyarat bila pengguna mempunyai peranti berdaftar dan tetapan notifikasi membenarkan.

Payload push mesti membawa `deep_link` dan `notification_id` dalam `data` supaya tap membuka skrin yang betul dan menanda telah dibaca.

### 4.7 Carian

#### `GET /api/mobile/v1/search?q=...&types=meetings,action_items,transcripts&limit=20`
Hasil bercampur dengan `type`, `id`, `title`, `snippet` (highlighted), `meeting_date`, `deep_link`.

#### `POST /api/mobile/v1/search/ai`
```json
{ "query": "apa keputusan pasal bajet Q3?", "scope": "organization", "meeting_ids": null }
```
Kembalikan `answer` + `citations[]` (`meeting_id`, `title`, `excerpt`, `deep_link`). Tertakluk `AI_DISABLED` dan kuota AI.

---

## 5. Katalog endpoint — Fasa 1

### 5.1 Offline pack & sync

Yang sedia ada (`OfflineDataController`) hanya menyokong `note` dan `comment` dan tiada mekanisme delta. Perlu diperluas.

#### `GET /api/mobile/v1/sync/pull?since={cursor}&limit=500`

```json
{
  "changes": {
    "meetings":      { "upserted": [...], "deleted": [12, 15] },
    "action_items":  { "upserted": [...], "deleted": [] },
    "attendees":     { "upserted": [...], "deleted": [] },
    "comments":      { "upserted": [...], "deleted": [] },
    "extractions":   { "upserted": [...], "deleted": [] },
    "resolutions":   { "upserted": [...], "deleted": [] },
    "notifications": { "upserted": [...], "deleted": [] }
  },
  "cursor": "eyJ0cyI6MTc1...",
  "has_more": false,
  "full_resync_required": false
}
```

Cursor = `(updated_at, id)` bersiri dan base64. `full_resync_required: true` bila cursor lebih lama daripada tempoh simpanan tombstone (cadangan 30 hari) — klien kosongkan DB tempatan dan pull dari awal.

**Tombstone:** model utama guna `SoftDeletes`, jadi rekod terpadam boleh dikesan melalui `deleted_at`. Untuk yang tiada soft delete (cth. `resolution_votes`), perlu jadual `sync_tombstones` (`model_type`, `model_id`, `organization_id`, `deleted_at`).

#### `POST /api/mobile/v1/sync/push`

```json
{
  "operations": [
    { "client_id": "a1", "entity": "action_item", "op": "update", "id": 4501, "base_updated_at": "2026-08-09T09:00:00+08:00", "payload": { "status": "completed" } },
    { "client_id": "a2", "entity": "manual_note", "op": "create", "meeting_id": 128, "payload": { "title": "...", "content": "..." } },
    { "client_id": "a3", "entity": "comment", "op": "create", "meeting_id": 128, "payload": { "body": "..." } }
  ]
}
```

```json
{
  "results": [
    { "client_id": "a1", "status": "applied",  "id": 4501, "server_updated_at": "..." },
    { "client_id": "a2", "status": "applied",  "id": 9902 },
    { "client_id": "a3", "status": "conflict", "reason": "STALE_WRITE", "server_state": { ... } },
    { "client_id": "a4", "status": "rejected", "reason": "FORBIDDEN", "message": "..." }
  ],
  "cursor": "eyJ0cyI6MTc1..."
}
```

Peraturan konflik:

| Entiti | Strategi |
|---|---|
| `manual_note` (peribadi) | last-write-wins |
| `comment` | append sahaja — tiada konflik |
| `action_item` status | LWW dengan `base_updated_at`; jika basi → `conflict`, klien papar pilihan |
| `meeting.content` | **jangan** benarkan edit offline; hanya baca |
| `resolution_vote` | **server-authoritative** — undian offline diqueue, server tolak jika resolusi sudah ditutup |
| `meeting.approve/finalize` | **tiada offline** — memerlukan sambungan |

Setiap operasi diproses dalam transaksi berasingan. Satu operasi gagal tidak boleh menggagalkan batch.

`op` mesti idempotent berdasarkan `client_id` — server menyimpan `client_id` yang telah diproses selama 7 hari.

#### `GET /api/mobile/v1/meetings/{id}/pack`
Muat turun pek mesyuarat penuh untuk offline: detail + semua dokumen (dengan URL bertandatangan berjangka masa) + transcript penuh. Untuk pengarah lembaga yang mahu baca dalam kapal terbang.

```json
{
  "meeting": { ...detail penuh... },
  "documents": [{ "id": 12, "name": "...", "size": 2400123, "sha256": "...", "download_url": "https://...?signature=...", "url_expires_at": "..." }],
  "transcript": { "id": 77, "segments": [...] },
  "pack_version": 3,
  "generated_at": "..."
}
```

`pack_version` naik setiap kali kandungan berubah — klien tahu bila perlu muat turun semula.

### 5.2 Transcript

#### `GET /api/mobile/v1/meetings/{id}/transcriptions`
#### `GET /api/mobile/v1/transcriptions/{id}?include=segments&cursor=...`
Segmen bercursor — transcript 2 jam boleh mencecah ribuan segmen.
```json
{ "id": 901, "speaker": "Dato' Rahim", "text": "...", "start_time": 120.5, "end_time": 134.2, "confidence": 0.93 }
```
#### `PATCH /api/mobile/v1/transcriptions/{id}/speakers` — namakan semula speaker (`{"from":"Speaker 1","to":"Dato' Rahim"}`).
#### `GET /api/mobile/v1/transcriptions/{id}/speaker-suggestions`

### 5.3 Resolusi & undian

#### `GET /api/mobile/v1/meetings/{id}/resolutions`
```json
{
  "id": 77,
  "resolution_number": "R2026/014",
  "title": "Kelulusan bajet modal Q4",
  "description": "...",
  "status": "proposed",
  "mover": { "id": 901, "name": "Dato' Rahim" },
  "seconder": { "id": 903, "name": "Puan Siti" },
  "tally": { "for": 5, "against": 1, "abstain": 2, "not_voted": 1, "total_eligible": 9 },
  "my_vote": null,
  "voting_open": true,
  "permissions": { "can_vote": true, "can_update": false }
}
```

#### `POST /api/mobile/v1/resolutions/{id}/vote`
```json
{ "vote": "for", "client_id": "..." }
```
`vote`: `for` | `against` | `abstain` (mengikut `VoteChoice`).

Peraturan:
- Kuatkuasa `ResolutionPolicy::vote`.
- `resolution_votes` mempunyai unique `(resolution_id, attendee_id)` — gunakan `updateOrCreate` supaya menukar undian dibenarkan selagi `status === proposed`.
- Jika `status !== proposed` → `409 VOTING_CLOSED`.
- **Jangan cache tally di klien lebih lama daripada 5 saat semasa mesyuarat aktif.**

#### `POST /api/mobile/v1/resolutions` · `PATCH /api/mobile/v1/resolutions/{id}`

### 5.4 Peredaran & kelulusan MoM

#### `GET /api/mobile/v1/circulations/pending`
Semua MoM yang menunggu tindakan pengguna ini. Ini yang mengisi lencana "Pending approvals" di home.

#### `POST /api/mobile/v1/circulations/{id}/respond`
```json
{
  "decision": "approve",              // approve | request_amendment | acknowledge
  "remark": "Setuju dengan pindaan minor pada perenggan 4.2",
  "amendments": [ { "section": "4.2", "proposed_text": "..." } ],
  "attestation": {
    "method": "biometric",            // biometric | password | none
    "device_id": "A1B2...",
    "at": "2026-08-09T14:03:11+08:00"
  },
  "client_id": "..."
}
```

**Nota keselamatan:** `attestation.method: "biometric"` adalah **dakwaan klien**, bukan bukti kriptografi. Ia boleh direkod dalam `AuditLog` sebagai konteks tetapi **tidak boleh** dianggap tandatangan sah dari segi undang-undang. Untuk e-signature sebenar, perlukan kunci peranti + tandatangan pada digest dokumen — skop Fasa 2.

Rekod dalam `AuditLog`: `user_id`, `ip`, `device_id`, `app_version`, `method`.

### 5.5 Nota suara & dokumen

#### `POST /api/mobile/v1/meetings/{id}/voice-notes` (multipart: `audio`, `duration_seconds`, `client_id`) → transkripsi automatik melalui `TranscribeVoiceNoteJob`.
#### `GET /api/mobile/v1/meetings/{id}/voice-notes` · `DELETE /api/mobile/v1/voice-notes/{id}`
#### `POST /api/mobile/v1/meetings/{id}/documents` (multipart, ≤25MB)
#### `GET /api/mobile/v1/documents/{id}/download` → `302` ke URL bertandatangan.

### 5.6 Komen & kolaborasi

#### `GET /api/mobile/v1/meetings/{id}/comments`
#### `POST /api/mobile/v1/meetings/{id}/comments` — `{ "body": "...", "parent_id": null, "mentions": [42], "client_id": "..." }`
#### `PATCH` / `DELETE /api/mobile/v1/comments/{id}`
#### `POST /api/mobile/v1/comments/{id}/reactions` — `{ "emoji": "👍" }`

### 5.7 AI

#### `POST /api/mobile/v1/meetings/{id}/chat`
```json
{ "message": "Apa action item untuk Kewangan?", "conversation_id": null }
```
Kembalikan `conversation_id`, `answer`, `citations[]`. Sokong SSE untuk streaming (`Accept: text/event-stream`) — jika tidak, respons penuh.

#### `POST /api/mobile/v1/meetings/{id}/extract`
`{ "types": ["summary","action_items","decisions"] }` → `202` dengan `job_ids`. Klien dengar melalui push atau poll `GET /meetings/{id}/extractions`.

#### `GET /api/mobile/v1/meetings/{id}/prep-brief`
#### `POST /api/mobile/v1/meetings/{id}/prep-brief/generate`
#### `GET /api/mobile/v1/insights` · `POST /api/mobile/v1/insights/{id}/read` · `/dismiss`

### 5.8 Eksport

#### `POST /api/mobile/v1/meetings/{id}/exports` — `{ "format": "pdf", "template_id": null }` → `202` `{ "export_id": 55 }`
#### `GET /api/mobile/v1/exports/{id}` → `{ "status": "completed", "download_url": "...", "expires_at": "..." }`

Mobile **tidak** menjana PDF; ia hanya meminta dan berkongsi melalui share sheet OS.

### 5.9 Tetapan

#### `GET|PATCH /api/mobile/v1/settings/notifications` — kawalan per-jenis, per-channel (`push`, `email`).
#### `PATCH /api/mobile/v1/settings/profile` · `POST /api/mobile/v1/settings/profile/avatar`
#### `GET /api/mobile/v1/settings/preferences` — `locale`, `timezone`, `default_meeting_type`.

---

## 6. Realtime (Reverb)

### 6.1 Auth broadcasting

Endpoint `/broadcasting/auth` mesti menerima bearer token Sanctum. Dalam `bootstrap/app.php`:

```php
Broadcast::routes(['middleware' => ['auth:sanctum']]);
```

Atau daftarkan laluan kedua `/api/mobile/v1/broadcasting/auth` supaya laluan web sedia ada tidak terjejas.

### 6.2 Saluran

| Saluran | Bila melanggan | Event |
|---|---|---|
| `private-live-meeting.{sessionId}` | hanya semasa sesi live aktif | `TranscriptionChunkProcessed`, `LiveExtractionUpdated`, `LiveSessionEnded`, `LiveTranscriptIncomplete` |
| `private-App.Models.User.{id}` | semasa app di foreground | notifikasi masa nyata |
| `presence-meeting.{meetingId}.presence` | semasa skrin mesyuarat dibuka | siapa sedang melihat |
| `private-meeting.{meetingId}` | semasa skrin mesyuarat dibuka | komen/action item baharu |

**Peraturan klien:** putuskan sambungan websocket bila app masuk background lebih 30 saat (kecuali sesi live sedang berjalan). Sambung semula → segera panggil `GET /live/{session}/state?since_chunk=` untuk mengisi jurang. **Jangan sekali-kali bergantung pada websocket sahaja untuk ketepatan transcript.**

### 6.3 Konfigurasi

`bootstrap` mengembalikan konfigurasi Reverb supaya app tidak perlu hard-code:
```json
"realtime": { "driver": "reverb", "key": "...", "host": "ws.antara.cloud", "port": 443, "scheme": "https" }
```

---

## 7. Muat naik audio — protokol

Ini bahagian paling kritikal. Kegagalan di sini bermakna rakaman mesyuarat hilang.

```
1. Perakam Flutter tulis segmen 15 saat ke fail tempatan (m4a/AAC, 32kbps mono, 16kHz).
2. Setiap segmen dimasukkan ke jadual `upload_queue` dalam Drift:
   (session_id, chunk_number, file_path, start_time, end_time, sha256, state)
   state: pending → uploading → uploaded → confirmed
3. Pekerja muat naik memproses queue secara bersiri (bukan selari — jaga jujukan & bateri).
4. Kejayaan (201 atau CHUNK_DUPLICATE) → tandakan confirmed, PADAM fail tempatan.
5. Kegagalan → backoff eksponen (2s, 4s, 8s … maks 60s), maks 20 percubaan.
6. Kekal atas WiFi/selular; jika queue > 20 chunk tertunggak, papar amaran kepada pengguna.
7. Sebelum `end`, klien mesti mengosongkan queue. Jika masih ada tertunggak,
   papar "Menyiapkan muat naik…" dan JANGAN benarkan pengguna keluar tanpa amaran.
8. Selepas `end`, panggil `state` dan bandingkan `missing_chunks` — hantar semula jika perlu.
```

**Jangan** padam fail audio tempatan sebelum server mengesahkan. Sebab: kegagalan senyap adalah aduan #1 dalam review app kategori ini.

Anggaran saiz: AAC 32kbps mono = ~4KB/saat = ~60KB per chunk 15s = ~14MB sejam. Selamat untuk data mudah alih.

---

## 8. Kuota & penguatkuasaan pelan

Semak sebelum operasi mahal, bukan selepas:

| Operasi | Semakan |
|---|---|
| `live/start` | `max_meetings_per_month`, `max_audio_minutes_per_month`, ciri `transcription` |
| chunk upload | baki minit audio; jika habis → `402 QUOTA_EXCEEDED`, sesi ditamatkan dengan anggun (audio yang sudah dimuat naik kekal) |
| `extract`, `chat`, `search/ai` | ciri `ai_summaries` + kill-switch AI + bajet organisasi (`OrganizationAiBudget`) |
| `exports` | ciri `export` |
| muat naik dokumen | `max_storage_mb` |

Sertakan baki dalam respons supaya UI boleh memberi amaran awal:
```
X-Quota-Audio-Minutes-Remaining: 47
X-Quota-Meetings-Remaining: 3
```

---

## 9. Deep link

Skema: `antaraflow://` + universal link `https://note.antara.cloud/app/*`.

| Corak | Skrin |
|---|---|
| `antaraflow://meetings/{id}` | detail mesyuarat |
| `antaraflow://meetings/{id}/live/{sessionId}` | dashboard live |
| `antaraflow://action-items/{id}` | detail action item |
| `antaraflow://circulations/{id}` | skrin kelulusan |
| `antaraflow://resolutions/{id}` | skrin undian |
| `antaraflow://scan` | pengimbas QR |

---

## 10. Senarai semak pelaksanaan backend

### Prasyarat — SIAP

- [x] `laravel/sanctum` dipasang; `HasApiTokens` pada `User`; guard `sanctum` dalam `config/auth.php`
- [x] `ResolveMobileOrganization` (header `X-Organization-Id`, override dalam memori sahaja)
- [x] `routes/mobile.php` + `routes/mobile/data.php`, didaftar dalam `bootstrap/app.php`
- [x] **`SubstituteBindings` didaftarkan secara eksplisit** — tanpa ia setiap `{meeting}` tiba sebagai model kosong
- [x] `MobileExceptionRenderer` — envelop `{message, code, errors}` seragam
- [x] `EnsureClientVersion` (`X-Client-Version` / `426` / `X-Min-Client-Version`)
- [x] `MobileIdempotency` (kunci dicap sebelum permintaan berjalan; dilepaskan bila gagal)
- [x] Ujian: `OrganizationScope` aktif dan betul di bawah guard `sanctum`

### Struktur baharu — SIAP

- [x] `app/Domain/API/Controllers/Mobile/V1/*` (17 pengawal)
- [x] `app/Domain/API/Resources/Mobile/*` (senarai vs detail berasingan)
- [x] Migrasi: `user_devices`, `sync_tombstones`, `live_bookmarks`, `personal_access_tokens`
- [x] Channel `push` + `FcmHttpV1Sender` (JWT ditandatangan dengan openssl — tiada kebergantungan baharu)
- [x] `MobileSyncService` — kursor, tombstone, resolusi konflik per-entiti

### Pindaan pada kod sedia ada — SIAP

- [x] Mimetype chunk diperluas melalui `AudioChunkFormats` (m4a/AAC diterima)
- [x] `pauseSession()` / `resumeSession()` didedahkan melalui endpoint
- [x] `getSessionState($session, $sinceChunk)` + `getResumeState()`
- [x] Chunk pendua dikesan → `CHUNK_DUPLICATE` (200), bukan rekod kedua
- [x] `/api/mobile/v1/broadcasting/auth` menerima token bearer
- [x] `CheckOrganizationSuspended` memulangkan JSON untuk permintaan API
- [x] `routes/channels.php` — pembacaan sifat pada null dibetulkan

### Ujian — SIAP (124 ujian mobile baharu)

- [x] Auth, peranti, tukar organisasi, lupa kata laluan
- [x] Kebocoran penyewa merentas setiap kata kerja HTTP
- [x] Sesi live: m4a, pendua, jurang, jeda/sambung, penanda
- [x] Undian: tukar undi, undian ditutup, bukan peserta, penyewa lain
- [x] Sync: delta, tombstone, resync penuh, konflik basi, kegagalan separa
- [x] Idempotency, gerbang versi klien, imbasan QR, push
- [x] Asap: 27 endpoint bacaan × (berjaya, tanpa auth)

### Belum siap

- [ ] Kuota per-operasi (`X-Quota-*`, `402 QUOTA_EXCEEDED`) — belum dikuatkuasakan
- [ ] Anotasi PDF (`document_annotations`) — Fasa 2, memerlukan keputusan storan (§12)
- [ ] Undian berwajaran — `resolution_votes` masih tiada medan `weight`
- [ ] Streaming SSE untuk chat AI — kini respons penuh sahaja
- [ ] Aplikasi Flutter itu sendiri

## 11. Perkara yang **tiada** dalam API mobile

Sengaja ditinggalkan. Mobile membuka web view untuk yang berikut:

- Wizard MoM 5-langkah (`step-setup` … `step-finalize`)
- Pembina templat eksport (`export-templates/*/builder`)
- Analytics governance & intelligence
- Panel super admin, tetapan organisasi, pengurusan API key
- Pengurusan pengguna & jemputan
- Konfigurasi webhook
- Sub-organisasi / reseller

---

## 12. Perkara terbuka untuk keputusan

1. **Tempoh hayat token** — cadangan 90 hari dengan refresh; atau tanpa tamat tempoh dengan pembatalan jauh? Segmen governance mungkin memerlukan yang pertama.
2. **Pengesahan dua faktor** — belum wujud dalam produk. Perlu untuk pelanggan lembaga pengarah?
3. **`meeting.content` adalah HTML** — mobile perlu renderer HTML atau kita hantar juga versi terstruktur (blok JSON)? HTML lebih mudah sekarang, terstruktur lebih baik jangka panjang.
4. **Anotasi PDF** — perlu jadual `document_annotations` baharu. Simpan sebagai koordinat + laluan (vektor) atau sebagai PDF beranotasi? Vektor lebih fleksibel dan boleh disaring ikut pengguna.
5. **Undian berwajaran** — Convene menyokongnya; `resolution_votes` kita tiada medan `weight`. Tambah sekarang atau tunggu permintaan?
