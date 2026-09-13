import Link from "next/link";
import { redirect } from "next/navigation";

import { BrandLogo } from "@/components/brand-logo";
import { BranchSwitcher } from "@/components/branch-switcher";
import { SignOutButton } from "@/components/sign-out-button";
import { getAuthenticatedUser } from "@/lib/auth";

const NAV = [
  { href: "/", label: "Site map" },
  { href: "/sales", label: "Sales" },
  { href: "/receipts", label: "Receipts" },
  { href: "/statements", label: "Statements" },
] as const;

/** Head office only: it reports across every branch at once. */
const ADMIN_NAV = [{ href: "/dashboard", label: "Dashboard" }] as const;

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
    <div className="flex flex-1 flex-col bg-brand-50/40 dark:bg-black">
      <header className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-brand-900/[.08] bg-white px-4 py-2.5 sm:px-6 dark:border-white/[.145] dark:bg-zinc-950 print:hidden">
        <div className="flex items-center gap-5">
          <Link href="/" className="rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marine-600">
            <BrandLogo compact />
            <span className="sr-only">Beyond Reality Stand Locator</span>
          </Link>

          <nav className="flex items-center gap-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">
            {[...NAV, ...(user.isAdmin ? ADMIN_NAV : [])].map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="hover:text-brand-700 dark:hover:text-brand-400"
              >
                {item.label}
              </Link>
            ))}
          </nav>
        </div>

        <div className="flex items-center gap-3">
          {/* An agent has one office and cannot change it, so they are told which rather than asked. */}
          {user.branches.length > 1 ? (
            <BranchSwitcher user={user} />
          ) : (
            user.branch && (
              <span
                className="rounded-full bg-marine-50 px-2.5 py-1 text-xs font-medium text-marine-800 ring-1 ring-marine-600/15 ring-inset dark:bg-marine-950 dark:text-marine-200 dark:ring-marine-400/25"
                title={`Everything you see is recorded against ${user.branch.name}`}
              >
                {user.branch.name}
              </span>
            )
          )}
          <span className="hidden text-xs text-zinc-500 sm:inline dark:text-zinc-400">{user.email}</span>
          <SignOutButton />
        </div>
      </header>

      {/* The logo's two inks, carried across the page as a single hairline. */}
      <div className="brand-rule h-0.5 shrink-0 print:hidden" />

      <main className="flex flex-1 flex-col">{children}</main>
    </div>
  );
}
