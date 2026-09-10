import { redirect } from "next/navigation";

import { ApiError, apiRequest, type Envelope, type Paginated } from "@/lib/api";
import type { Branch, Buyer, Payment, Receipt, Sale } from "@/types/trading";

/**
 * Reads for sales, receipts and buyers. Writes live in the server actions
 * beside the pages that trigger them, so a failed write can report its
 * validation errors back into the form that caused it.
 */
async function read<T>(path: string, query?: Record<string, string>): Promise<T> {
  try {
    return await apiRequest<T>(path, { query });
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      redirect("/login?expired=1");
    }
    throw error;
  }
}

export async function getSales(query: Record<string, string> = {}): Promise<Paginated<Sale>> {
  return read<Paginated<Sale>>("/sales", query);
}

export async function getSale(reference: string): Promise<Sale | undefined> {
  try {
    const { data } = await read<Envelope<Sale>>(`/sales/${encodeURIComponent(reference)}`);
    return data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

export async function getReceipts(query: Record<string, string> = {}): Promise<Paginated<Payment>> {
  return read<Paginated<Payment>>("/receipts", query);
}

export async function getReceipt(receiptNumber: string): Promise<Receipt | undefined> {
  try {
    const { data } = await read<Envelope<Receipt>>(`/receipts/${encodeURIComponent(receiptNumber)}`);
    return data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

export async function getBuyers(search = ""): Promise<Buyer[]> {
  const { data } = await read<Envelope<Buyer[]>>("/buyers", search ? { search } : undefined);
  return data;
}

export async function getBranches(): Promise<Branch[]> {
  const { data } = await read<Envelope<Branch[]>>("/branches");
  return data;
}
