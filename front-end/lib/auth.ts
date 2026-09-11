import { ApiError, apiRequest, type Envelope } from "@/lib/api";
import { clearActiveBranch, getSessionToken } from "@/lib/session";
import type { AuthenticatedUser } from "@/types/stand";

interface LoginResponse {
  token: string;
  user: AuthenticatedUser;
}

/** Where a visitor lands when they sign in without having asked for somewhere in particular. */
export const DEFAULT_LANDING_PATH = "/";

/**
 * The page this account opens on.
 *
 * An administrator's job starts with how the branches are selling, so they land
 * on the dashboard; an agent works a single township and lands on its map.
 */
export function landingPathFor(user: AuthenticatedUser): string {
  return user.isAdmin ? "/dashboard" : DEFAULT_LANDING_PATH;
}

export async function requestToken(email: string, password: string, deviceName: string): Promise<LoginResponse> {
  return apiRequest<LoginResponse>("/login", {
    method: "POST",
    body: { email, password, deviceName },
    token: null,
  });
}

/**
 * Revoking the token is best-effort: if the API has already forgotten it the
 * visitor is signed out either way, so a failure here must not block sign-out.
 */
export async function revokeToken(): Promise<void> {
  try {
    await apiRequest<void>("/logout", { method: "POST" });
  } catch (error) {
    if (!(error instanceof ApiError)) {
      throw error;
    }
  }
}

export async function getAuthenticatedUser(): Promise<AuthenticatedUser | null> {
  if (!(await getSessionToken())) {
    return null;
  }

  try {
    const { data } = await apiRequest<Envelope<AuthenticatedUser>>("/user");
    return data;
  } catch (error) {
    if (!(error instanceof ApiError)) {
      throw error;
    }

    if (error.status === 401) {
      return null;
    }

    /**
     * The only 403 this call can draw is a branch the account may not work
     * from - an agent signing in where an administrator left a selection
     * behind. Dropping it recovers the session rather than wedging every page.
     */
    if (error.status === 403) {
      await clearActiveBranch();
      const { data } = await apiRequest<Envelope<AuthenticatedUser>>("/user", { branch: null });
      return data;
    }

    throw error;
  }
}
