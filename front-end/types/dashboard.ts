import type { SaleStatus, SaleType } from "@/types/trading";

/** Every money figure arrives in minor units, so nothing is rounded before the browser formats it. */
export interface BranchPerformance {
  code: string;
  name: string;
  city: string;
  /** Keyed by stand status, plus a `total`. */
  stands: { total: number; available: number; sold: number; "in-progress": number };
  salesCount: number;
  valueCents: number;
  costCents: number;
  collectedCents: number;
  /** Everything ever signed less everything ever received, so it ties to Accounts Receivable. */
  outstandingCents: number;
}

export interface DashboardTotals {
  standsTotal: number;
  standsAvailable: number;
  standsSold: number;
  standsInProgress: number;
  salesCount: number;
  valueCents: number;
  costCents: number;
  grossProfitCents: number;
  collectedCents: number;
  outstandingCents: number;
}

/** One point per month across the whole period, including months that did not trade. */
export interface MonthlyPoint {
  month: string;
  salesCount: number;
  valueCents: number;
  collectedCents: number;
}

export interface SaleMix {
  type: SaleType;
  salesCount: number;
  valueCents: number;
}

export interface AgentPerformance {
  name: string;
  branch: string | null;
  salesCount: number;
  valueCents: number;
}

export interface RecentSale {
  reference: string;
  saleDate: string;
  standNumber: string | null;
  buyerName: string | null;
  branch: string | null;
  type: SaleType;
  status: SaleStatus;
  valueCents: number;
  paidCents: number;
  outstandingCents: number;
}

export interface Dashboard {
  from: string;
  to: string;
  /** A branch code, or null when every branch is consolidated. */
  branch: string | null;
  totals: DashboardTotals;
  branches: BranchPerformance[];
  monthly: MonthlyPoint[];
  mix: SaleMix[];
  topAgents: AgentPerformance[];
  recentSales: RecentSale[];
}
