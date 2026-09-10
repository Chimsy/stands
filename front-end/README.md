# Stand Locator - front-end

Next.js app for the site map, sales, receipts and financial statements. All data
comes from the Laravel API in the parent directory; this app holds no fixtures
of its own.

## Getting started

1. Start the backend. It is served by Laravel Herd at `https://stands.test`, and
   needs to have been migrated and seeded:

   ```bash
   cd .. && composer setup
   ```

2. Point this app at the API:

   ```bash
   cp .env.example .env.local
   ```

3. Run the dev server:

   ```bash
   npm run dev
   ```

Open [http://localhost:3000](http://localhost:3000). Every page requires a
signed-in session; the seeded accounts are `test@example.com` (Harare) and
`bulawayo@example.com` (Bulawayo), both with the password `password`.

## Pages

| Route | |
| --- | --- |
| `/` | The site map. Search, filter and open a stand. |
| `/stands/[standNumber]` | A stand's profile, and the form that sells it. |
| `/sales` | Sales booked at the branch. |
| `/sales/[reference]` | One sale: schedule, receipts, and the form that receipts a payment. |
| `/receipts` | Receipts issued at the branch. |
| `/receipts/[receiptNumber]` | The printable receipt. |
| `/statements` | Income statement, balance sheet, trial balance and receivables ageing. |

## How it talks to the API

- `lib/api.ts` is the only place that calls `fetch`. It reads the API token from
  an httpOnly cookie and sends it as a bearer token, so the browser never sees
  the token and never talks to the backend directly.
- `lib/stands.ts`, `lib/trading.ts` and `lib/reports.ts` are the read layers the
  pages use. Writes go through the server actions in `app/(app)/sales/actions.ts`,
  so a rejected write can put its errors back into the form that caused it.
- `proxy.ts` turns visitors without a session cookie away before a page renders.
  That is an optimistic check only; `app/(app)/layout.tsx` confirms the token
  against the API on every request.

## Two things that will bite

- **Local HTTPS.** Herd signs `stands.test` with its own certificate authority,
  which macOS trusts but Node does not. `scripts/run-next.mjs` points
  `NODE_EXTRA_CA_CERTS` at that CA before starting Next, which is why the npm
  scripts go through it rather than calling `next` directly.
- **Dates.** The API validates sale and receipt dates against its own
  `APP_TIMEZONE`. `lib/business-date.ts` formats "today" in the same zone, set by
  `BUSINESS_TIMEZONE` in `.env.local`; keep the two in step, or an agent working
  late will be handed yesterday.

## Receipts

There is no PDF renderer. `/receipts/[receiptNumber]` is a print-styled page and
Download opens the browser's print dialog, where the viewer saves it as a PDF.
Print rules live at the bottom of `app/globals.css`; the app chrome is hidden
with `print:hidden`.

## Sample data

`npm run generate:plan` regenerates both township fixtures into
`../database/data/`. Re-seed the backend afterwards with
`php artisan migrate:fresh --seed`.
