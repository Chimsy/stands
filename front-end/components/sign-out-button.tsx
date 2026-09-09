"use client";

import { useFormStatus } from "react-dom";

import { logout } from "@/app/login/actions";

export function SignOutButton() {
  return (
    <form action={logout}>
      <SubmitButton />
    </form>
  );
}

function SubmitButton() {
  const { pending } = useFormStatus();

  return (
    <button
      type="submit"
      disabled={pending}
      className="rounded-lg border border-black/[.08] px-2.5 py-1.5 text-xs font-medium text-zinc-600 hover:bg-black/[.04] disabled:opacity-60 dark:border-white/[.145] dark:text-zinc-400 dark:hover:bg-white/[.08]"
    >
      {pending ? "Signing out…" : "Sign out"}
    </button>
  );
}
