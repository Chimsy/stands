import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";

import { BrandLogo } from "@/components/brand-logo";
import { PrintButton } from "@/components/print-button";
import { formatDate, formatPrice } from "@/lib/format";
import { getReceipt } from "@/lib/trading";
import { PAYMENT_METHOD_LABEL, SALE_TYPE_LABEL } from "@/types/trading";

export async function generateMetadata(props: PageProps<"/receipts/[receiptNumber]">): Promise<Metadata> {
  const { receiptNumber } = await props.params;

  return { title: `Receipt ${receiptNumber}` };
}

export default async function ReceiptPage(props: PageProps<"/receipts/[receiptNumber]">) {
  const { receiptNumber } = await props.params;
  const receipt = await getReceipt(receiptNumber);

  if (!receipt) {
    notFound();
  }

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4 sm:p-8 print:max-w-none print:p-0">
      <div className="flex items-center justify-between gap-3 print:hidden">
        <Link
          href={`/sales/${receipt.sale.reference}`}
          className="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50"
        >
          &larr; Back to sale {receipt.sale.reference}
        </Link>
        <PrintButton />
      </div>

      {/*
        The printed sheet. It is deliberately plain and light-only: a receipt is
        a document a buyer keeps, so it must look the same on screen, on paper
        and in a saved PDF.
      */}
      <article className="flex flex-col gap-6 rounded-xl border border-black/[.08] bg-white p-8 text-zinc-900 print:rounded-none print:border-0 print:p-0 dark:border-white/[.145]">
        <header className="flex flex-wrap items-start justify-between gap-4 border-b border-black/10 pb-5">
          <div>
            {/* Letterhead: the same lock-up on screen, on paper and in the PDF. */}
            <BrandLogo tone="fixed" className="mb-3" />
            <h1 className="text-lg font-semibold">{receipt.branch.name}</h1>
            <p className="text-sm text-zinc-500">{receipt.stand.township}</p>
            <p className="text-sm text-zinc-500">{receipt.branch.city}</p>
          </div>
          <div className="text-right">
            <p className="text-xs uppercase tracking-widest text-zinc-400">Receipt</p>
            <p className="text-lg font-semibold tabular-nums">{receipt.receiptNumber}</p>
            <p className="text-sm text-zinc-500">{formatDate(receipt.paidOn)}</p>
          </div>
        </header>

        <section className="grid gap-5 sm:grid-cols-2">
          <div>
            <h2 className="text-xs uppercase tracking-widest text-zinc-400">Received from</h2>
            <p className="font-medium">{receipt.buyer.name}</p>
            {receipt.buyer.nationalId && <p className="text-sm text-zinc-500">ID {receipt.buyer.nationalId}</p>}
            {receipt.buyer.phone && <p className="text-sm text-zinc-500">{receipt.buyer.phone}</p>}
            {receipt.buyer.email && <p className="text-sm text-zinc-500">{receipt.buyer.email}</p>}
          </div>

          <div>
            <h2 className="text-xs uppercase tracking-widest text-zinc-400">In respect of</h2>
            <p className="font-medium">Stand {receipt.stand.standNumber}</p>
            <p className="text-sm text-zinc-500">
              {receipt.stand.block} · {receipt.stand.road}
            </p>
            <p className="text-sm text-zinc-500">{receipt.stand.areaSqm} m²</p>
            <p className="text-sm text-zinc-500">
              Agreement {receipt.sale.reference} · {SALE_TYPE_LABEL[receipt.sale.type]}
            </p>
          </div>
        </section>

        <section className="flex items-center justify-between rounded-lg bg-zinc-50 px-5 py-4 print:bg-transparent print:px-0 print:ring-1 print:ring-black/20">
          <div>
            <p className="text-xs uppercase tracking-widest text-zinc-400">Amount received</p>
            <p className="text-sm text-zinc-500">{PAYMENT_METHOD_LABEL[receipt.method]}</p>
            {receipt.externalReference && <p className="text-sm text-zinc-500">Ref {receipt.externalReference}</p>}
          </div>
          <p className="text-2xl font-semibold tabular-nums">{formatPrice(receipt.amount)}</p>
        </section>

        <section>
          <h2 className="text-xs uppercase tracking-widest text-zinc-400">Account position</h2>
          <dl className="mt-2 grid grid-cols-3 gap-4 text-sm">
            <Figure label="Purchase price" value={formatPrice(receipt.sale.price)} />
            <Figure label="Paid to date" value={formatPrice(receipt.sale.paid)} />
            <Figure label="Balance owing" value={formatPrice(receipt.sale.outstanding)} />
          </dl>
          <p className="mt-3 text-xs text-zinc-400">
            The balance shown is the position as at the date this receipt was printed.
          </p>
        </section>

        <footer className="flex flex-wrap items-end justify-between gap-4 border-t border-black/10 pt-5 text-sm text-zinc-500">
          <div>
            <p>Received by {receipt.receivedBy ?? "—"}</p>
            <p className="text-xs text-zinc-400">
              {receipt.sale.outstanding === 0
                ? "This account is settled in full."
                : "This receipt is not proof of transfer of title."}
            </p>
          </div>
          <p className="text-xs text-zinc-400">Issued electronically; valid without signature.</p>
        </footer>
      </article>
    </div>
  );
}

function Figure({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-zinc-500">{label}</dt>
      <dd className="font-medium tabular-nums">{value}</dd>
    </div>
  );
}
