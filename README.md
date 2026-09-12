# Stand Locator

Site map, sales and books for a residential township developer trading from
several branches. Two applications in one repository:

| | |
| --- | --- |
| Repository root | Laravel 13 API. Serves JSON only - no Blade, no Vite, no web routes. |
| [`front-end/`](front-end) | Next.js 16 app. The only user interface. |

## Requirements

- PHP 8.4 and Composer
- Node 20+
- Android Studio and JDK 17+, for the phone app in `mobile/Stands/` (optional)
- MySQL 8+ reachable on `127.0.0.1:3306`
- [Laravel Herd](https://herd.laravel.com), which serves the backend at `https://stands.test`

## Setup

Create the database and point `.env` at it. The schema is built by migration,
so the database only needs to exist and be writable:

```sql
CREATE DATABASE stands CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stands
DB_USERNAME=
DB_PASSWORD=
```

Then:

```bash
composer setup
```

That installs dependencies, generates the app key, migrates, and seeds: two
branches, two townships, a chart of accounts, and a trading history for each
branch. It takes about ten seconds.

Then start the front-end:

```bash
cd front-end
cp .env.example .env.local
npm run dev
```

Open <http://localhost:3000> and sign in. Everything is behind the sign-in.

| Sign-in | Role | Branch |
| --- | --- | --- |
| `harare@chimsy.co.za` | Sales | Harare — Riverstone Park Estate |
| `bulawayo@chimsy.co.za` | Sales | Bulawayo — Hillside Park Estate |
| `magaya@chimsy.co.za` | Admin | Harare, and free to switch to any branch |

All three use the password `#p@$$123!`.

## What it does

- **Site map** — every stand in the branch's township, drawn from the plan, searchable and filterable.
- **Selling** — cash or a deposit-and-instalments payment plan, against a registered buyer.
- **Receipting** — every payment issues a numbered receipt, printable and saveable as a PDF from the browser.
- **Statements** — income statement, balance sheet, trial balance and receivables ageing, read live from the ledger. An agent sees their own branch; an administrator sees the consolidated group and each branch on its own.
- **Dashboard** — head-office only: signings against collections by month, branch performance, stock take-up, sale mix and leading agents, for one branch or the whole group.
- **Android app** — the same head-office view on a phone, offline-first: it opens from a local cache, says when the figures were last refreshed, and keeps working without a connection. See [`mobile/Stands/`](mobile/Stands/README.md).

## The accounting

Transactions are recorded as double entry. `App\Actions\PostJournalEntry` is the
only writer of the journal and refuses an entry that does not balance, so the
trial balance cannot drift. Entries are append-only; corrections are reversing
entries.

Revenue is recognised **when the stand is sold**, not when the money arrives, so
a payment plan raises the whole price as revenue and carries the unpaid part as
a receivable:

```
On sale        Dr  Accounts Receivable    price
                   Cr  Stand Sales Revenue        price
               Dr  Cost of Sales           cost
                   Cr  Land Inventory              cost

On receipt     Dr  Bank                  amount
                   Cr  Accounts Receivable       amount
```

The chart of accounts is shared across branches; the branch is a dimension on
each journal entry. That is what lets one ledger produce both a branch's own
statements and the consolidated group.

Money is stored as integer minor units throughout. Statements are derived from
the journal on every request — there is no period close, and retained earnings
is computed rather than posted.

## The API

Versioned under `/api/v1`. Every route except `login` requires a Sanctum bearer
token, so unauthenticated requests get `401` rather than a redirect. Operational
routes are scoped to the branch the request is worked from, and another branch's
record returns `404` rather than `403`.

A sales agent is fixed to their own branch, statements included - another
branch's books and the consolidated group are both a `403`. An administrator
names the office they are working from in an `X-Branch` header, and every list,
document and statement in that request answers for it; they alone may read
another branch's books, the group, and the dashboard.

| Method | Route | |
| --- | --- | --- |
| `GET` | `/api/v1/health` | Liveness. Open, reads nothing, always `200` while the process is up. |
| `GET` | `/api/v1/health/ready` | Readiness. Open; `503` once this instance cannot serve. |
| `GET` | `/api/v1/health/diagnostics` | Full report. Administrators, or the `X-Health-Token` secret. |
| `POST` | `/api/v1/login` | Exchanges credentials for a token. Throttled to 5/min per account and address. |
| `POST` | `/api/v1/logout` | Revokes the calling token only. |
| `GET` | `/api/v1/user` | The account behind the current token, and its branch. |
| `GET` | `/api/v1/branches` | Every branch. |
| `GET` | `/api/v1/site-plan` | Boundary, roads, zones and block labels for the caller's township. |
| `GET` | `/api/v1/stands` | Every stand. Filter with `status`, `block`, `road`, `search`, or `x`+`y`+`radius`. |
| `GET` | `/api/v1/stands/{standNumber}` | One stand, e.g. `/api/v1/stands/2001`. |
| `GET`&nbsp;/&nbsp;`POST` | `/api/v1/buyers` | List or register buyers. `search` narrows by name, phone or identity number. |
| `GET`&nbsp;/&nbsp;`POST` | `/api/v1/sales` | List or book sales. Filter with `status`, `type`, `standNumber`, and `branch` (a code, or `group` for administrators). |
| `GET` | `/api/v1/sales/{reference}` | One sale with its schedule and receipts. |
| `POST` | `/api/v1/sales/{reference}/payments` | Receipt a payment; returns the receipt. |
| `GET` | `/api/v1/receipts` | Receipts issued at the branch. `branch` scopes it as above. |
| `GET` | `/api/v1/receipts/{receiptNumber}` | One printable receipt. |
| `GET` | `/api/v1/reports/trial-balance` | `branch=<code>`, or `branch=group` for administrators; `to` sets the cut-off. |
| `GET` | `/api/v1/reports/income-statement` | Also takes `from`; defaults to the year to date. |
| `GET` | `/api/v1/reports/balance-sheet` | |
| `GET` | `/api/v1/reports/receivables-ageing` | Outstanding instalments, bucketed by how late they are. |
| `GET` | `/api/v1/dashboard` | Administrators only. Same `branch`/`from`/`to` scope as a statement. |

Stands are identified by their surveyed stand number, not a database id.
Coordinates are metres on the site plan, origin at its top-left corner.

## Health and monitoring

Three endpoints, because three different things ask. All of them answer `200`
while the application is usable and `503` once it is not, so a monitor needs no
rule beyond the status code, and `degraded` - serving, but something wants
looking at - stays `200`.

| Endpoint | Point it at | Runs |
| --- | --- | --- |
| `/api/v1/health` | The orchestrator's liveness probe | Nothing. It answers through a database outage on purpose: a failing dependency should not restart a healthy container. |
| `/api/v1/health/ready` | The load balancer | Database, pending migrations, a cache round trip, storage writability, and the accounts the posting rules name by code. |
| `/api/v1/health/diagnostics` | An alerting monitor, or a person | All of the above with detail, plus the queue backlog and **the ledger's total debits against its total credits**, and a description of the instance. |

The ledger check is the one to page on: `PostJournalEntry` refuses an entry that
does not balance, so a `failed` there means something wrote to `journal_lines`
behind its back and every statement derived from them has stopped being
trustworthy.

Diagnostics names drivers, versions and table sizes, so it is never public. An
administrator's token opens it; so does `HEALTH_CHECK_TOKEN` in an
`X-Health-Token` header, which is how an unattended monitor reads it without
holding anybody's credentials. `APP_VERSION` is echoed back by all three, so a
deploy can be confirmed from outside.

Checks are registered in `config/health.php`; adding one is a class implementing
`App\Support\Health\HealthCheck` and a line of configuration. Thresholds
(`HEALTH_SLOW_MS`, `HEALTH_MAX_QUEUE_BACKLOG`, `HEALTH_MAX_FAILED_JOBS`) decide
where healthy stops and degraded begins.

## API reference (OpenAPI)

[`openapi.yaml`](openapi.yaml) is the whole `v1` API as an OpenAPI 3.1 document: every endpoint and its
parameters, the branch-scoping rules, request and response schemas, the error shapes, and worked examples
taken from the seeded books.

Nothing serves it - this backend returns no HTML by design - so read it with whichever tool you prefer:

```bash
npx @redocly/cli preview-docs openapi.yaml    # Redoc, in a browser
npx @redocly/cli lint openapi.yaml            # after changing the API
```

For Swagger UI itself, without installing anything into the project:

```bash
docker run --rm -p 8080:8080 -e SWAGGER_JSON=/spec/openapi.yaml -v "$PWD:/spec" swaggerapi/swagger-ui
```

WebStorm renders `openapi.yaml` in an editor tab and will fire requests from it; so will
[editor.swagger.io](https://editor.swagger.io) if you would rather paste it in. Anything that "tries it out"
needs a token from `POST /api/v1/login` first - the spec declares the bearer scheme, so the tool will offer
somewhere to put it.

The document is written by hand and is part of the API's contract: change a route, a resource or a form
request, and change it in the same commit. Generating it from the code instead would mean adding a package
such as `dedoc/scramble`, which is a dependency decision, not a documentation one.

## Tests

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

The suite runs against SQLite in memory (pinned in `phpunit.xml`), so it is
fast and needs no database of its own. That is a different engine from the one
the app runs on - see the date-range trap in `.ai/rules/actions-reports.md`
before writing a report that filters on a date.

The accounting is covered against hand-worked figures in
`tests/Feature/Reports/FinancialStatementsTest.php`, which asserts that debits
equal credits, that assets equal liabilities plus equity, that the branches sum
to the group, and that the receivables ageing ties back to the Accounts
Receivable balance.

## Sample data

`database/data/<township>/*.json` is fixture input for `SitePlanSeeder`, not
runtime data. Regenerate both townships deterministically with
`cd front-end && npm run generate:plan`, then re-seed with
`php artisan migrate:fresh --seed`.

## Conventions

Settled decisions and known traps live in [`.ai/rules/`](.ai/rules) - start at
`.ai/rules/index.md`.
