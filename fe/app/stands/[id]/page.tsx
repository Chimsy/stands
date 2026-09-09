import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";

import { StandLocator } from "@/components/site-map/stand-locator";
import { StatusBadge } from "@/components/status-badge";
import { formatPrice } from "@/lib/format";
import { getSitePlan, getStandById, getStandsNear } from "@/lib/stands";

const LOCATOR_RADIUS = 110;

export async function generateMetadata(props: PageProps<"/stands/[id]">): Promise<Metadata> {
  const { id } = await props.params;
  const stand = await getStandById(id);

  return { title: stand ? `Stand ${stand.standNumber}` : "Stand not found" };
}

export default async function StandProfilePage(props: PageProps<"/stands/[id]">) {
  const { id } = await props.params;
  const [stand, plan] = await Promise.all([getStandById(id), getSitePlan()]);

  if (!stand) {
    notFound();
  }

  const neighbours = await getStandsNear(stand.centroid, LOCATOR_RADIUS);

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
