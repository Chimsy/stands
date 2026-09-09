import Link from "next/link";
import { redirect } from "next/navigation";

import { SignOutButton } from "@/components/sign-out-button";
import { getAuthenticatedUser } from "@/lib/auth";

/**
 * Shell for every signed-in page. Middleware already turns visitors without a
 * cookie away; this re-checks with the API so a revoked or expired token cannot
 * render the app either.
 */
export default async function AuthenticatedLayout({ children }: LayoutProps<"/">) {
  const user = await getAuthenticatedUser();

  if (!user) {
    redirect("/login?expired=1");
  }

  return (
    <div className="flex flex-1 flex-col bg-zinc-50 dark:bg-black">
      <header className="flex items-center justify-between gap-4 border-b border-black/[.08] bg-white px-4 py-2.5 sm:px-6 dark:border-white/[.145] dark:bg-zinc-950">
        <Link href="/" className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
          Stand Locator
        </Link>

        <div className="flex items-center gap-3">
          <span className="hidden text-xs text-zinc-500 sm:inline dark:text-zinc-400">{user.email}</span>
          <SignOutButton />
        </div>
      </header>

      <main className="flex flex-1 flex-col">{children}</main>
    </div>
  );
}
