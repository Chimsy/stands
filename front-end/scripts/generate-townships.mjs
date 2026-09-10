#!/usr/bin/env node
/**
 * Generates every township fixture the backend seeds from, one directory per
 * township under `database/data/`.
 *
 * Each township is produced by running `generate-site-plan.mjs` with the
 * configuration below in its environment. Layout geometry is fixed - only the
 * seed, bearing, naming and stand numbering vary - so the two estates share a
 * street skeleton but differ in stand sizes, prices, statuses and names.
 *
 * Stand numbers must not overlap between townships: they are what agents quote
 * and what the API routes on.
 *
 * Run with: npm run generate:plan
 */
import { spawnSync } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const GENERATOR = resolve(dirname(fileURLToPath(import.meta.url)), "generate-site-plan.mjs");

const TOWNSHIPS = [
  {
    PLAN_SLUG: "riverstone-park",
    PLAN_NAME: "Riverstone Park Estate",
    PLAN_SUBTITLE: "Proposed medium density residential township",
    PLAN_AUTHORITY: "City of Harare",
    PLAN_SEED: "20260907",
    PLAN_ROTATION: "5",
    PLAN_FIRST_STAND: "2001",
  },
  {
    PLAN_SLUG: "hillside-park",
    PLAN_NAME: "Hillside Park Estate",
    PLAN_SUBTITLE: "Proposed low density residential township",
    PLAN_AUTHORITY: "City of Bulawayo",
    PLAN_SEED: "20260910",
    PLAN_ROTATION: "-8",
    PLAN_FIRST_STAND: "5001",
    PLAN_ARTERIAL_NAMES: JSON.stringify(["Hillside Road", "Burnside Drive", "Matopos Road", "Khami Road"]),
    PLAN_COLLECTOR_NAMES: JSON.stringify([
      "Mopane Road",
      "Marula Road",
      "Umtshabezi Road",
      "Nkulumane Road",
      "Ntaba Road",
    ]),
    PLAN_MINOR_NAMES: JSON.stringify([
      "Aloe Close",
      "Ilala Way",
      "Umganu Close",
      "Sikhonyane Way",
      "Ihlahla Close",
      "Umkhomo Way",
      "Isihlahla Close",
      "Umnyii Way",
      "Ithambo Close",
      "Umtshwankela Way",
      "Inkanyezi Close",
      "Umganu Grove",
    ]),
  },
];

for (const township of TOWNSHIPS) {
  const result = spawnSync(process.execPath, [GENERATOR], {
    stdio: "inherit",
    env: { ...process.env, ...township },
  });

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}
