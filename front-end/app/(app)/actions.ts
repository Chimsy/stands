"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";

import { getAuthenticatedUser } from "@/lib/auth";
import { setActiveBranch } from "@/lib/session";

/** Only same-origin paths are accepted, so a crafted form cannot bounce the user elsewhere. */
function safeRedirectPath(value: FormDataEntryValue | null): string {
  const path = typeof value === "string" ? value : "";
  return path.startsWith("/") && !path.startsWith("//") ? path : "/";
}

/**
 * Moves an administrator to another office.
 *
 * The API is the authority on who may work where, so the code is checked
 * against what it said this account can reach rather than trusted from the
 * form; a rejected choice is dropped instead of being stored and breaking every
 * subsequent request.
 */
export async function switchBranch(formData: FormData): Promise<void> {
  const code = String(formData.get("branch") ?? "").toUpperCase();
  const destination = safeRedirectPath(formData.get("next"));
  const user = await getAuthenticatedUser();

  if (!user?.branches.some((branch) => branch.code === code)) {
    redirect(destination);
  }

  await setActiveBranch(code);

  /** Every page is scoped to the office, so none of what is rendered still applies. */
  revalidatePath("/", "layout");
  redirect(destination);
}
