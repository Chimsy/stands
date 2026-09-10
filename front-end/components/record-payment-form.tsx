"use client";

import { useActionState } from "react";
import { useFormStatus } from "react-dom";

import { recordPayment, type FormState } from "@/app/(app)/sales/actions";
import { Field, FormError, Select, SubmitButton, TextInput } from "@/components/field";
import { formatPrice } from "@/lib/format";
import { PAYMENT_METHOD_LABEL, type PaymentMethod } from "@/types/trading";

const INITIAL: FormState = {};

export function RecordPaymentForm({
  saleReference,
  outstanding,
  suggested,
  today,
}: {
  saleReference: string;
  outstanding: number;
  suggested: number;
  today: string;
}) {
  const [state, formAction] = useActionState(recordPayment, INITIAL);
  const fieldError = (field: string) => state.fieldErrors?.[field];

  return (
    <form action={formAction} className="flex flex-col gap-3">
      <input type="hidden" name="saleReference" value={saleReference} />

      <FormError message={state.error} />

      <div className="grid gap-3 sm:grid-cols-2">
        <Field
          label="Amount received"
          error={fieldError("amount")}
          hint={`${formatPrice(outstanding)} still owing`}
        >
          <TextInput
            name="amount"
            type="number"
            min="0.01"
            step="0.01"
            max={outstanding}
            required
            defaultValue={suggested}
          />
        </Field>

        <Field label="Paid by" error={fieldError("method")}>
          <Select name="method" defaultValue="mobile-money">
            {(Object.keys(PAYMENT_METHOD_LABEL) as PaymentMethod[]).map((method) => (
              <option key={method} value={method}>
                {PAYMENT_METHOD_LABEL[method]}
              </option>
            ))}
          </Select>
        </Field>

        <Field label="Date received" error={fieldError("paidOn")}>
          <TextInput name="paidOn" type="date" required defaultValue={today} max={today} />
        </Field>

        <Field
          label="Their reference"
          error={fieldError("externalReference")}
          hint="Bank or mobile-money reference, if quoted"
        >
          <TextInput name="externalReference" autoComplete="off" />
        </Field>
      </div>

      <Submit />
    </form>
  );
}

function Submit() {
  const { pending } = useFormStatus();

  return (
    <div className="flex justify-end">
      <SubmitButton pending={pending}>{pending ? "Receipting…" : "Receipt payment"}</SubmitButton>
    </div>
  );
}
