/**
 * Kept apart from `lib/session.ts` so middleware, which runs before the Node
 * runtime is available, can read the cookie name without pulling in
 * `next/headers`.
 */
export const SESSION_COOKIE = "stand_session";

/**
 * The branch an administrator is currently working from. Held apart from the
 * token so signing out clears both, and so an agent - who has no choice of
 * office - never carries one.
 */
export const ACTIVE_BRANCH_COOKIE = "stand_branch";
