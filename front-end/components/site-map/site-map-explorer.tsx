"use client";

import Link from "next/link";
import { useMemo, useRef, useState } from "react";

import { Legend } from "@/components/site-map/legend";
import { SiteMap, type FocusTarget } from "@/components/site-map/site-map";
import { StatusBadge } from "@/components/status-badge";
import { formatNumber, formatPrice } from "@/lib/format";
import { STAND_STATUSES, STAND_STATUS_LABEL } from "@/lib/stand-status";
import type { SitePlan, Stand, StandStatus } from "@/types/stand";

const MAX_RESULTS = 60;

export function SiteMapExplorer({ plan, stands }: { plan: SitePlan; stands: Stand[] }) {
  const [query, setQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<StandStatus | "all">("all");
  const [selected, setSelected] = useState<Stand | null>(null);
  const [focusTarget, setFocusTarget] = useState<FocusTarget | null>(null);
  const focusKey = useRef(0);

  const normalizedQuery = query.trim().toLowerCase();

  const results = useMemo(() => {
    if (!normalizedQuery) return [];
    return stands.filter(
      (stand) =>
        stand.standNumber.includes(normalizedQuery) ||
        stand.road.toLowerCase().includes(normalizedQuery) ||
        stand.block.toLowerCase().includes(normalizedQuery),
    );
  }, [stands, normalizedQuery]);

  const matchIds = useMemo(() => (results.length ? new Set(results.map((stand) => stand.standNumber)) : null), [results]);

  function focusStand(stand: Stand) {
    setSelected(stand);
    focusKey.current += 1;
    setFocusTarget({ point: stand.centroid, key: focusKey.current });
  }

  return (
    <div className="mx-auto flex w-full max-w-7xl flex-col gap-4 p-4 sm:p-6">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{plan.name}</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">
            {plan.subtitle} · {formatNumber(stands.length)} stands · {plan.authority}
          </p>
        </div>
        <p className="text-xs text-zinc-400 dark:text-zinc-500">Drag to pan · scroll to zoom · click a stand for details</p>
      </header>

      <div className="flex flex-wrap items-center gap-2">
        <form
          className="min-w-56 flex-1"
          onSubmit={(event) => {
            event.preventDefault();
            if (results.length) focusStand(results[0]);
          }}
        >
          <label>
            <span className="sr-only">Search by stand number, road or block</span>
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search stand number, road or block…"
              className="w-full rounded-lg border border-black/[.08] bg-white px-3.5 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none dark:border-white/[.145] dark:bg-zinc-950 dark:text-zinc-50"
            />
          </label>
        </form>

        <div className="flex items-center gap-1.5">
          <FilterChip active={statusFilter === "all"} onClick={() => setStatusFilter("all")}>
            All
          </FilterChip>
          {STAND_STATUSES.map((status) => (
            <FilterChip key={status} active={statusFilter === status} onClick={() => setStatusFilter(status)}>
              {STAND_STATUS_LABEL[status]}
            </FilterChip>
          ))}
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_300px]">
        <SiteMap
          plan={plan}
          stands={stands}
          selectedId={selected?.standNumber ?? null}
          matchIds={matchIds}
          statusFilter={statusFilter}
          focusTarget={focusTarget}
          onSelect={setSelected}
        />

        <aside className="flex flex-col gap-3">
          <Legend stands={stands} />

          {selected ? (
            <section className="flex flex-col gap-3 rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-zinc-950">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h2 className="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Stand {selected.standNumber}</h2>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">
                    {selected.block} · {selected.road}
                  </p>
                </div>
                <StatusBadge status={selected.status} />
              </div>

              <dl className="grid grid-cols-2 gap-2 text-sm">
                <Detail label="Area" value={`${selected.areaSqm} m²`} />
                <Detail label={selected.status === "sold" ? "Sold price" : "Asking price"} value={formatPrice(selected.price)} />
              </dl>

              <div className="flex gap-2">
                <Link
                  href={`/stands/${selected.standNumber}`}
                  className="flex-1 rounded-lg bg-zinc-900 px-3 py-2 text-center text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                  Full profile
                </Link>
                <button
                  type="button"
                  onClick={() => focusStand(selected)}
                  className="rounded-lg border border-black/[.08] px-3 py-2 text-sm text-zinc-700 hover:bg-black/[.04] dark:border-white/[.145] dark:text-zinc-300 dark:hover:bg-white/[.08]"
                >
                  Zoom to
                </button>
              </div>
            </section>
          ) : (
            <section className="rounded-xl border border-black/[.08] bg-white p-4 text-sm text-zinc-500 dark:border-white/[.145] dark:bg-zinc-950 dark:text-zinc-400">
              Select a stand on the plan to see its details, or search for one by number.
            </section>
          )}

          {normalizedQuery && (
            <section className="flex min-h-0 flex-col gap-2 rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-zinc-950">
              <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                {formatNumber(results.length)} result{results.length === 1 ? "" : "s"}
              </h2>
              <ul className="flex max-h-72 flex-col gap-0.5 overflow-y-auto">
                {results.slice(0, MAX_RESULTS).map((stand) => (
                  <li key={stand.standNumber}>
                    <button
                      type="button"
                      onClick={() => focusStand(stand)}
                      className={`flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-black/[.04] dark:hover:bg-white/[.08] ${
                        selected?.standNumber === stand.standNumber ? "bg-black/[.04] dark:bg-white/[.08]" : ""
                      }`}
                    >
                      <span>
                        <span className="font-medium text-zinc-900 dark:text-zinc-50">{stand.standNumber}</span>
                        <span className="ml-2 text-xs text-zinc-400">{stand.road}</span>
                      </span>
                      <StatusBadge status={stand.status} />
                    </button>
                  </li>
                ))}
              </ul>
              {results.length > MAX_RESULTS && (
                <p className="text-xs text-zinc-400">Showing first {MAX_RESULTS}. Refine the search to narrow it down.</p>
              )}
              {results.length === 0 && <p className="text-sm text-zinc-400">No stands match &ldquo;{query}&rdquo;.</p>}
            </section>
          )}
        </aside>
      </div>
    </div>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs text-zinc-500 dark:text-zinc-400">{label}</dt>
      <dd className="font-medium text-zinc-900 dark:text-zinc-50">{value}</dd>
    </div>
  );
}

function FilterChip({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${
        active
          ? "border-transparent bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900"
          : "border-black/[.08] text-zinc-600 hover:bg-black/[.04] dark:border-white/[.145] dark:text-zinc-400 dark:hover:bg-white/[.08]"
      }`}
    >
      {children}
    </button>
  );
}
