<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

# Stand Locator

> **This section takes precedence over the generated guidelines above.** Two of
> them no longer apply here: "Frontend Bundling" and "Vite Error". This project
> has no Vite build and no root `package.json` - if the UI looks stale, the
> Next.js dev server in `front-end/` is what needs restarting.

Two applications in one repository, talking to each other over HTTP only.

| | |
| --- | --- |
| Repository root | Laravel 13 **API-only backend**. Served by Herd at `https://stands.test`. |
| `front-end/` | Next.js 16 app - the **only** user interface. Runs at `http://localhost:3000`. |

## The backend serves no HTML

Blade, Vite, the asset pipeline and `routes/web.php` were deliberately removed:
there is no `resources/`, no `vite.config.js`, and no root `package.json`.
`bootstrap/app.php` registers `api` and `console` routes only, so `/` returns
404 by design and `tests/Feature/HealthCheckTest.php` protects that.

Do not re-add a web route, a Blade view, or a root `package.json` to render a
page - the UI belongs in `front-end/`. (`php artisan dev` picks up Vite only
when a root `package.json` exists, which is why removing it was enough.)

## Working on it

```bash
composer setup                      # install, key, migrate --seed
cd front-end && cp .env.example .env.local && npm run dev
```

Every page and every endpoint except `POST /api/v1/login` requires a token, and
everything a user sees is scoped to their branch.

- Backend tests: `php artisan test --compact`. Format with `vendor/bin/pint --dirty --format agent`.
- Front-end: `npm run build` and `npx eslint` from `front-end/`.
- Never run `npm` from the repository root; it has no package manifest.

## Selling, receipting and the books

Selling a stand and taking money for it are recorded as double-entry
bookkeeping, so the financial statements are derived from the ledger rather
than assembled from summaries.

- **Post through the action, never the tables.** `App\Actions\PostJournalEntry`
  is the only writer of `journal_entries` / `journal_lines`, and it refuses an
  entry whose debits and credits disagree. Entries are append-only; a mistake is
  corrected with a reversing entry.
- **Revenue is recognised at signing**, not on collection. `SellStand` takes the
  whole price to revenue, carries the unpaid part as a receivable, and charges
  the stand's cost out of Land Inventory the same day. `RecordPayment` only
  moves receivable to bank.
- **Money is integer minor units** (`*_cents`) everywhere behind the API.
  Resources divide by 100 at the edge; nothing else does.
- **Statements are live.** `App\Reports\*` read the journal on every request.
  There is no period close, and retained earnings is derived rather than posted.

## One ledger, many branches

A branch owns its townships, staff, buyers, and every sale, receipt and journal
entry raised there. That single dimension is what lets the same ledger answer
both "how did Bulawayo do" and "how did the group do".

Everything operational is scoped to the signed-in user's branch, and a record
belonging to another branch reads as **404, not 403** - a 403 would confirm the
reference exists. Statements are the one place a caller may cross the boundary,
and only to consolidate the whole group.

Seeded sign-ins: `test@example.com` (Harare) and `bulawayo@example.com`
(Bulawayo), both with the password `password`.

## Where the data lives

The sample townships are **fixture input, not runtime data**. Each lives in
`database/data/<slug>/`, is imported by `database/seeders/SitePlanSeeder.php`
against its branch, and is regenerated deterministically by
`cd front-end && npm run generate:plan` (re-seed afterwards). `front-end/` holds
no data files of its own.

`TradingHistorySeeder` then sells the stands the fixture marks as sold or in
progress, through the same actions the API uses, so the seeded books are
produced the way a real one would be. Seeding takes about ten seconds.

## Read before editing

`.ai/rules/` holds the settled decisions and the traps - the posting rules, the
branch scoping contract, the API shape, the front-end's server-side-only API
access, the Next 16 `proxy.ts` rename, and a SQLite `whereRaw` binding trap that
silently returns wrong rows. Start at `.ai/rules/index.md` and read every file
whose globs match what you are touching.
