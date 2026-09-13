import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";

import { RegisterBuyerForm } from "@/components/register-buyer-form";
import { SellStandForm } from "@/components/sell-stand-form";
import { StandLocator } from "@/components/site-map/stand-locator";
import { StatusBadge } from "@/components/status-badge";
import { formatPrice } from "@/lib/format";
import { getSitePlan, getStandByNumber, getStandsNear } from "@/lib/stands";
import { getBuyers, getSales } from "@/lib/trading";
import { businessToday } from "@/lib/business-date";

const LOCATOR_RADIUS = 110;

export async function generateMetadata(props: PageProps<"/stands/[standNumber]">): Promise<Metadata> {
  const { standNumber } = await props.params;
  const stand = await getStandByNumber(standNumber);

  return { title: stand ? `Stand ${stand.standNumber}` : "Stand not found" };
}

export default async function StandProfilePage(props: PageProps<"/stands/[standNumber]">) {
  const { standNumber } = await props.params;
  const [stand, plan] = await Promise.all([getStandByNumber(standNumber), getSitePlan()]);

  if (!stand) {
    notFound();
  }

  const neighbours = await getStandsNear(stand.centroid, LOCATOR_RADIUS);
  const isAvailable = stand.status === "available";

  /** An unavailable stand has a sale behind it; find it so the page can link there. */
  const [buyers, existingSale] = await Promise.all([
    isAvailable ? getBuyers() : Promise.resolve([]),
    isAvailable ? Promise.resolve(undefined) : findSaleForStand(stand.standNumber),
  ]);

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 sm:p-8">
      <Link href="/" className="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">
        &larr; Back to site map
      </Link>

      <div className="flex flex-col gap-4 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Stand {stand.standNumber}</h1>
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
              {stand.road} · {stand.block} · {plan.name}
            </p>
          </div>
          <StatusBadge status={stand.status} />
        </div>

        <dl className="grid grid-cols-2 gap-4 border-t border-black/[.08] pt-4 text-sm sm:grid-cols-4 dark:border-white/[.145]">
          <Field label="Area" value={`${stand.areaSqm} m²`} />
          <Field label={stand.status === "sold" ? "Sold price" : "Asking price"} value={formatPrice(stand.price)} />
          <Field label="Frontage road" value={stand.road} />
          <Field label="Last updated" value={stand.updatedAt} />
        </dl>
      </div>

      {isAvailable ? (
        <section className="flex flex-col gap-4 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-zinc-950">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Sell this stand</h2>
            <RegisterBuyerForm />
          </div>

          <SellStandForm standNumber={stand.standNumber} listPrice={stand.price} buyers={buyers} today={businessToday()} />
        </section>
      ) : (
        <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-black/[.08] bg-white p-6 text-sm dark:border-white/[.145] dark:bg-zinc-950">
          <p className="text-zinc-500 dark:text-zinc-400">
            {existingSale
              ? `Sold on ${existingSale.saleDate}. ${formatPrice(existingSale.outstanding)} still owing.`
              : "This stand is not available."}
          </p>
          {existingSale && (
            <Link
              href={`/sales/${existingSale.reference}`}
              className="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 dark:bg-brand-400 dark:text-brand-950 dark:hover:bg-brand-300"
            >
              Open sale {existingSale.reference}
            </Link>
          )}
        </section>
      )}

      <div className="flex flex-col gap-2">
        <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Location on the approved plan</h2>
        <StandLocator plan={plan} stand={stand} neighbours={neighbours} radius={LOCATOR_RADIUS} />
      </div>
    </div>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-zinc-500 dark:text-zinc-400">{label}</dt>
      <dd className="font-medium text-zinc-900 dark:text-zinc-50">{value}</dd>
    </div>
  );
}

async function findSaleForStand(standNumber: string) {
  const { data } = await getSales({ standNumber });

  return data[0];
}
