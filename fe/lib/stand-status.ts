import type { StandStatus } from "@/types/stand";

export const STAND_STATUSES: StandStatus[] = ["available", "sold", "in-progress"];

export const STAND_STATUS_LABEL: Record<StandStatus, string> = {
  available: "Available",
  sold: "Sold",
  "in-progress": "In Progress",
};

/** Fill used for stand polygons on the plan. Values resolve in globals.css. */
export const STAND_STATUS_FILL: Record<StandStatus, string> = {
  available: "var(--map-available)",
  sold: "var(--map-sold)",
  "in-progress": "var(--map-progress)",
};

export const STAND_STATUS_SWATCH: Record<StandStatus, string> = {
  available: "bg-emerald-400",
  sold: "bg-rose-400",
  "in-progress": "bg-amber-400",
};

export const STAND_STATUS_TEXT: Record<StandStatus, string> = {
  available: "text-emerald-700 dark:text-emerald-400",
  sold: "text-rose-700 dark:text-rose-400",
  "in-progress": "text-amber-700 dark:text-amber-500",
};
