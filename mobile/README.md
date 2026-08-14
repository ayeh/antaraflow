# antaraNote — mobile

Flutter client for iOS and Android. Talks to `/api/mobile/v1` in the Laravel app
one directory up; the contract is `docs/plans/2026-08-09-mobile-api-v1-spec.md`.

## Running

The API base URL is a compile-time constant, so a debug build can point at Herd
while a release build points at production without a code change deciding it.

```bash
flutter run --dart-define=API_BASE_URL=https://antaraflow.test
```

The Android emulator cannot reach `localhost` — the host machine is `10.0.2.2`:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

## Shipping to TestFlight

`API_BASE_URL` defaults to the Herd host, so **a release built without the
define points a tester's phone at `antaraflow.test` and shows nothing but "No
connection"**. It builds, signs, uploads and installs perfectly; it just cannot
reach anything. Build 5 went out that way.

```bash
flutter build ipa --release --dart-define=API_BASE_URL=https://note.antara.cloud
```

Then upload. The App Store Connect API key lives in
`~/.appstoreconnect/private_keys/`; the issuer is on the Users and Access →
Integrations page:

```bash
xcrun altool --upload-app --type ios \
  -f build/ios/ipa/antaranote.ipa \
  --apiKey PVLV32P899 \
  --apiIssuer 69a6de8c-eb5b-47e3-e053-5b8c7c11a4d1
```

Bump `version:` in `pubspec.yaml` first — App Store Connect rejects a build
number it has already seen, and it counts a rejected upload as seen.

## Shipping to Play

The same `API_BASE_URL` warning applies, and for the same reason — the default
is the Herd host.

### The upload key

Release builds are signed with the upload key described by
`android/key.properties`, which is gitignored along with the keystore itself.
**Losing that keystore means never being able to publish an update to the
listing again**, so it is generated once, backed up before anything is built
with it, and never committed.

Generate it (`keytool` needs the Android Studio JDK; the system `java` on this
machine is not installed):

```bash
"/Applications/Android Studio.app/Contents/jbr/Contents/Home/bin/keytool" -genkeypair -v -keystore ~/.android/antaranote-upload.jks -storetype PKCS12 -keyalg RSA -keysize 4096 -validity 10000 -alias antaranote-upload -dname "CN=antaraNote, O=Antara, C=MY"
```

Then point Gradle at it — `android/key.properties`, never committed:

```
storeFile=/Users/ayeh/.android/antaranote-upload.jks
storePassword=…
keyAlias=antaranote-upload
keyPassword=…
```

`storeFile` is resolved from `mobile/android/`, so an absolute path is the
safe form. When `key.properties` is absent the release build falls back to the
debug key with a warning, so `flutter run --release` still works for anyone
without it — but a debug-signed AAB is rejected by Play Console, so the
signature check below is not optional.

### Build

```bash
flutter build appbundle --release --dart-define=API_BASE_URL=https://note.antara.cloud
```

Verify the artefact before uploading. The build succeeds either way, so the
command line is not evidence:

```bash
unzip -o -q -d /tmp/aab build/app/outputs/bundle/release/app-release.aab
strings -a /tmp/aab/base/lib/arm64-v8a/libapp.so | grep -E "note\.antara\.cloud|antaraflow\.test"
```

The compiled `libapp.so` must contain `https://note.antara.cloud/api/mobile/v1`
and must not contain `antaraflow.test`. Note that a plain `grep` on the `.so`
reports nothing either way — it treats the file as binary — so the string has
to come out through `strings`.

Confirm it is the upload key and not the debug key:

```bash
"/Applications/Android Studio.app/Contents/jbr/Contents/Home/bin/keytool" -printcert -jarfile build/app/outputs/bundle/release/app-release.aab | grep Owner
```

`CN=Android Debug` means `key.properties` was not picked up. Stop there.

Play also rejects native libraries that are not 16 KB page aligned. Flutter's
own libraries are fine; a plugin bump is what would regress this, so it is
worth re-checking whenever `pubspec.lock` moves. Every `LOAD` alignment must be
`0x4000` or larger:

```bash
for so in /tmp/aab/base/lib/arm64-v8a/*.so; do echo "$(basename $so): $(~/Library/Android/sdk/ndk/*/toolchains/llvm/prebuilt/darwin-x86_64/bin/llvm-readelf -l $so | awk '/LOAD/{print $NF}' | sort -u | tr '\n' ' ')"; done
```

Then upload the AAB at Play Console → antaraNote → Testing → Internal testing →
Create new release. The first upload also has to enrol in Play App Signing,
which is where the upload key stops being the signing key — Google re-signs
with a key it holds, and the upload key only proves the build came from us.

Bump `version:` in `pubspec.yaml` first; Play rejects a version code it has
already seen. It reaches the manifest through `flutter.versionCode`, so
`aapt2 dump badging` on the APK is the way to confirm it landed.

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

## Brand assets

Palette and type come from the live platform branding settings. The artwork is
in `assets/images/`: `logo-mark.png` (512², the tile) and `logo-lockup.png`
(1024×144, tile plus wordmark). `BrandMark` loads them and only falls back to a
drawn approximation if they go missing.

Launcher icons are generated from `logo-mark.png`. Two constraints to keep if
they are ever regenerated:

- **iOS icons carry no alpha.** The App Store rejects an alpha channel, and iOS
  applies its own corner mask — so the tile is flattened onto its own lime and
  bled to the full square.
- **The Android adaptive foreground sits at 62% of the canvas.** Anything
  outside the safe zone gets cropped by whatever shape the launcher uses.

**Still needed: Nunito.** Put the `.ttf` files in `assets/fonts/` and declare
them in `pubspec.yaml`; every text style then picks them up. Until then the
theme falls back to the platform's rounded face, which is close but not the
brand face.

> Note: `docs/brand/BRAND-REFERENCE.md`, `docs/brand/logos/` and
> `public/design-system.html` all describe older brands — teal and gold, and
> purple respectively. None of them match what ships. Do not use them.
