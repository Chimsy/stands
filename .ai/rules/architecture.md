---
paths:
  - '{routes,bootstrap,config}/**'
---

# Architecture

## API-only backend: no web routes, no Blade, no Vite
The UI is the Next.js app in `front-end/`. This Laravel application serves JSON and nothing else, and the scaffolding for serving HTML was removed on purpose: no `resources/`, no `vite.config.js`, no root `package.json` / `package-lock.json` / `.npmrc`, no `routes/web.php`.

`bootstrap/app.php` registers `api` and `console` routes only. `/` returning 404 is the intended behaviour, and `tests/Feature/HealthCheckTest.php` asserts it along with `/up` staying open and unauthenticated API calls answering 401 in JSON rather than redirecting.

Do not re-add a web route, a Blade view, or a root `package.json` to render a page - put it in `front-end/`. `php artisan dev` adds a Vite process only when a root `package.json` exists, so leaving that file out is what keeps it out. Run `npm` from `front-end/`, never from the repository root.

## ResolveActiveBranch must run before SubstituteBindings
Route binding is branch-scoped (`ScopedToUserBranch` reads `Auth::user()->activeBranch()`), so the branch has to be settled before bindings resolve. An unprioritised alias runs *after* `SubstituteBindings`, and the failure is silent and nasty: an administrator switching branches changes every list on the page but none of the records behind them, so `/stands/5001` 404s while the list it was clicked from shows it.

`bootstrap/app.php` fixes the order with `$middleware->prependToPriorityList(SubstituteBindings::class, ResolveActiveBranch::class)`. Do not drop that line, and add any future middleware that decides what a query may see the same way. `tests/Feature/Api/V1/ActiveBranchTest.php` asserts it.
