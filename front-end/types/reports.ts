/**
 * Statements arrive with amounts in minor units, named `...Cents`, so no
 * rounding happens before the browser formats them.
 */
export interface StatementLine {
  code: string;
  name: string;
  amountCents: number;
}

export interface TrialBalance {
  asAt: string;
  branch: string | null;
  rows: { code: string; name: string; type: string; debitCents: number; creditCents: number }[];
  totalDebitCents: number;
  totalCreditCents: number;
  inBalance: boolean;
}

export interface IncomeStatement {
  from: string;
  to: string;
  branch: string | null;
  revenue: StatementLine[];
  expenses: StatementLine[];
  revenueCents: number;
  expensesCents: number;
  netIncomeCents: number;
}

export interface BalanceSheet {
  asAt: string;
  branch: string | null;
  assets: StatementLine[];
  liabilities: StatementLine[];
  equity: StatementLine[];
  assetsCents: number;
  liabilitiesCents: number;
  equityCents: number;
  retainedEarningsCents: number;
  inBalance: boolean;
}

export type AgeingBucket = "notYetDue" | "days1To30" | "days31To60" | "days61To90" | "over90Days";

export interface ReceivablesAgeing {
  asAt: string;
  branch: string | null;
  buckets: Record<AgeingBucket, number>;
  totalCents: number;
  overdueCents: number;
}

export const AGEING_BUCKET_LABEL: Record<AgeingBucket, string> = {
  notYetDue: "Not yet due",
  days1To30: "1 – 30 days",
  days31To60: "31 – 60 days",
  days61To90: "61 – 90 days",
  over90Days: "Over 90 days",
};
