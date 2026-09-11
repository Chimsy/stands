---
paths:
  - 'app/Http/**'
---

# Http

## API shape: versioned routes, camelCase resources, stand_number as the public id
The Next.js app in `front-end/` is the only consumer, so the API is the contract between them.

- Routes live under `routes/api.php` behind `prefix('v1')->name('api.v1.')`, and everything except `login` sits behind `auth:sanctum`.
- Resources emit camelCase keys because the TypeScript types in `front-end/types/stand.ts` mirror them one-for-one. Change one, change the other.
- `StandResource` deliberately does not expose the database id. `stand_number` is unique, stable and what `Stand::getRouteKeyName()` returns, so `/api/v1/stands/2001` and the front-end's `/stands/2001` are the same identifier.
- `GET /stands` returns the whole filtered set unpaginated. The site map draws every stand at once, so adding pagination would silently break it - narrow with the `status`, `block`, `road`, `search` or `x`/`y`/`radius` filters instead.
- Money is stored as `price_cents`; the `price` accessor and the resource expose major units.

## X-Branch selects the office; admin-only routes use the admin alias
Every authenticated route sits behind the `branch` middleware. The caller names the office in the `X-Branch` header (a branch code); an agent may only name their own, an administrator any. A code that does not exist or is not permitted is a 403 rather than being ignored, because silently answering for a different branch than the caller believes they are in would misfile a sale.

`StatementRequest::branch()` decides whose books a caller reads. An agent gets their own branch only - naming another, or asking for the consolidated `group`, is a 403. An administrator may name any branch or `group`. It is shared by the statements and `GET /v1/dashboard`, so the books and the sales floor read the same scope and dates.

Head-office-only routes add the `admin` alias (`EnsureUserIsAdmin`) and answer 403 - unlike a cross-branch record, the existence of the endpoint is not worth hiding.
