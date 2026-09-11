import { share } from "@/lib/chart-scale";
import { formatCents, formatNumber } from "@/lib/format";
import { SALE_TYPE_LABEL } from "@/types/trading";
import type { SaleMix as SaleMixRow } from "@/types/dashboard";

const FILL: Record<string, string> = {
  cash: "var(--viz-signed)",
  "payment-plan": "var(--viz-plan)",
};

/**
 * How much of the period's value settled on the day against how much arrives as
 * instalments - the difference between revenue booked and cash in hand.
 *
 * Each segment is labelled outright, so the reader never has to match a colour
 * to the legend to get the number.
 */
export function SaleMix({ mix }: { mix: SaleMixRow[] }) {
  const total = mix.reduce((sum, row) => sum + row.valueCents, 0);

  if (total === 0) {
    return <p className="py-1 text-sm text-zinc-400">Nothing was signed in this period.</p>;
  }

  return (
    <figure className="m-0 flex flex-col gap-3">
      {/* A 2px gap in the surface colour is what separates the segments - never a border. */}
      <div className="flex h-3 w-full gap-0.5">
        {mix
          .filter((row) => row.valueCents > 0)
          .map((row) => (
            <div
              key={row.type}
              className="h-full rounded-sm"
              style={{ width: `${share(row.valueCents, total) * 100}%`, background: FILL[row.type] }}
            />
          ))}
      </div>

      <ul className="flex flex-col gap-2">
        {mix.map((row) => (
          <li key={row.type} className="flex items-baseline justify-between gap-3 text-sm">
            <span className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
              <span className="h-2.5 w-2.5 rounded-sm" style={{ background: FILL[row.type] }} />
              {SALE_TYPE_LABEL[row.type]}
              <span className="text-xs text-zinc-400">
                {formatNumber(row.salesCount)} {row.salesCount === 1 ? "sale" : "sales"}
              </span>
            </span>
            <span className="tabular-nums text-zinc-900 dark:text-zinc-50">
              {formatCents(row.valueCents)}
              <span className="ml-1.5 text-zinc-400">{Math.round(share(row.valueCents, total) * 100)}%</span>
            </span>
          </li>
        ))}
      </ul>
    </figure>
  );
}
