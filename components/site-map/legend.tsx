import { formatNumber } from "@/lib/format";
import { STAND_STATUSES, STAND_STATUS_LABEL, STAND_STATUS_SWATCH } from "@/lib/stand-status";
import type { Stand } from "@/types/stand";

export function Legend({ stands }: { stands: Stand[] }) {
  const counts = stands.reduce<Record<string, number>>((acc, stand) => {
    acc[stand.status] = (acc[stand.status] ?? 0) + 1;
    return acc;
  }, {});

  return (
    <div className="flex flex-col gap-2 rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-zinc-950">
      <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Legend</h2>
      <ul className="flex flex-col gap-1.5">
        {STAND_STATUSES.map((status) => (
          <li key={status} className="flex items-center justify-between gap-2 text-sm text-zinc-600 dark:text-zinc-400">
            <span className="flex items-center gap-2">
              <span className={`h-2.5 w-2.5 rounded-sm ${STAND_STATUS_SWATCH[status]}`} />
              {STAND_STATUS_LABEL[status]}
            </span>
            <span className="tabular-nums text-zinc-400 dark:text-zinc-500">{formatNumber(counts[status] ?? 0)}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
