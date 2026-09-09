import { cookies } from "next/headers";

import { SESSION_COOKIE } from "@/lib/session-cookie";

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
}
