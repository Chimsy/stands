import { redirect } from "next/navigation";

import { ApiError, apiRequest, type Envelope } from "@/lib/api";
import type { BalanceSheet, IncomeStatement, ReceivablesAgeing, TrialBalance } from "@/types/reports";

/**
 * The statements are read straight from the ledger on every request, so what a
 * page shows is the position at the moment it was rendered.
 */
async function statement<T>(path: string, query: Record<string, string>): Promise<T> {
  try {
    const { data } = await apiRequest<Envelope<T>>(`/reports/${path}`, { query });
    return data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      redirect("/login?expired=1");
    }
    throw error;
  }
}

/** `branch` is a branch code, or "group" to consolidate every branch. */
export interface StatementScope {
  branch?: string;
  from?: string;
  to?: string;
}

const toQuery = (scope: StatementScope): Record<string, string> =>
  Object.fromEntries(Object.entries(scope).filter(([, value]) => Boolean(value))) as Record<string, string>;

export async function getTrialBalance(scope: StatementScope): Promise<TrialBalance> {
  return statement<TrialBalance>("trial-balance", toQuery(scope));
}

export async function getIncomeStatement(scope: StatementScope): Promise<IncomeStatement> {
  return statement<IncomeStatement>("income-statement", toQuery(scope));
}

export async function getBalanceSheet(scope: StatementScope): Promise<BalanceSheet> {
  return statement<BalanceSheet>("balance-sheet", toQuery(scope));
}

export async function getReceivablesAgeing(scope: StatementScope): Promise<ReceivablesAgeing> {
  return statement<ReceivablesAgeing>("receivables-ageing", toQuery(scope));
}
