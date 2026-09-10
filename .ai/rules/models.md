---
paths:
  - 'app/Models/**'
---

# Models

## Never put a bare float parameter on the right of a comparison in whereRaw
PDO binds PHP floats as strings. SQLite only applies numeric affinity when a parameter is compared against a column, so a bare `?` on the right of a comparison against a computed expression stays TEXT - and in SQLite every number sorts before every text value, so `expr <= ?` is silently always true. No error, just wrong rows.

Keep bound numbers inside arithmetic so they are forced to numbers, e.g. `... <= ? * ?` binding the radius twice, or move the term to the left and compare against a literal 0. `Stand::near()` documents the live example. If you add a numeric whereRaw, assert its result against the same calculation done in PHP.

## Branch is the tenant dimension: scope every query and binding to it
One database holds every branch. A branch owns its townships, its staff, its buyers, and every sale, receipt and journal entry raised there, which is what lets the same ledger produce per-branch and consolidated statements.

- `Stand`, `Sale` and `Payment` use the `ScopedToUserBranch` trait, so route binding resolves only within the signed-in user's branch and another branch's record reads as 404 rather than 403. A 403 would confirm the reference exists.
- Every list query goes through the model's `forBranch` scope. `Stand::forBranch(null)` deliberately returns nothing: a user without a branch sees no stock at all, rather than everything.
- Statements are the one place a caller may cross the boundary, and only to consolidate: `StatementRequest::branch()` accepts the caller's own code or `group`, and aborts 403 on anything else. There is no role system yet - a head-office role is the obvious next step.
- Stand numbers are unique system-wide, not per township. `front-end/scripts/generate-townships.mjs` keeps each estate's numbering range apart, and the unique index enforces it.
- Document numbers (`SALE-`, `RCP-`, `JE-`) are sequential per branch via `App\Support\DocumentNumber`, allocated inside the writing transaction; the unique index on each column is the real guard against a race.
