# Stand Locator

Interactive site map for browsing stand availability across a residential
township. Two applications in one repository:

| | |
| --- | --- |
| Repository root | Laravel 13 API. Serves JSON only - no Blade, no Vite, no web routes. |
| [`front-end/`](front-end) | Next.js 16 app. The only user interface. |

## Requirements

- PHP 8.4 and Composer
- Node 20+
- [Laravel Herd](https://herd.laravel.com), which serves the backend at `https://stands.test`

## Setup

```bash
composer setup
```

That installs dependencies, generates the app key, and runs the migrations and
seeders - which import the sample township from `database/data/` and create a
sign-in of `test@example.com` / `password`.

Then start the front-end:

```bash
cd front-end
cp .env.example .env.local
npm run dev
```

Open <http://localhost:3000>. Everything is behind the sign-in.

## The API

Versioned under `/api/v1`. Every route except `login` requires a Sanctum bearer
token, so unauthenticated requests get `401` rather than a redirect.

| Method | Route | |
| --- | --- | --- |
| `POST` | `/api/v1/login` | Exchanges credentials for a token. Throttled to 5/min per account and address. |
| `POST` | `/api/v1/logout` | Revokes the calling token only. |
| `GET` | `/api/v1/user` | The account behind the current token. |
| `GET` | `/api/v1/site-plan` | Boundary, roads, zones and block labels. |
| `GET` | `/api/v1/stands` | Every stand. Filter with `status`, `block`, `road`, `search`, or `x`+`y`+`radius`. |
| `GET` | `/api/v1/stands/{standNumber}` | One stand, e.g. `/api/v1/stands/2001`. |

Stands are identified by their surveyed stand number, not a database id.
Coordinates are metres on the site plan, origin at its top-left corner.

## Tests

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

## Sample data

`database/data/*.json` is fixture input for `SitePlanSeeder`, not runtime data.
Regenerate it deterministically with `cd front-end && npm run generate:plan`,
then re-seed with `php artisan db:seed --class=SitePlanSeeder`.

## Conventions

Settled decisions and known traps live in [`.ai/rules/`](.ai/rules) - start at
`.ai/rules/index.md`.
