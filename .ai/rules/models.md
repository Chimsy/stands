---
paths:
  - 'app/Models/**'
---

# Models

## Never put a bare float parameter on the right of a comparison in whereRaw
PDO binds PHP floats as strings. SQLite only applies numeric affinity when a parameter is compared against a column, so a bare `?` on the right of a comparison against a computed expression stays TEXT - and in SQLite every number sorts before every text value, so `expr <= ?` is silently always true. No error, just wrong rows.

Keep bound numbers inside arithmetic so they are forced to numbers, e.g. `... <= ? * ?` binding the radius twice, or move the term to the left and compare against a literal 0. `Stand::near()` documents the live example. If you add a numeric whereRaw, assert its result against the same calculation done in PHP.
