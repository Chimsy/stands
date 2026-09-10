import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";

import { RecordPaymentForm } from "@/components/record-payment-form";
import { businessToday } from "@/lib/business-date";
import { formatDate, formatPrice } from "@/lib/format";
import { getSale } from "@/lib/trading";
import { PAYMENT_METHOD_LABEL, SALE_TYPE_LABEL, type Instalment, type Payment, type Sale } from "@/types/trading";

export async function generateMetadata(props: PageProps<"/sales/[reference]">): Promise<Metadata> {
  const { reference } = await props.params;

  return { title: `Sale ${reference}` };
}

export default async function SalePage(props: PageProps<"/sales/[reference]">) {
  const { reference } = await props.params;
  const sale = await getSale(reference);

  if (!sale) {
    notFound();
  }

  const nextDue = sale.instalments?.find((instalment) => !instalment.isSettled);
  const today = businessToday();

  return (
    <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 sm:p-8">
      <Link href="/sales" className="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">
        &larr; Back to sales
      </Link>

      <section className="flex flex-col gap-4 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{sale.reference}</h1>
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
              Stand{" "}
              <Link href={`/stands/${sale.standNumber}`} className="underline underline-offset-4">
                {sale.standNumber}
              </Link>{" "}
              · {sale.buyer?.name} · {SALE_TYPE_LABEL[sale.type]} · {sale.branch?.name}
            </p>
          </div>
          <SettlementBadge sale={sale} />
        </div>

        <dl className="grid grid-cols-2 gap-4 border-t border-black/[.08] pt-4 text-sm sm:grid-cols-4 dark:border-white/[.145]">
          <Figure label="Price" value={formatPrice(sale.price)} />
          <Figure label="Received" value={formatPrice(sale.paid)} />
          <Figure label="Outstanding" value={formatPrice(sale.outstanding)} />
          <Figure label="Date of sale" value={formatDate(sale.saleDate)} />
        </dl>

        {sale.soldBy && <p className="text-xs text-zinc-400">Sold by {sale.soldBy}</p>}
      </section>

      {sale.outstanding > 0 && (
        <section className="flex flex-col gap-4 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
          <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Receipt a payment</h2>
          <RecordPaymentForm
            saleReference={sale.reference}
            outstanding={sale.outstanding}
            suggested={nextDue?.outstanding ?? sale.outstanding}
            today={today}
          />
        </section>
      )}

      {sale.instalments && sale.instalments.length > 0 && (
        <Schedule instalments={sale.instalments} deposit={sale.deposit} />
      )}

      <Receipts payments={sale.payments ?? []} />
    </div>
  );
}

function SettlementBadge({ sale }: { sale: Sale }) {
  const settled = sale.outstanding === 0;

  return (
    <span
      className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${
        settled
          ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
          : "bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-500"
      }`}
    >
      {settled ? "Settled" : `${formatPrice(sale.outstanding)} owing`}
    </span>
  );
}

function Schedule({ instalments, deposit }: { instalments: Instalment[]; deposit: number }) {
  return (
    <section className="flex flex-col gap-3 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Repayment schedule</h2>
        <p className="text-xs text-zinc-500 dark:text-zinc-400">
          {formatPrice(deposit)} deposit, then {instalments.length} monthly instalments
        </p>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-lg text-sm">
          <thead className="border-b border-black/[.08] text-left text-xs text-zinc-500 dark:border-white/[.145] dark:text-zinc-400">
            <tr>
              <th className="py-2 font-medium">#</th>
              <th className="py-2 font-medium">Due</th>
              <th className="py-2 text-right font-medium">Amount</th>
              <th className="py-2 text-right font-medium">Paid</th>
              <th className="py-2 text-right font-medium">Status</th>
            </tr>
          </thead>
          <tbody>
            {instalments.map((instalment) => (
              <tr key={instalment.sequence} className="border-b border-black/[.05] last:border-0 dark:border-white/[.08]">
                <td className="py-2 text-zinc-400">{instalment.sequence}</td>
                <td className="py-2 text-zinc-600 dark:text-zinc-400">{formatDate(instalment.dueDate)}</td>
                <td className="py-2 text-right tabular-nums text-zinc-900 dark:text-zinc-50">{formatPrice(instalment.amount)}</td>
                <td className="py-2 text-right tabular-nums text-zinc-500 dark:text-zinc-400">{formatPrice(instalment.paid)}</td>
                <td className="py-2 text-right text-xs font-medium">
                  {instalment.isSettled ? (
                    <span className="text-emerald-700 dark:text-emerald-400">Paid</span>
                  ) : instalment.isOverdue ? (
                    <span className="text-rose-700 dark:text-rose-400">Overdue</span>
                  ) : (
                    <span className="text-zinc-400">Due</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function Receipts({ payments }: { payments: Payment[] }) {
  return (
    <section className="flex flex-col gap-3 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
      <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Receipts issued</h2>

      {payments.length === 0 ? (
        <p className="text-sm text-zinc-500 dark:text-zinc-400">Nothing received yet.</p>
      ) : (
        <ul className="flex flex-col divide-y divide-black/[.05] text-sm dark:divide-white/[.08]">
          {[...payments]
            .sort((a, b) => b.receiptNumber.localeCompare(a.receiptNumber))
            .map((payment) => (
              <li key={payment.receiptNumber} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                <div>
                  <Link
                    href={`/receipts/${payment.receiptNumber}`}
                    className="font-medium text-zinc-900 underline underline-offset-4 dark:text-zinc-50"
                  >
                    {payment.receiptNumber}
                  </Link>
                  <span className="ml-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {formatDate(payment.paidOn)} · {PAYMENT_METHOD_LABEL[payment.method]}
                    {payment.externalReference ? ` · ${payment.externalReference}` : ""}
                  </span>
                </div>
                <span className="tabular-nums text-zinc-900 dark:text-zinc-50">{formatPrice(payment.amount)}</span>
              </li>
            ))}
        </ul>
      )}
    </section>
  );
}

function Figure({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-zinc-500 dark:text-zinc-400">{label}</dt>
      <dd className="font-medium tabular-nums text-zinc-900 dark:text-zinc-50">{value}</dd>
    </div>
  );
}
