import { cookies } from "next/headers";

import { ACTIVE_BRANCH_COOKIE, SESSION_COOKIE } from "@/lib/session-cookie";

/** Matches the lifetime a Sanctum token is useful for before we ask again. */
const SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

/**
 * The API token is held in an httpOnly cookie so it is never readable from
 * client-side JavaScript; only server components and server actions see it.
 */
export async function getSessionToken(): Promise<string | null> {
  const store = await cookies();
  return store.get(SESSION_COOKIE)?.value ?? null;
}

export async function startSession(token: string): Promise<void> {
  const store = await cookies();
  /** A new sign-in must not inherit the last visitor's choice of office. */
  store.delete(ACTIVE_BRANCH_COOKIE);
  store.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: SESSION_MAX_AGE_SECONDS,
  });
}

export async function endSession(): Promise<void> {
  const store = await cookies();
  store.delete(SESSION_COOKIE);
  store.delete(ACTIVE_BRANCH_COOKIE);
}

/**
 * Which office the API should answer for. Read on every server-side call and
 * sent as `X-Branch`; the API is what decides whether the caller may use it, so
 * this is a preference rather than a permission.
 */
export async function getActiveBranch(): Promise<string | null> {
  const store = await cookies();
  return store.get(ACTIVE_BRANCH_COOKIE)?.value ?? null;
}

export async function setActiveBranch(code: string): Promise<void> {
  const store = await cookies();
  store.set(ACTIVE_BRANCH_COOKIE, code, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: SESSION_MAX_AGE_SECONDS,
  });
}

/** Used when a selection is refused, so a stale choice cannot wedge the app. */
export async function clearActiveBranch(): Promise<void> {
  const store = await cookies();
  store.delete(ACTIVE_BRANCH_COOKIE);
}
