import Link from "next/link";
import type { Metadata } from "next";

import { formatDate, formatPrice } from "@/lib/format";
import { getSales } from "@/lib/trading";
import { SALE_STATUS_LABEL, SALE_TYPE_LABEL, type Sale } from "@/types/trading";

export const metadata: Metadata = { title: "Sales" };

const FILTERS = [
  { key: "", label: "All" },
  { key: "outstanding", label: "Outstanding" },
  { key: "settled", label: "Settled" },
] as const;

export default async function SalesPage(props: PageProps<"/sales">) {
  const { status, page } = await props.searchParams;
  const activeStatus = typeof status === "string" ? status : "";

  const sales = await getSales({
    ...(activeStatus ? { status: activeStatus } : {}),
    ...(typeof page === "string" ? { page } : {}),
  });

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Sales</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">
            {sales.meta.total} agreement{sales.meta.total === 1 ? "" : "s"} booked at your branch
          </p>
        </div>

        <nav className="flex items-center gap-1.5">
          {FILTERS.map((filter) => (
            <Link
              key={filter.key}
              href={filter.key ? `/sales?status=${filter.key}` : "/sales"}
              className={`rounded-full border px-3 py-1.5 text-xs font-medium ${
                activeStatus === filter.key
                  ? "border-transparent bg-marine-600 text-white dark:bg-marine-400 dark:text-marine-950"
                  : "border-black/[.08] text-zinc-600 hover:bg-black/[.04] dark:border-white/[.145] dark:text-zinc-400 dark:hover:bg-white/[.08]"
              }`}
            >
              {filter.label}
            </Link>
          ))}
        </nav>
      </header>

      <div className="overflow-x-auto rounded-xl border border-black/[.08] bg-white dark:border-white/[.145] dark:bg-zinc-950">
        <table className="w-full min-w-3xl text-sm">
          <thead className="border-b border-black/[.08] text-left text-xs text-zinc-500 dark:border-white/[.145] dark:text-zinc-400">
            <tr>
              <th className="px-4 py-2.5 font-medium">Reference</th>
              <th className="px-4 py-2.5 font-medium">Date</th>
              <th className="px-4 py-2.5 font-medium">Stand</th>
              <th className="px-4 py-2.5 font-medium">Buyer</th>
              <th className="px-4 py-2.5 font-medium">Terms</th>
              <th className="px-4 py-2.5 text-right font-medium">Price</th>
              <th className="px-4 py-2.5 text-right font-medium">Outstanding</th>
            </tr>
          </thead>
          <tbody>
            {sales.data.map((sale) => (
              <SaleRow key={sale.reference} sale={sale} />
            ))}
            {sales.data.length === 0 && (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                  No sales yet. Open a stand on the site map to sell it.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <Pagination current={sales.meta.current_page} last={sales.meta.last_page} status={activeStatus} />
    </div>
  );
}

function SaleRow({ sale }: { sale: Sale }) {
  return (
    <tr className="border-b border-black/[.05] last:border-0 hover:bg-black/[.02] dark:border-white/[.08] dark:hover:bg-white/[.04]">
      <td className="px-4 py-2.5">
        <Link href={`/sales/${sale.reference}`} className="font-medium text-zinc-900 underline underline-offset-4 dark:text-zinc-50">
          {sale.reference}
        </Link>
      </td>
      <td className="px-4 py-2.5 text-zinc-500 dark:text-zinc-400">{formatDate(sale.saleDate)}</td>
      <td className="px-4 py-2.5 text-zinc-900 dark:text-zinc-50">{sale.standNumber}</td>
      <td className="px-4 py-2.5 text-zinc-500 dark:text-zinc-400">{sale.buyer?.name}</td>
      <td className="px-4 py-2.5 text-zinc-500 dark:text-zinc-400">
        {SALE_TYPE_LABEL[sale.type]}
        {sale.type === "payment-plan" && ` · ${sale.instalmentCount}m`}
      </td>
      <td className="px-4 py-2.5 text-right tabular-nums text-zinc-900 dark:text-zinc-50">{formatPrice(sale.price)}</td>
      <td className="px-4 py-2.5 text-right tabular-nums">
        {sale.outstanding > 0 ? (
          <span className="text-amber-700 dark:text-amber-500">{formatPrice(sale.outstanding)}</span>
        ) : (
          <span className="text-emerald-700 dark:text-emerald-400">{SALE_STATUS_LABEL.settled}</span>
        )}
      </td>
    </tr>
  );
}

function Pagination({ current, last, status }: { current: number; last: number; status: string }) {
  if (last <= 1) return null;

  const href = (page: number) => `/sales?${new URLSearchParams({ ...(status ? { status } : {}), page: String(page) })}`;

  return (
    <nav className="flex items-center justify-between text-sm">
      <PageLink href={href(current - 1)} disabled={current <= 1}>
        &larr; Previous
      </PageLink>
      <span className="text-xs text-zinc-500 dark:text-zinc-400">
        Page {current} of {last}
      </span>
      <PageLink href={href(current + 1)} disabled={current >= last}>
        Next &rarr;
      </PageLink>
    </nav>
  );
}

function PageLink({ href, disabled, children }: { href: string; disabled: boolean; children: React.ReactNode }) {
  if (disabled) {
    return <span className="text-xs text-zinc-300 dark:text-zinc-700">{children}</span>;
  }

  return (
    <Link href={href} className="text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">
      {children}
    </Link>
  );
}
