import type { Metadata } from "next";

import { BrandLogo } from "@/components/brand-logo";
import { LoginForm } from "@/components/login-form";

export const metadata: Metadata = { title: "Sign in" };

export default async function LoginPage(props: PageProps<"/login">) {
  const { next, expired } = await props.searchParams;
  const destination = typeof next === "string" ? next : "/";

  return (
    <div className="flex flex-1 items-center justify-center bg-linear-to-b from-marine-50 via-white to-brand-50 p-4 dark:from-marine-950 dark:via-black dark:to-brand-950">
      <div className="flex w-full max-w-sm flex-col gap-6">
        <BrandLogo className="self-center" />

        <div className="flex w-full flex-col gap-5 overflow-hidden rounded-xl border border-brand-900/[.08] bg-white shadow-sm shadow-marine-900/5 dark:border-white/[.145] dark:bg-zinc-950 dark:shadow-none">
          <div className="brand-rule h-1" />

          <div className="flex flex-col gap-5 px-6 pb-6">
            <div className="flex flex-col gap-1">
              <h1 className="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Stand Locator</h1>
              <p className="text-sm text-zinc-500 dark:text-zinc-400">
                {expired ? "Your session has expired. Please sign in again." : "Sign in to browse the site map."}
              </p>
            </div>

            <LoginForm next={destination} />
          </div>
        </div>
      </div>
    </div>
  );
}
