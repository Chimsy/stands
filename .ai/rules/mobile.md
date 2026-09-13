---
paths:
  - 'mobile/**'
---

# Mobile

## Android app: offline-first, manual DI, and two build traps
`mobile/Stands` is a native Kotlin/Compose MVVM app for administrators only. Screens render Room, never a network response - `refresh()` writes to the database and the UI updates because it is collecting it. Keep that direction: two sources of truth is how a dashboard ends up showing panels from two different fetches. Every screen shows when its figures were last true; `ScopedFeedViewModel` holds the whole loop (stale after 5 min, pull-to-refresh always fetches, a failed refresh keeps the figures with a note, only a 401 ends the session).

DI is by hand through `AppContainer` - deliberately, not an oversight. Hilt's Gradle plugin is a hard dependency on AGP and Kotlin versions that this project sits ahead of, and every class is constructor-injected anyway, so the tests gain nothing from a generator.

Two traps that cost real debugging:
- Never call an overridable member from a base-class `init`. `ScopedFeedViewModel` used to start its first fetch there, which runs before the subclass assigns its repository - it crashed on device while every unit test passed, because a queueing test dispatcher deferred the coroutine until after construction. The first fetch hangs off `onStart` instead.
- `minSdk` is 25 and `java.time` is API 26. Core library desugaring is enabled for exactly this; `lintDebug` is part of the build gate because it is what catches this class of bug - the emulator is too new to.

Point the app at a backend with `-Pstands.apiBaseUrl=...`; never hard-code a workstation address. Debug builds permit cleartext to loopback only, from `src/debug/res/xml/network_security_config.xml`, which overlays the strict `src/main` one.

## DevHostDns only redirects local API hosts
`stands.devHostAddress` defaults to `10.0.2.2` only when `stands.apiBaseUrl` names a local backend (`localhost`, `.test`, `.localhost`); against a deployed host it defaults to empty and DNS resolves normally.

This is not cosmetic: when the default base URL was pointed at the live API while the override still defaulted to `10.0.2.2`, every debug build sent `magaya.chimsy.co.za` to the workstation and login died with a 15s `SocketTimeoutException` to `/10.0.2.2:443` - while OkHttp's log still showed the public URL, because the rewrite happens in the resolver, not in the request.

## Material You is off on purpose
`StandsTheme` defaults `dynamicColor = false`. Turning it back on lets the handset's wallpaper repaint the whole head-office view, which throws away the brand and means the green a branch figure is shown in is not the green on the dashboard. The schemes in `ui/theme/Theme.kt` are built from `Color.kt`, whose values are the same hex as the web app's `--color-brand-*` / `--color-marine-*` scales - keep the two in step.
