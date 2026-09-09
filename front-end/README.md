# Stand Locator - front-end

Next.js app for browsing the township site map. All data comes from the Laravel
API in the parent directory; this app holds no fixtures of its own.

## Getting started

1. Start the backend. It is served by Laravel Herd at `https://stands.test`, and
   needs to have been migrated and seeded:

   ```bash
   cd .. && php artisan migrate --seed
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
signed-in session; the seeded account is `test@example.com` / `password`.

## How it talks to the API

- `lib/api.ts` is the only place that calls `fetch`. It reads the API token from
  an httpOnly cookie and sends it as a bearer token, so the browser never sees
  the token and never talks to the backend directly.
- `lib/stands.ts` is the data-access layer the pages use. Swapping endpoints or
  adding filters happens there, not in components.
- `proxy.ts` turns visitors without a session cookie away before a page renders.
  That is an optimistic check only; `app/(app)/layout.tsx` confirms the token
  against the API on every request.
- Local HTTPS: Herd signs `stands.test` with its own certificate authority,
  which macOS trusts but Node does not. `scripts/run-next.mjs` points
  `NODE_EXTRA_CA_CERTS` at that CA before starting Next, which is why the npm
  scripts go through it.

## Sample data

`npm run generate:plan` regenerates the deterministic sample township into
`../database/data/`. Re-seed the backend afterwards with
`php artisan db:seed --class=SitePlanSeeder`.
