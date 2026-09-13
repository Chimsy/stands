import Link from "next/link";
import type { Metadata } from "next";

import { formatDate, formatPrice } from "@/lib/format";
import { getReceipts } from "@/lib/trading";
import { PAYMENT_METHOD_LABEL } from "@/types/trading";

export const metadata: Metadata = { title: "Receipts" };

export default async function ReceiptsPage(props: PageProps<"/receipts">) {
  const { search, page } = await props.searchParams;
  const term = typeof search === "string" ? search : "";

  const receipts = await getReceipts({
    ...(term ? { search: term } : {}),
    ...(typeof page === "string" ? { page } : {}),
  });

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Receipts</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">
            {receipts.meta.total} issued at your branch
          </p>
        </div>

        <form className="min-w-64">
          <label>
            <span className="sr-only">Search receipts</span>
            <input
              type="search"
              name="search"
              defaultValue={term}
              placeholder="Receipt number, buyer, stand or sale…"
              className="w-full rounded-lg border border-black/[.08] bg-white px-3.5 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-marine-500 focus:ring-2 focus:ring-marine-500/25 focus:outline-none dark:border-white/[.145] dark:bg-zinc-950 dark:text-zinc-50"
            />
          </label>
        </form>
      </header>

      <div className="overflow-x-auto rounded-xl border border-black/[.08] bg-white dark:border-white/[.145] dark:bg-zinc-950">
        <table className="w-full min-w-3xl text-sm">
          <thead className="border-b border-black/[.08] text-left text-xs text-zinc-500 dark:border-white/[.145] dark:text-zinc-400">
            <tr>
              <th className="px-4 py-2.5 font-medium">Receipt</th>
              <th className="px-4 py-2.5 font-medium">Date</th>
              <th className="px-4 py-2.5 font-medium">Buyer</th>
              <th className="px-4 py-2.5 font-medium">Stand</th>
              <th className="px-4 py-2.5 font-medium">Method</th>
              <th className="px-4 py-2.5 text-right font-medium">Amount</th>
            </tr>
          </thead>
          <tbody>
            {receipts.data.map((receipt) => (
              <tr
                key={receipt.receiptNumber}
                className="border-b border-black/[.05] last:border-0 hover:bg-black/[.02] dark:border-white/[.08] dark:hover:bg-white/[.04]"
              >
                <td className="px-4 py-2.5">
                  <Link
                    href={`/receipts/${receipt.receiptNumber}`}
                    className="font-medium text-zinc-900 underline underline-offset-4 dark:text-zinc-50"
                  >
                    {receipt.receiptNumber}
                  </Link>
                </td>
                <td className="px-4 py-2.5 text-zinc-500 dark:text-zinc-400">{formatDate(receipt.paidOn)}</td>
                <td className="px-4 py-2.5 text-zinc-900 dark:text-zinc-50">{receipt.buyerName}</td>
                <td className="px-4 py-2.5 text-zinc-500 dark:text-zinc-400">{receipt.standNumber}</td>
                <td className="px-4 py-2.5 text-zinc-500 dark:text-zinc-400">{PAYMENT_METHOD_LABEL[receipt.method]}</td>
                <td className="px-4 py-2.5 text-right tabular-nums text-zinc-900 dark:text-zinc-50">
                  {formatPrice(receipt.amount)}
                </td>
              </tr>
            ))}
            {receipts.data.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                  {term ? `No receipts match “${term}”.` : "No receipts yet."}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {receipts.meta.last_page > 1 && (
        <nav className="flex items-center justify-between text-xs">
          <PageLink
            href={`/receipts?${new URLSearchParams({ ...(term ? { search: term } : {}), page: String(receipts.meta.current_page - 1) })}`}
            disabled={receipts.meta.current_page <= 1}
          >
            &larr; Previous
          </PageLink>
          <span className="text-zinc-500 dark:text-zinc-400">
            Page {receipts.meta.current_page} of {receipts.meta.last_page}
          </span>
          <PageLink
            href={`/receipts?${new URLSearchParams({ ...(term ? { search: term } : {}), page: String(receipts.meta.current_page + 1) })}`}
            disabled={receipts.meta.current_page >= receipts.meta.last_page}
          >
            Next &rarr;
          </PageLink>
        </nav>
      )}
    </div>
  );
}

function PageLink({ href, disabled, children }: { href: string; disabled: boolean; children: React.ReactNode }) {
  if (disabled) {
    return <span className="text-zinc-300 dark:text-zinc-700">{children}</span>;
  }

  return (
    <Link href={href} className="font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">
      {children}
    </Link>
  );
}
