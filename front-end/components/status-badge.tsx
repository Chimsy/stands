import { STAND_STATUS_LABEL, STAND_STATUS_SWATCH, STAND_STATUS_TEXT } from "@/lib/stand-status";
import type { StandStatus } from "@/types/stand";

export function StatusBadge({ status }: { status: StandStatus }) {
  return (
    <span
      className={`inline-flex shrink-0 items-center gap-1.5 rounded-full bg-black/[.04] px-2.5 py-1 text-xs font-medium dark:bg-white/[.08] ${STAND_STATUS_TEXT[status]}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${STAND_STATUS_SWATCH[status]}`} />
      {STAND_STATUS_LABEL[status]}
    </span>
  );
}
