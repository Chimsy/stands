"use client";

import { useState } from "react";

import { niceScale } from "@/lib/chart-scale";
import { formatCents, formatCompactCents, formatMonth, formatNumber } from "@/lib/format";
import type { MonthlyPoint } from "@/types/dashboard";

/**
 * Signings against collections, month by month.
 *
 * Both series are money in the same currency, so they share one axis - the two
 * are only comparable because of that. Columns rather than lines: each month is
 * a closed period, not a reading on a continuum.
 */
const VIEW = { width: 760, height: 260, left: 56, right: 12, top: 16, bottom: 28 };

const COLUMN_GAP = 2;
const MAX_COLUMN = 22;

/** Half of the tooltip's `w-44`, which is what it has to be kept clear of either edge by. */
const TOOLTIP_HALF = 88;

export function MonthlyChart({ points }: { points: MonthlyPoint[] }) {
  const [hovered, setHovered] = useState<number | null>(null);

  const scale = niceScale(Math.max(0, ...points.flatMap((point) => [point.valueCents, point.collectedCents])));

  const plotWidth = VIEW.width - VIEW.left - VIEW.right;
  const plotHeight = VIEW.height - VIEW.top - VIEW.bottom;
  const baseline = VIEW.top + plotHeight;
  const band = plotWidth / Math.max(points.length, 1);

  /** Two columns to a month, capped so a sparse year does not draw slabs. */
  const columnWidth = Math.min(MAX_COLUMN, (band - COLUMN_GAP) / 2 - 6);
  const heightOf = (cents: number) => (cents / scale.max) * plotHeight;
  const centreOf = (index: number) => VIEW.left + band * index + band / 2;

  const active = hovered === null ? null : points[hovered];

  return (
    <figure className="m-0 flex flex-col gap-3">
      <Legend />

      <div className="relative">
        <svg
          viewBox={`0 0 ${VIEW.width} ${VIEW.height}`}
          className="h-auto w-full"
          role="img"
          aria-label="Value signed and cash collected, by month"
        >
          {scale.ticks.map((tick) => {
            const y = baseline - heightOf(tick);

            return (
              <g key={tick}>
                <line x1={VIEW.left} x2={VIEW.width - VIEW.right} y1={y} y2={y} stroke="var(--viz-grid)" strokeWidth={1} />
                <text x={VIEW.left - 8} y={y + 4} textAnchor="end" className="fill-zinc-400 text-[11px] tabular-nums">
                  {formatCompactCents(tick)}
                </text>
              </g>
            );
          })}

          <line
            x1={VIEW.left}
            x2={VIEW.width - VIEW.right}
            y1={baseline}
            y2={baseline}
            stroke="var(--viz-axis)"
            strokeWidth={1}
          />

          {points.map((point, index) => {
            const centre = centreOf(index);
            const signedHeight = heightOf(point.valueCents);
            const collectedHeight = heightOf(point.collectedCents);

            return (
              <g key={point.month}>
                <Column
                  x={centre - COLUMN_GAP / 2 - columnWidth}
                  height={signedHeight}
                  width={columnWidth}
                  baseline={baseline}
                  fill="var(--viz-signed)"
                  dimmed={hovered !== null && hovered !== index}
                />
                <Column
                  x={centre + COLUMN_GAP / 2}
                  height={collectedHeight}
                  width={columnWidth}
                  baseline={baseline}
                  fill="var(--viz-collected)"
                  dimmed={hovered !== null && hovered !== index}
                />

                <text
                  x={centre}
                  y={VIEW.height - 8}
                  textAnchor="middle"
                  className={`text-[11px] ${hovered === index ? "fill-zinc-900 dark:fill-zinc-50" : "fill-zinc-400"}`}
                >
                  {formatMonth(point.month)}
                </text>

                {/* A full-height band, so the whole column is a hover target rather than the bar alone. */}
                <rect
                  x={VIEW.left + band * index}
                  y={VIEW.top}
                  width={band}
                  height={plotHeight}
                  fill="transparent"
                  onMouseEnter={() => setHovered(index)}
                  onMouseLeave={() => setHovered(null)}
                >
                  {/* Carries the same figures as the tooltip to anyone who cannot hover. */}
                  <title>
                    {`${formatMonth(point.month)} ${point.month.slice(0, 4)}: ${formatCents(point.valueCents)} signed, ${formatCents(point.collectedCents)} collected`}
                  </title>
                </rect>
              </g>
            );
          })}
        </svg>

        {active && (
          <div
            className="pointer-events-none absolute top-0 z-10 w-44 -translate-x-1/2 rounded-lg border border-black/[.08] bg-white p-2.5 text-xs shadow-sm dark:border-white/[.145] dark:bg-zinc-900"
            /** Clamped by half its own width so a tooltip on the first or last month stays inside the panel. */
            style={{
              left: `clamp(${TOOLTIP_HALF}px, ${(centreOf(hovered!) / VIEW.width) * 100}%, calc(100% - ${TOOLTIP_HALF}px))`,
            }}
          >
            <p className="mb-1.5 font-medium text-zinc-900 dark:text-zinc-50">
              {formatMonth(active.month)} {active.month.slice(0, 4)}
            </p>
            <TooltipRow swatch="var(--viz-signed)" label="Signed" value={formatCents(active.valueCents)} />
            <TooltipRow swatch="var(--viz-collected)" label="Collected" value={formatCents(active.collectedCents)} />
            <p className="mt-1.5 border-t border-black/[.06] pt-1.5 text-zinc-500 dark:border-white/[.1] dark:text-zinc-400">
              {formatNumber(active.salesCount)} {active.salesCount === 1 ? "sale" : "sales"}
            </p>
          </div>
        )}
      </div>
    </figure>
  );
}

/** A 4px rounded cap on the data end, square where it meets the baseline. */
function Column({
  x,
  width,
  height,
  baseline,
  fill,
  dimmed,
}: {
  x: number;
  width: number;
  height: number;
  baseline: number;
  fill: string;
  dimmed: boolean;
}) {
  if (height <= 0) {
    return null;
  }

  const radius = Math.min(4, height, width / 2);

  return (
    <path
      d={`M ${x} ${baseline} L ${x} ${baseline - height + radius} Q ${x} ${baseline - height} ${x + radius} ${baseline - height} L ${x + width - radius} ${baseline - height} Q ${x + width} ${baseline - height} ${x + width} ${baseline - height + radius} L ${x + width} ${baseline} Z`}
      fill={fill}
      opacity={dimmed ? 0.45 : 1}
    />
  );
}

function Legend() {
  return (
    <ul className="flex items-center gap-4 text-xs text-zinc-600 dark:text-zinc-400">
      <LegendItem swatch="var(--viz-signed)" label="Value signed" />
      <LegendItem swatch="var(--viz-collected)" label="Cash collected" />
    </ul>
  );
}

function LegendItem({ swatch, label }: { swatch: string; label: string }) {
  return (
    <li className="flex items-center gap-1.5">
      <span className="h-2.5 w-2.5 rounded-sm" style={{ background: swatch }} />
      {label}
    </li>
  );
}

function TooltipRow({ swatch, label, value }: { swatch: string; label: string; value: string }) {
  return (
    <p className="flex items-center justify-between gap-2 text-zinc-600 dark:text-zinc-400">
      <span className="flex items-center gap-1.5">
        <span className="h-2 w-2 rounded-sm" style={{ background: swatch }} />
        {label}
      </span>
      <span className="tabular-nums text-zinc-900 dark:text-zinc-50">{value}</span>
    </p>
  );
}
