import { share } from "@/lib/chart-scale";
import { formatCents, formatNumber } from "@/lib/format";

export interface ValueBar {
  key: string;
  label: string;
  note?: string;
  cents: number;
  count: number;
}

/**
 * A ranked list of amounts - branches, or agents.
 *
 * One series, so there is nothing to tell apart by colour and no legend to
 * read: the row's own label names it and the amount is written at the bar's
 * tip. Bars are proportional to the largest in the list, not to a fixed axis.
 */
export function ValueBars({ rows, empty }: { rows: ValueBar[]; empty: string }) {
  if (rows.length === 0) {
    return <p className="py-1 text-sm text-zinc-400">{empty}</p>;
  }

  const largest = Math.max(...rows.map((row) => row.cents));

  return (
    <ul className="flex flex-col gap-3">
      {rows.map((row) => (
        <li key={row.key} className="flex flex-col gap-1">
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="truncate font-medium text-zinc-900 dark:text-zinc-50">{row.label}</span>
            <span className="shrink-0 tabular-nums text-zinc-900 dark:text-zinc-50">{formatCents(row.cents)}</span>
          </div>

          <div className="h-2 w-full rounded-full bg-black/[.04] dark:bg-white/[.08]">
            <div
              className="h-full rounded-full"
              style={{ width: `${share(row.cents, largest) * 100}%`, background: "var(--viz-signed)" }}
            />
          </div>

          <p className="text-xs text-zinc-500 dark:text-zinc-400">
            {formatNumber(row.count)} {row.count === 1 ? "sale" : "sales"}
            {row.note && <span className="text-zinc-400"> · {row.note}</span>}
          </p>
        </li>
      ))}
    </ul>
  );
}
