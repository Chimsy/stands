import { share } from "@/lib/chart-scale";
import { formatNumber } from "@/lib/format";
import { STAND_STATUS_LABEL } from "@/lib/stand-status";
import type { BranchPerformance } from "@/types/dashboard";

/**
 * How much of each estate has gone.
 *
 * Drawn as a meter in one hue rather than a three-colour stack: taken against
 * remaining is a single proportion, and the three counts beside it say how the
 * taken part splits without asking anyone to tell three similar colours apart.
 */
export function TakeUp({ branches }: { branches: BranchPerformance[] }) {
  return (
    <ul className="flex flex-col gap-4">
      {branches.map((branch) => {
        const taken = branch.stands.sold + branch.stands["in-progress"];
        const proportion = share(taken, branch.stands.total);

        return (
          <li key={branch.code} className="flex flex-col gap-1.5">
            <div className="flex items-baseline justify-between gap-3 text-sm">
              <span className="font-medium text-zinc-900 dark:text-zinc-50">{branch.name}</span>
              <span className="tabular-nums text-zinc-600 dark:text-zinc-400">
                {formatNumber(taken)} of {formatNumber(branch.stands.total)} taken
                <span className="ml-1.5 text-zinc-400">{Math.round(proportion * 100)}%</span>
              </span>
            </div>

            <div
              className="h-2.5 w-full overflow-hidden rounded-full"
              style={{ background: "var(--viz-track)" }}
              role="img"
              aria-label={`${Math.round(proportion * 100)} percent of ${branch.name} taken`}
            >
              <div
                className="h-full rounded-full"
                style={{ width: `${proportion * 100}%`, background: "var(--viz-signed)" }}
              />
            </div>

            <p className="flex flex-wrap gap-x-4 text-xs text-zinc-500 dark:text-zinc-400">
              <span>
                {STAND_STATUS_LABEL.sold} <span className="tabular-nums">{formatNumber(branch.stands.sold)}</span>
              </span>
              <span>
                {STAND_STATUS_LABEL["in-progress"]}{" "}
                <span className="tabular-nums">{formatNumber(branch.stands["in-progress"])}</span>
              </span>
              <span>
                {STAND_STATUS_LABEL.available}{" "}
                <span className="tabular-nums">{formatNumber(branch.stands.available)}</span>
              </span>
            </p>
          </li>
        );
      })}
    </ul>
  );
}
