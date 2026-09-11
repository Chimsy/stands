"use server";

import { headers } from "next/headers";
import { redirect } from "next/navigation";

import { ApiError } from "@/lib/api";
import { DEFAULT_LANDING_PATH, landingPathFor, requestToken, revokeToken } from "@/lib/auth";
import { endSession, startSession } from "@/lib/session";

export interface LoginState {
  error?: string;
}

/** Only same-origin paths are accepted so a crafted link cannot bounce a signed-in user elsewhere. */
function safeRedirectPath(value: FormDataEntryValue | null): string {
  const path = typeof value === "string" ? value : "";
  return path.startsWith("/") && !path.startsWith("//") ? path : DEFAULT_LANDING_PATH;
}

export async function login(_previous: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get("email") ?? "");
  const password = String(formData.get("password") ?? "");
  const requested = safeRedirectPath(formData.get("next"));
  const deviceName = (await headers()).get("user-agent") ?? "Unknown device";

  let destination = requested;

  try {
    const { token, user } = await requestToken(email, password, deviceName);
    await startSession(token);

    /**
     * Someone sent here from a page they asked for gets taken back to it; only
     * a plain sign-in falls through to whatever this account's home is.
     */
    if (requested === DEFAULT_LANDING_PATH) {
      destination = landingPathFor(user);
    }
  } catch (error) {
    if (error instanceof ApiError) {
      return {
        error: error.fieldError("email") ?? error.fieldError("password") ?? error.message,
      };
    }

    return { error: "Could not reach the server. Please try again." };
  }

  redirect(destination);
}

export async function logout(): Promise<void> {
  await revokeToken();
  await endSession();

  redirect("/login");
}
