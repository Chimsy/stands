import type { Metadata } from "next";

import { LoginForm } from "@/components/login-form";

export const metadata: Metadata = { title: "Sign in" };

export default async function LoginPage(props: PageProps<"/login">) {
  const { next, expired } = await props.searchParams;
  const destination = typeof next === "string" ? next : "/";

  return (
    <div className="flex flex-1 items-center justify-center bg-zinc-50 p-4 dark:bg-black">
      <div className="flex w-full max-w-sm flex-col gap-5 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
        <div className="flex flex-col gap-1">
          <h1 className="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Stand Locator</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">
            {expired ? "Your session has expired. Please sign in again." : "Sign in to browse the site map."}
          </p>
        </div>

        <LoginForm next={destination} />
      </div>
    </div>
  );
}
