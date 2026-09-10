"use client";

import { useActionState, useState } from "react";
import { useFormStatus } from "react-dom";

import { sellStand, type FormState } from "@/app/(app)/sales/actions";
import { Field, FormError, Select, SubmitButton, TextInput } from "@/components/field";
import { formatPrice } from "@/lib/format";
import { PAYMENT_METHOD_LABEL, type Buyer, type PaymentMethod, type SaleType } from "@/types/trading";

const INITIAL: FormState = {};

const INSTALMENT_TERMS = [6, 12, 18, 24, 36, 48];

export function SellStandForm({
  standNumber,
  listPrice,
  buyers,
  today,
}: {
  standNumber: string;
  listPrice: number;
  buyers: Buyer[];
  today: string;
}) {
  const [state, formAction] = useActionState(sellStand, INITIAL);
  const [type, setType] = useState<SaleType>("cash");
  const [price, setPrice] = useState(listPrice);
  const [deposit, setDeposit] = useState(Math.round(listPrice * 0.2));
  const [term, setTerm] = useState(12);

  const balance = Math.max(0, price - deposit);
  const perInstalment = type === "payment-plan" && term > 0 ? balance / term : 0;
  const fieldError = (field: string) => state.fieldErrors?.[field];

  return (
    <form action={formAction} className="flex flex-col gap-4">
      <input type="hidden" name="standNumber" value={standNumber} />

      <FormError message={state.error} />

      {buyers.length === 0 ? (
        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-400">
          Register a buyer before selling a stand.
        </p>
      ) : (
        <Field label="Buyer" error={fieldError("buyerId")}>
          <Select name="buyerId" required defaultValue="">
            <option value="" disabled>
              Choose a buyer…
            </option>
            {buyers.map((buyer) => (
              <option key={buyer.id} value={buyer.id}>
                {buyer.name}
                {buyer.nationalId ? ` · ${buyer.nationalId}` : ""}
              </option>
            ))}
          </Select>
        </Field>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Terms" error={fieldError("type")}>
          <Select name="type" value={type} onChange={(event) => setType(event.target.value as SaleType)}>
            <option value="cash">Cash — settled today</option>
            <option value="payment-plan">Payment plan — deposit and instalments</option>
          </Select>
        </Field>

        <Field label="Selling price" error={fieldError("price")} hint={`List price ${formatPrice(listPrice)}`}>
          <TextInput
            name="price"
            type="number"
            min="1"
            step="0.01"
            required
            value={price}
            onChange={(event) => setPrice(Number(event.target.value))}
          />
        </Field>
      </div>

      {type === "payment-plan" && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Deposit" error={fieldError("deposit")}>
            <TextInput
              name="deposit"
              type="number"
              min="0"
              step="0.01"
              required
              value={deposit}
              onChange={(event) => setDeposit(Number(event.target.value))}
            />
          </Field>

          <Field
            label="Instalments"
            error={fieldError("instalmentCount")}
            hint={perInstalment > 0 ? `${formatPrice(perInstalment)} a month for ${term} months` : undefined}
          >
            <Select name="instalmentCount" value={term} onChange={(event) => setTerm(Number(event.target.value))}>
              {INSTALMENT_TERMS.map((months) => (
                <option key={months} value={months}>
                  {months} months
                </option>
              ))}
            </Select>
          </Field>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Date of sale" error={fieldError("saleDate")}>
          <TextInput name="saleDate" type="date" required defaultValue={today} max={today} />
        </Field>

        <Field
          label={type === "cash" ? "Paid by" : "Deposit paid by"}
          error={fieldError("method")}
        >
          <Select name="method" defaultValue="bank-transfer">
            {(Object.keys(PAYMENT_METHOD_LABEL) as PaymentMethod[]).map((method) => (
              <option key={method} value={method}>
                {PAYMENT_METHOD_LABEL[method]}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      <Submit type={type} balance={balance} price={price} />
    </form>
  );
}

function Submit({ type, balance, price }: { type: SaleType; balance: number; price: number }) {
  const { pending } = useFormStatus();

  return (
    <div className="flex items-center justify-between gap-3 border-t border-black/[.08] pt-4 dark:border-white/[.145]">
      <p className="text-xs text-zinc-500 dark:text-zinc-400">
        {type === "cash"
          ? `${formatPrice(price)} receipted today.`
          : `${formatPrice(price - balance)} today, ${formatPrice(balance)} owing.`}
      </p>
      <SubmitButton pending={pending}>{pending ? "Recording…" : "Record sale"}</SubmitButton>
    </div>
  );
}
