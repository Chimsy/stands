"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";

import { ApiError, apiRequest, type Envelope } from "@/lib/api";
import type { Buyer, Receipt, Sale } from "@/types/trading";

export interface FormState {
  error?: string;
  fieldErrors?: Record<string, string>;
}

/**
 * Turns an API rejection into something a form can show. Validation failures
 * come back per field; a refusal from the accounting rules is a single message
 * about the operation as a whole.
 */
function toFormState(error: unknown): FormState {
  if (!(error instanceof ApiError)) {
    return { error: "Could not reach the server. Please try again." };
  }

  const fieldErrors = Object.fromEntries(
    Object.entries(error.errors).map(([field, messages]) => [field, messages[0]]),
  );

  return Object.keys(fieldErrors).length > 0 ? { fieldErrors } : { error: error.message };
}

const number = (formData: FormData, field: string): number => Number(formData.get(field) ?? 0);

const text = (formData: FormData, field: string): string => String(formData.get(field) ?? "").trim();

export async function registerBuyer(_previous: FormState, formData: FormData): Promise<FormState & { buyer?: Buyer }> {
  try {
    const { data } = await apiRequest<Envelope<Buyer>>("/buyers", {
      method: "POST",
      body: {
        name: text(formData, "name"),
        email: text(formData, "email") || null,
        phone: text(formData, "phone") || null,
        nationalId: text(formData, "nationalId") || null,
      },
    });

    revalidatePath("/sales");

    return { buyer: data };
  } catch (error) {
    return toFormState(error);
  }
}

export async function sellStand(_previous: FormState, formData: FormData): Promise<FormState> {
  const isPlan = text(formData, "type") === "payment-plan";
  let reference: string;

  try {
    const { data } = await apiRequest<Envelope<Sale>>("/sales", {
      method: "POST",
      body: {
        standNumber: text(formData, "standNumber"),
        buyerId: number(formData, "buyerId"),
        type: text(formData, "type"),
        price: number(formData, "price"),
        saleDate: text(formData, "saleDate"),
        method: text(formData, "method"),
        ...(isPlan ? { deposit: number(formData, "deposit"), instalmentCount: number(formData, "instalmentCount") } : {}),
      },
    });

    reference = data.reference;
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath("/");
  revalidatePath("/sales");

  redirect(`/sales/${reference}`);
}

export async function recordPayment(_previous: FormState, formData: FormData): Promise<FormState> {
  const saleReference = text(formData, "saleReference");
  let receiptNumber: string;

  try {
    const { data } = await apiRequest<Envelope<Receipt>>(`/sales/${encodeURIComponent(saleReference)}/payments`, {
      method: "POST",
      body: {
        amount: number(formData, "amount"),
        method: text(formData, "method"),
        paidOn: text(formData, "paidOn"),
        externalReference: text(formData, "externalReference") || null,
      },
    });

    receiptNumber = data.receiptNumber;
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath(`/sales/${saleReference}`);
  revalidatePath("/receipts");

  /** Straight to the receipt, which is what the buyer is waiting for. */
  redirect(`/receipts/${receiptNumber}`);
}
