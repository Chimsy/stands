---
paths:
  - 'front-end/**'
---

# Front End

## Front-end talks to the API server-side only, and Next 16 renamed middleware to proxy
- There are no data fixtures in `front-end/` any more; the seed JSON lives in `database/data/` and is served through the API. `lib/stands.ts` is the only data-access layer, and `lib/api.ts` the only place calling `fetch`.
- The Sanctum token is kept in an httpOnly cookie and attached server-side, so the browser never holds it and never calls the backend directly. `API_URL` is therefore not a `NEXT_PUBLIC_` variable.
- Next.js 16 deprecated `middleware.ts` in favour of `proxy.ts` exporting `proxy()`. Do not re-add a middleware file. The proxy is an optimistic cookie check only; `app/(app)/layout.tsx` is what verifies the token against the API.
- Herd signs `stands.test` with its own CA, which Node does not trust. `scripts/run-next.mjs` sets `NODE_EXTRA_CA_CERTS` before starting Next, because Node reads that variable at process start and it cannot come from `.env.local`. Keep the npm scripts pointed at that launcher.
- Check `node_modules/next/dist/docs/` before using a Next API; this major version renamed several things.
