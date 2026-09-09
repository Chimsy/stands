/**
 * Kept apart from `lib/session.ts` so middleware, which runs before the Node
 * runtime is available, can read the cookie name without pulling in
 * `next/headers`.
 */
export const SESSION_COOKIE = "stand_session";
