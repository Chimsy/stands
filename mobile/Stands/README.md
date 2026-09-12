# Stand Locator for Android

The head-office view of the business, on a phone. Signs in with the same
accounts as the web app, reads the same `/api/v1` endpoints, and is **for
administrators only** - an agent's credentials are accepted by the API but
turned away here with an explanation, because this build has no screens for a
single branch's day-to-day work.

## Running it

The app talks to the Laravel API in the repository root. Point it at yours with
a Gradle property rather than editing source:

```bash
./gradlew installDebug -Pstands.apiBaseUrl=https://magaya.chimsy.co.za/api/v1/
```

| Property | Default | |
| --- | --- | --- |
| `stands.apiBaseUrl` | `https://magaya.chimsy.co.za/api/v1/` | Base URL, trailing slash required. |
| `stands.devHostAddress` | `10.0.2.2` | Debug only: what the API's hostname resolves to. |

Herd serves the backend under a name the emulator cannot resolve, signed by a
local authority it has never heard of. Two ways round it, both debug-only:

1. **Trust Herd's CA** (closest to production - same HTTPS URL as the browser).
   Install `~/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem`
   on the emulator through Settings → Security → Encryption & credentials, then
   build with the default URL. `DevHostDns` rewrites `stands.test` to
   `stands.devHostAddress` while leaving the Host header and TLS handshake
   naming the real site.
2. **Plain HTTP over an adb tunnel** (quickest). Serve the API on a port, map it
   into the device, and point the app at the loopback address:

   ```bash
   adb reverse tcp:8123 tcp:8123
   ./gradlew installDebug -Pstands.apiBaseUrl=http://127.0.0.1:8123/api/v1/
   ```

   The debug build permits cleartext to loopback addresses only; a release build
   permits none, from `src/main/res/xml/network_security_config.xml`.

## How it is put together

MVVM, one Activity, Compose throughout.

```
ui/          Compose screens and their ViewModels. Screens render state and
             emit events; they never call a repository.
domain/      The models the UI speaks in, and `Scope` / `Cached`.
data/
  remote/    Retrofit API, DTOs, the interceptor that attaches credentials.
  local/     Room. The device's copy of what the API last said.
  session/   The token, encrypted with a key held in the Android Keystore.
  repository/ Decides what to read from where. The only layer that knows both.
di/          `AppContainer`, the composition root.
```

**Dependency injection is by hand.** The graph is small and every class takes
what it needs through its constructor, which is what actually makes the
ViewModels testable - a test builds one with fakes and touches none of this.
Adding a code generator to wire a dozen objects would cost build time and a
toolchain constraint without changing a line of the tests.

## Offline first, and it says so

Screens render the **database**, never a network response. Refreshing means
writing to the database; the UI updates because it is collecting it. That one
rule is what makes the app open instantly, keep working in a lift, and never
show two panels from two different fetches.

- Opening a screen shows the cache immediately and refreshes behind it only if
  the cache is older than five minutes.
- A pull-to-refresh always asks the server.
- **Every screen says when its figures were last true** - "Updated 4 min ago" -
  because a cached screen that does not date itself is indistinguishable from a
  live one.
- A failed refresh leaves the figures on screen with a note above them. Dated
  figures beat a blank page.
- A rejected token is the one failure that ends the session. Being unreachable
  is not.

Reports are cached as the JSON the API computed, keyed by report and scope;
sales get real columns because the app queries them as a list. Cache tables are
dropped and refetched on a schema change - they hold nothing the server does not
already own.

## Tests

```bash
./gradlew testDebugUnitTest lintDebug
```

`ScopedFeedViewModelTest` covers the refresh loop - what is shown, when the
network is consulted, and what survives a failure. `StandsApiTest` runs the
networking layer against a real HTTP server so the contract with Laravel is
checked rather than assumed. Lint is part of the gate: it is what caught
`java.time` being API 26 against a minSdk of 25.
