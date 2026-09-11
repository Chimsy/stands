---
paths:
  - 'app/Models/**'
---

# Models

## Never put a bare float parameter on the right of a comparison in whereRaw
PDO binds PHP floats as strings. SQLite only applies numeric affinity when a parameter is compared against a column, so a bare `?` on the right of a comparison against a computed expression stays TEXT - and in SQLite every number sorts before every text value, so `expr <= ?` is silently always true. No error, just wrong rows.

Keep bound numbers inside arithmetic so they are forced to numbers, e.g. `... <= ? * ?` binding the radius twice, or move the term to the left and compare against a literal 0. `Stand::near()` documents the live example. If you add a numeric whereRaw, assert its result against the same calculation done in PHP.

The app runs on MySQL, where this particular binding is not a problem - but the test suite runs on SQLite, so the defensive form is what keeps the tests meaningful. Do not "simplify" it away because MySQL tolerates the plain version.

## Branch is the tenant dimension: scope every query and binding to it
One database holds every branch. A branch owns its townships, its staff, its buyers, and every sale, receipt and journal entry raised there, which is what lets the same ledger produce per-branch and consolidated statements.

- `Stand`, `Sale` and `Payment` use the `ScopedToUserBranch` trait, so route binding resolves only within the signed-in user's branch and another branch's record reads as 404 rather than 403. A 403 would confirm the reference exists.
- Every list query goes through the model's `forBranch` scope. `Stand::forBranch(null)` deliberately returns nothing: a user without a branch sees no stock at all, rather than everything.
- Statements do not widen the boundary for an agent: `StatementRequest::branch()` gives them their own branch and refuses everything else, the consolidated `group` included. Only an administrator may name another branch code or ask for `group`. Anything else aborts 403.
- Stand numbers are unique system-wide, not per township. `front-end/scripts/generate-townships.mjs` keeps each estate's numbering range apart, and the unique index enforces it.
- Document numbers (`SALE-`, `RCP-`, `JE-`) are sequential per branch via `App\Support\DocumentNumber`, allocated inside the writing transaction; the unique index on each column is the real guard against a race.

## Two roles, and activeBranch() is the tenant key
`App\Enums\UserRole` has `Sales` and `Admin`. An agent is fixed to `branch_id`; an administrator may work from any branch.

Scope every query to `$user->activeBranch()`, never `$user->branch` or `$user->branch_id` - the former is the office the request is actually being worked from, set for the request by `ResolveActiveBranch` from the `X-Branch` header and falling back to the home branch. `canWorkFrom()` is the only entitlement check; `workFrom()` is transient and must stay that way, so one request's choice never leaks into another.

Note the asymmetry in the `forBranch` scopes: `Stand::forBranch(null)` returns nothing, but `Sale`/`Payment::forBranch(null)` return everything, because the statements consolidate through them. An operational controller must therefore pass a real branch, not a nullable one it has not checked.
