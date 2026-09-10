"use client";

import { useActionState, useState } from "react";
import { useFormStatus } from "react-dom";

import { registerBuyer, type FormState } from "@/app/(app)/sales/actions";
import { Field, FormError, SubmitButton, TextInput } from "@/components/field";

const INITIAL: FormState = {};

/** Collapsed by default: most sales are to a buyer who is already on file. */
export function RegisterBuyerForm() {
  const [state, formAction] = useActionState(registerBuyer, INITIAL);
  const [open, setOpen] = useState(false);
  const fieldError = (field: string) => state.fieldErrors?.[field];

  if (!open) {
    return (
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="self-start text-sm font-medium text-zinc-600 underline underline-offset-4 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50"
      >
        Register a new buyer
      </button>
    );
  }

  return (
    <form action={formAction} className="flex flex-col gap-3 rounded-xl border border-black/[.08] p-4 dark:border-white/[.145]">
      <h3 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">New buyer</h3>

      <FormError message={state.error} />

      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Full name" error={fieldError("name")}>
          <TextInput name="name" required autoComplete="off" />
        </Field>
        <Field label="Identity number" error={fieldError("nationalId")}>
          <TextInput name="nationalId" autoComplete="off" />
        </Field>
        <Field label="Phone" error={fieldError("phone")}>
          <TextInput name="phone" type="tel" autoComplete="off" />
        </Field>
        <Field label="Email" error={fieldError("email")}>
          <TextInput name="email" type="email" autoComplete="off" />
        </Field>
      </div>

      <Actions onCancel={() => setOpen(false)} />
    </form>
  );
}

function Actions({ onCancel }: { onCancel: () => void }) {
  const { pending } = useFormStatus();

  return (
    <div className="flex items-center gap-2">
      <SubmitButton pending={pending}>{pending ? "Saving…" : "Save buyer"}</SubmitButton>
      <button
        type="button"
        onClick={onCancel}
        className="rounded-lg border border-black/[.08] px-3 py-2 text-sm text-zinc-700 hover:bg-black/[.04] dark:border-white/[.145] dark:text-zinc-300 dark:hover:bg-white/[.08]"
      >
        Cancel
      </button>
    </div>
  );
}
