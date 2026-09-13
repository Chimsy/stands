---
paths:
  - 'front-end/**'
---

# Front End

## Front-end talks to the API server-side only, and Next 16 renamed middleware to proxy
- There are no data fixtures in `front-end/` any more; the seed JSON lives in `database/data/` and is served through the API. `lib/stands.ts` is the only data-access layer, and `lib/api.ts` the only place calling `fetch`.
- The Sanctum token is kept in an httpOnly cookie and attached server-side, so the browser never holds it and never calls the backend directly. `API_URL` is therefore not a `NEXT_PUBLIC_` variable.
- Next.js 16 deprecated `middleware.ts` in favour of `proxy.ts` exporting `proxy()`. Do not re-add a middleware file. The proxy is an optimistic cookie check only; `app/(app)/layout.tsx` is what verifies the token against the API.
- Herd signs `stands.test` with its own CA, which Node does not trust. `scripts/run-next.mjs` sets `NODE_EXTRA_CA_CERTS` before starting Next, because Node reads that variable at process start and it cannot come from `.env.local`. Keep the npm scripts pointed at that launcher.
- Check `node_modules/next/dist/docs/` before using a Next API; this major version renamed several things.

## Dev-origin blocking and the business timezone
Two things that look like application bugs but are not:

- Next 16 serves `/_next/*` in development only to origins listed in `allowedDevOrigins`, and answers **403** to anything else. Opening the app on an unlisted host makes every client component silently fail to hydrate - `useState` toggles do nothing and forms stop reacting - with no error beyond failed HMR sockets. `127.0.0.1` is listed alongside the default `localhost` in `next.config.ts`. If hydration is dead, check for 403s on a `/_next/static` asset before suspecting the component.
- The API validates `saleDate` and `paidOn` against its own `APP_TIMEZONE` (Africa/Harare). Never prefill those from `new Date().toISOString()`, which is UTC and hands an agent working after 22:00 local the previous day. Use `lib/business-date.ts`, and keep `BUSINESS_TIMEZONE` in `.env.local` in step with the backend's `APP_TIMEZONE`.

## Branch cookie, and never use Intl compact notation
An administrator's chosen office lives in the httpOnly `stand_branch` cookie and `lib/api.ts` attaches it to every server-side call as `X-Branch`. It is a preference, not a permission - the API decides. `startSession`/`endSession` both clear it so a new sign-in never inherits the last visitor's choice, and `getAuthenticatedUser` drops it on a 403 rather than letting a stale value wedge every page.

Do not use `Intl.NumberFormat` with `notation: "compact"` anywhere rendered on both sides. Node's ICU and the browser's round it differently (`$0.0` against `$0`), which fails hydration - and a hydration failure here silently kills every client interaction on the page. `formatCompactCents` in `lib/format.ts` does it by hand instead.

Chart colours are the `--viz-*` tokens in `globals.css`, assigned in a fixed order and validated for colour-blind separation and contrast against this app's actual surfaces. They are not interchangeable with the `--map-*` tokens, which encode stand status - green/amber/red is unusable as a chart palette (deutan ΔE under 6).

## The brand palette and the logo's five copies
Beyond Reality has two inks: `--color-brand-*` (grass green, the primary action) and `--color-marine-*` (sky blue, selection/focus/links), declared as fixed scales in `@theme` in `app/globals.css`. Light and dark pick different *steps* through `dark:` variants - never redefine a scale value under a media query, or a utility stops meaning one hue. The `--brand-green/blue/sky` vars beneath them do flip, because SVG fills cannot use a `dark:` variant.

The logo is one 64-unit drawing kept in five places: `components/brand-logo.tsx` (the live one, `--brand-*` vars), `app/icon.svg` and `public/logo.svg` (literal hex - a detached SVG has no page to inherit from), and on the Android side `res/drawable/ic_brand_mark.xml` plus the two `ic_launcher_*` drawables. Change the paths in all of them together. `BrandLogo tone="fixed"` exists for the receipt, which is white paper in either theme.
