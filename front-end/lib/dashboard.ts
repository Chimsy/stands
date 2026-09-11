import { redirect } from "next/navigation";

import { ApiError, apiRequest, type Envelope } from "@/lib/api";
import type { StatementScope } from "@/lib/reports";
import type { Dashboard } from "@/types/dashboard";

/**
 * The head-office view of how the stands are selling. It takes the same scope
 * as a statement - a branch code, or "group" - so the two can be read side by
 * side for the same office over the same dates.
 */
export async function getDashboard(scope: StatementScope = {}): Promise<Dashboard> {
  const query = Object.fromEntries(
    Object.entries(scope).filter(([, value]) => Boolean(value)),
  ) as Record<string, string>;

  try {
    const { data } = await apiRequest<Envelope<Dashboard>>("/dashboard", { query });
    return data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      redirect("/login?expired=1");
    }
    throw error;
  }
}
