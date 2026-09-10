export type SaleType = "cash" | "payment-plan";
export type SaleStatus = "outstanding" | "settled";
export type PaymentMethod = "cash" | "bank-transfer" | "mobile-money" | "card";

export interface Branch {
  code: string;
  name: string;
  city: string;
}

export interface Buyer {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  nationalId: string | null;
}

export interface Instalment {
  sequence: number;
  dueDate: string;
  amount: number;
  paid: number;
  outstanding: number;
  isSettled: boolean;
  isOverdue: boolean;
}

export interface Payment {
  receiptNumber: string;
  paidOn: string;
  amount: number;
  method: PaymentMethod;
  externalReference: string | null;
  saleReference?: string;
  standNumber?: string | null;
  buyerName?: string | null;
  branch?: Branch;
}

/** Money is exchanged in major units; the backend holds it in minor units. */
export interface Sale {
  reference: string;
  saleDate: string;
  type: SaleType;
  status: SaleStatus;
  price: number;
  cost: number;
  deposit: number;
  paid: number;
  outstanding: number;
  instalmentCount: number;
  standNumber?: string;
  buyer?: Buyer;
  soldBy?: string | null;
  branch?: Branch;
  instalments?: Instalment[];
  payments?: Payment[];
}

/** Everything printed on a receipt. */
export interface Receipt {
  receiptNumber: string;
  paidOn: string;
  issuedAt: string | null;
  amount: number;
  method: PaymentMethod;
  externalReference: string | null;
  receivedBy: string | null;
  branch: Branch;
  buyer: Buyer;
  sale: {
    reference: string;
    saleDate: string;
    type: SaleType;
    price: number;
    paid: number;
    outstanding: number;
  };
  stand: {
    standNumber: string;
    block: string;
    road: string;
    areaSqm: number;
    township: string;
  };
}

export const SALE_TYPE_LABEL: Record<SaleType, string> = {
  cash: "Cash",
  "payment-plan": "Payment plan",
};

export const PAYMENT_METHOD_LABEL: Record<PaymentMethod, string> = {
  cash: "Cash",
  "bank-transfer": "Bank transfer",
  "mobile-money": "Mobile money",
  card: "Card",
};

export const SALE_STATUS_LABEL: Record<SaleStatus, string> = {
  outstanding: "Outstanding",
  settled: "Settled",
};
