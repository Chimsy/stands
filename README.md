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
- [Laravel Herd](https://herd.laravel.com), which serves the backend at `https://stands.test`

## Setup

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

| Sign-in | Branch | Password |
| --- | --- | --- |
| `test@example.com` | Harare — Riverstone Park Estate | `password` |
| `bulawayo@example.com` | Bulawayo — Hillside Park Estate | `password` |

## What it does

- **Site map** — every stand in the branch's township, drawn from the plan, searchable and filterable.
- **Selling** — cash or a deposit-and-instalments payment plan, against a registered buyer.
- **Receipting** — every payment issues a numbered receipt, printable and saveable as a PDF from the browser.
- **Statements** — income statement, balance sheet, trial balance and receivables ageing, for the branch or consolidated across the group, read live from the ledger.

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
routes are scoped to the caller's branch, and another branch's record returns
`404` rather than `403`.

| Method | Route | |
| --- | --- | --- |
| `POST` | `/api/v1/login` | Exchanges credentials for a token. Throttled to 5/min per account and address. |
| `POST` | `/api/v1/logout` | Revokes the calling token only. |
| `GET` | `/api/v1/user` | The account behind the current token, and its branch. |
| `GET` | `/api/v1/branches` | Every branch, for the consolidated statements view. |
| `GET` | `/api/v1/site-plan` | Boundary, roads, zones and block labels for the caller's township. |
| `GET` | `/api/v1/stands` | Every stand. Filter with `status`, `block`, `road`, `search`, or `x`+`y`+`radius`. |
| `GET` | `/api/v1/stands/{standNumber}` | One stand, e.g. `/api/v1/stands/2001`. |
| `GET`&nbsp;/&nbsp;`POST` | `/api/v1/buyers` | List or register buyers. `search` narrows by name, phone or identity number. |
| `GET`&nbsp;/&nbsp;`POST` | `/api/v1/sales` | List or book sales. Filter with `status`, `type`, `standNumber`. |
| `GET` | `/api/v1/sales/{reference}` | One sale with its schedule and receipts. |
| `POST` | `/api/v1/sales/{reference}/payments` | Receipt a payment; returns the receipt. |
| `GET` | `/api/v1/receipts` | Receipts issued at the branch. |
| `GET` | `/api/v1/receipts/{receiptNumber}` | One printable receipt. |
| `GET` | `/api/v1/reports/trial-balance` | `branch=<code>` or `branch=group`; `to` sets the cut-off. |
| `GET` | `/api/v1/reports/income-statement` | Also takes `from`; defaults to the year to date. |
| `GET` | `/api/v1/reports/balance-sheet` | |
| `GET` | `/api/v1/reports/receivables-ageing` | Outstanding instalments, bucketed by how late they are. |

Stands are identified by their surveyed stand number, not a database id.
Coordinates are metres on the site plan, origin at its top-left corner.

## Tests

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

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
