# antaraNote — mobile

Flutter client for iOS and Android. Talks to `/api/mobile/v1` in the Laravel app
one directory up; the contract is `docs/plans/2026-08-09-mobile-api-v1-spec.md`.

## Running

The API base URL is a compile-time constant, so a debug build can point at Herd
while a release build points at production without a code change deciding it.

```bash
flutter run --dart-define=API_BASE_URL=https://antara-flow.test
```

The Android emulator cannot reach `localhost` — the host machine is `10.0.2.2`:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

## Layout

```
lib/
  core/         config, theme, error envelope, dependency wiring
  data/
    api/        Dio client and the interceptors that carry the API contract
    local/      keychain-backed credential and device storage
    repositories/
  domain/models/
  features/     one directory per screen area
```

UI reads from providers; providers read from repositories; repositories are the
only things that know an API exists.

## Conventions worth knowing before changing anything

- **Every failure is an `ApiException`.** No screen branches on an HTTP status —
  409 means five different things depending on the endpoint, so the branch is
  always on `code`.
- **`Idempotency-Key` goes on every POST**, set by `HeadersInterceptor`. The
  write queue retries after a lost response and cannot know whether the first
  attempt landed.
- **A 401 tears down the session in one place** (`AuthController.onTokenRejected`),
  not in whichever screen noticed first.
- **Being offline never signs anyone out.** `restore()` keeps the session when
  the network is unreachable; only an actual rejection clears it.

## Not wired up yet

Recording, the meeting list, tasks, and the offline store. The permissions those
need are already declared — microphone and background audio on iOS, microphone
plus the foreground-service types on Android — so the recorder can be added
without touching platform configuration.

## Brand assets still needed

The palette and type come from the live platform branding settings. Two things
cannot be reproduced from settings alone and are not in this repository:

**The logo.** `BrandMark` loads these and falls back to a drawn approximation
when they are missing, so the app builds either way — but the "n" is a custom
letterform and the fallback must not ship.

```
mobile/assets/images/logo-mark.png     lime tile alone, square, 512×512
mobile/assets/images/logo-lockup.png   tile + wordmark, transparent, ~1024 wide
```

Nothing else needs changing once they are dropped in.

**Nunito.** Put the `.ttf` files in `assets/fonts/` and declare them in
`pubspec.yaml`; every text style then picks them up. Until then the theme falls
back to the platform's rounded face, which is close but not the brand face.

**Launcher icons** are still Flutter's default. They need generating from
`logo-mark.png` once it exists.

> Note: `docs/brand/BRAND-REFERENCE.md`, `docs/brand/logos/` and
> `public/design-system.html` all describe older brands — teal and gold, and
> purple respectively. None of them match what ships. Do not use them.
