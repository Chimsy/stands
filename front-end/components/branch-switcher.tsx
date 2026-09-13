"use client";

import { useRef } from "react";
import { usePathname } from "next/navigation";

import { switchBranch } from "@/app/(app)/actions";
import type { AuthenticatedUser } from "@/types/stand";

/**
 * Lets an administrator move between offices without leaving the page they are
 * on. Submitting is what changes the branch, so the select posts on change and
 * the button is only there for a visitor without JavaScript.
 */
export function BranchSwitcher({ user }: { user: AuthenticatedUser }) {
  const form = useRef<HTMLFormElement>(null);
  const pathname = usePathname();

  return (
    <form ref={form} action={switchBranch} className="flex items-center gap-1.5">
      <input type="hidden" name="next" value={pathname} />

      <label htmlFor="branch" className="sr-only">
        Branch
      </label>

      <select
        id="branch"
        name="branch"
        defaultValue={user.branch?.code ?? ""}
        onChange={() => form.current?.requestSubmit()}
        className="rounded-full border border-black/[.08] bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 focus:border-marine-500 focus:ring-2 focus:ring-marine-500/25 focus:outline-none dark:border-white/[.145] dark:bg-zinc-950 dark:text-zinc-300"
      >
        {user.branches.map((branch) => (
          <option key={branch.code} value={branch.code}>
            {branch.name}
          </option>
        ))}
      </select>

      <noscript>
        <button
          type="submit"
          className="rounded-lg border border-black/[.08] px-2 py-1 text-xs font-medium text-zinc-600 dark:border-white/[.145] dark:text-zinc-400"
        >
          Go
        </button>
      </noscript>
    </form>
  );
}
