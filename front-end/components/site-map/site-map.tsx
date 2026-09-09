"use client";

import { useEffect, useMemo, useRef, useState } from "react";

import { STAND_STATUS_FILL, STAND_STATUS_LABEL } from "@/lib/stand-status";
import { useMapViewport } from "@/components/site-map/use-map-viewport";
import type { MapPoint, SitePlan, Stand, StandStatus } from "@/types/stand";

const toPath = (points: MapPoint[]) => points.map((p) => `${p.x},${p.y}`).join(" ");

// Level-of-detail thresholds, expressed as multiples of the fit-to-screen zoom.
const ROAD_LABEL_ZOOM = 1.8;
const STAND_LABEL_ZOOM = 4.5;
const SCALE_BAR_STEPS = [5, 10, 20, 25, 50, 100, 200, 250, 500, 1000];
const MAX_HIGHLIGHTED_MATCHES = 200;

export interface FocusTarget {
  point: MapPoint;
  scale?: number;
  key: number;
}

interface SiteMapProps {
  plan: SitePlan;
  stands: Stand[];
  selectedId: string | null;
  matchIds: ReadonlySet<string> | null;
  statusFilter: StandStatus | "all";
  focusTarget: FocusTarget | null;
  onSelect: (stand: Stand | null) => void;
}

export function SiteMap({ plan, stands, selectedId, matchIds, statusFilter, focusTarget, onSelect }: SiteMapProps) {
  const { containerRef, viewBox, scale, fitScale, zoomFactor, isPanning, pointerHandlers, onKeyDown, zoomAt, flyTo, fit } =
    useMapViewport(plan.bounds);

  const [hoveredId, setHoveredId] = useState<string | null>(null);
  // Panning captures the pointer, which retargets the click to the container,
  // so the stand under the pointer is recorded when the gesture starts.
  const pointerDownRef = useRef<{ x: number; y: number; standId?: string } | null>(null);

  const standsById = useMemo(() => new Map(stands.map((stand) => [stand.standNumber, stand])), [stands]);
  const selected = selectedId ? standsById.get(selectedId) : undefined;
  const hovered = hoveredId ? standsById.get(hoveredId) : undefined;

  useEffect(() => {
    if (focusTarget) flyTo(focusTarget.point, focusTarget.scale ?? fitScale * 9);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- re-run only when a new focus is requested
  }, [focusTarget?.key]);

  // Static layers: built once per data change so pan and zoom never re-render them.
  const roadLayer = useMemo(
    () =>
      plan.roads.map((road) => (
        <polygon key={road.id} points={toPath(road.points)} fill="var(--map-road)" stroke="var(--map-road-line)" strokeWidth={0.6} />
      )),
    [plan.roads],
  );

  const zoneLayer = useMemo(
    () =>
      plan.zones.map((zone) => (
        <polygon
          key={zone.id}
          points={toPath(zone.points)}
          fill={zone.kind === "open-space" ? "var(--map-open-space)" : zone.kind === "commercial" ? "var(--map-commercial)" : "var(--map-institutional)"}
          stroke="var(--map-line)"
          strokeWidth={0.8}
        />
      )),
    [plan.zones],
  );

  const standLayer = useMemo(
    () =>
      stands.map((stand) => (
        <polygon
          key={stand.standNumber}
          data-stand-id={stand.standNumber}
          data-status={stand.status}
          points={toPath(stand.points)}
          fill={STAND_STATUS_FILL[stand.status]}
          stroke="var(--map-line)"
          strokeWidth={0.7}
        />
      )),
    [stands],
  );

  const standLabelLayer = useMemo(
    () =>
      stands.map((stand) => (
        <text key={stand.standNumber} x={stand.centroid.x} y={stand.centroid.y} textAnchor="middle" dominantBaseline="middle" fontSize={3.4}>
          {stand.standNumber}
        </text>
      )),
    [stands],
  );

  const matchLayer = useMemo(() => {
    // A broad query can match most of the township; ringing every result is
    // both slow and unreadable, so the overlay only draws narrow searches.
    if (!matchIds || matchIds.size === 0 || matchIds.size > MAX_HIGHLIGHTED_MATCHES) return null;
    return stands
      .filter((stand) => matchIds.has(stand.standNumber))
      .map((stand) => <polygon key={stand.standNumber} points={toPath(stand.points)} fill="none" stroke="var(--map-match)" strokeWidth={2.5} />);
  }, [matchIds, stands]);

  function handleClick(event: React.MouseEvent) {
    const down = pointerDownRef.current;
    if (!down || Math.hypot(event.clientX - down.x, event.clientY - down.y) > 5) return;
    onSelect(down.standId ? (standsById.get(down.standId) ?? null) : null);
  }

  function handlePointerMove(event: React.PointerEvent) {
    pointerHandlers.onPointerMove(event);
    if (isPanning) return;
    const id = (event.target as SVGElement).dataset?.standId ?? null;
    setHoveredId((current) => (current === id ? current : id));
  }

  // The tooltip is anchored to the stand itself, not the cursor, so it tracks
  // the stand while the map moves. Percentages avoid measuring the DOM.
  const projected = hovered
    ? {
        left: `${((hovered.centroid.x - viewBox.x) / viewBox.width) * 100}%`,
        top: `${((hovered.centroid.y - viewBox.y) / viewBox.height) * 100}%`,
      }
    : null;

  const scaleBarMetres = SCALE_BAR_STEPS.reduce((best, step) => (step * scale <= 130 ? step : best), SCALE_BAR_STEPS[0]);

  return (
    <div className="relative overflow-hidden rounded-xl border border-black/[.08] bg-[var(--map-paper)] dark:border-white/[.145]">
      <div
        ref={containerRef}
        tabIndex={0}
        role="application"
        aria-label={`${plan.name} site map. Drag to pan, scroll to zoom, click a stand to select it.`}
        onKeyDown={onKeyDown}
        onClick={handleClick}
        onDoubleClick={(event) => zoomAt(2, event.clientX, event.clientY)}
        onPointerDown={(event) => {
          pointerDownRef.current = { x: event.clientX, y: event.clientY, standId: (event.target as SVGElement).dataset?.standId };
          pointerHandlers.onPointerDown(event);
        }}
        onPointerMove={handlePointerMove}
        onPointerUp={pointerHandlers.onPointerUp}
        onPointerCancel={pointerHandlers.onPointerCancel}
        onPointerLeave={() => setHoveredId(null)}
        className={`h-[62vh] min-h-[420px] w-full touch-none outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 ${isPanning ? "cursor-grabbing" : "cursor-grab"}`}
      >
        <svg
          viewBox={`${viewBox.x} ${viewBox.y} ${viewBox.width} ${viewBox.height}`}
          preserveAspectRatio="xMidYMid slice"
          className="h-full w-full"
        >
          <polygon points={toPath(plan.boundary)} fill="var(--map-paper)" stroke="var(--map-ink)" strokeWidth={1.6} vectorEffect="non-scaling-stroke" />

          <g className="[&>polygon]:[vector-effect:non-scaling-stroke]">{roadLayer}</g>
          <g className="[&>polygon]:[vector-effect:non-scaling-stroke]">{zoneLayer}</g>

          <g
            data-filter={statusFilter}
            className="map-stands [&>polygon]:[vector-effect:non-scaling-stroke]"
          >
            {standLayer}
          </g>

          {matchLayer && <g className="pointer-events-none [&>polygon]:[vector-effect:non-scaling-stroke]">{matchLayer}</g>}

          {selected && (
            <polygon
              className="pointer-events-none"
              points={toPath(selected.points)}
              fill="var(--map-selected-fill)"
              stroke="var(--map-selected)"
              strokeWidth={2.5}
              vectorEffect="non-scaling-stroke"
            />
          )}

          {zoomFactor >= ROAD_LABEL_ZOOM && (
            <g className="pointer-events-none fill-[var(--map-ink)] opacity-70">
              {plan.roads.map((road) => (
                <text
                  key={road.id}
                  x={road.label.x}
                  y={road.label.y}
                  fontSize={4.4}
                  textAnchor="middle"
                  dominantBaseline="middle"
                  transform={`rotate(${road.label.angle} ${road.label.x} ${road.label.y})`}
                >
                  {road.name}
                </text>
              ))}
            </g>
          )}

          <g className="pointer-events-none fill-[var(--map-ink)]">
            {plan.zones.map((zone) => (
              <text key={zone.id} x={zone.label.x} y={zone.label.y} fontSize={14} textAnchor="middle" dominantBaseline="middle" className="uppercase tracking-wide opacity-70">
                {zone.name}
              </text>
            ))}
          </g>

          {zoomFactor < STAND_LABEL_ZOOM && (
            <g className="pointer-events-none fill-[var(--map-ink)] opacity-45">
              {plan.blocks.map((block) => (
                <text key={block.id} x={block.x} y={block.y} fontSize={26} textAnchor="middle" dominantBaseline="middle" className="font-semibold uppercase tracking-widest">
                  {block.name}
                </text>
              ))}
            </g>
          )}

          <g className="pointer-events-none fill-[var(--map-ink)]" style={{ display: zoomFactor >= STAND_LABEL_ZOOM ? undefined : "none" }}>
            {standLabelLayer}
          </g>
        </svg>
      </div>

      {hovered && projected && (
        <div
          className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-[calc(100%+10px)] rounded-lg bg-zinc-900/95 px-2.5 py-1.5 text-xs text-white shadow-lg dark:bg-zinc-100/95 dark:text-zinc-900"
          style={{ left: projected.left, top: projected.top }}
        >
          <span className="font-semibold">Stand {hovered.standNumber}</span>
          <span className="opacity-70"> · {STAND_STATUS_LABEL[hovered.status]} · {hovered.areaSqm} m²</span>
        </div>
      )}

      <div className="pointer-events-none absolute inset-x-3 bottom-3 flex items-end justify-between gap-3">
        <div className="rounded-lg border border-black/[.08] bg-white/85 px-3 py-2 text-[11px] leading-tight text-zinc-600 backdrop-blur dark:border-white/[.145] dark:bg-zinc-950/85 dark:text-zinc-400">
          <p className="font-semibold uppercase tracking-wide text-zinc-900 dark:text-zinc-50">{plan.name}</p>
          <p>{plan.subtitle}</p>
          {/* Depends on the measured container, so it only renders once measured. */}
          {fitScale > 0 && (
            <p className="mt-1 flex items-center gap-2">
              <span className="inline-block h-1.5 border-y border-zinc-500" style={{ width: scaleBarMetres * scale }} />
              {scaleBarMetres} m
            </p>
          )}
        </div>

        <div className="pointer-events-auto flex flex-col gap-1.5">
          <MapButton label="Zoom in" onClick={() => zoomAt(1.6)}>+</MapButton>
          <MapButton label="Zoom out" onClick={() => zoomAt(1 / 1.6)}>−</MapButton>
          <MapButton label="Fit plan to view" onClick={fit}>⤢</MapButton>
        </div>
      </div>

      <div className="pointer-events-none absolute right-3 top-3 flex flex-col items-center text-zinc-500 dark:text-zinc-400">
        <svg viewBox="0 0 24 34" className="h-9 w-6" style={{ transform: `rotate(${plan.northRotation}deg)` }} aria-hidden>
          <path d="M12 2 L18 22 L12 17 L6 22 Z" fill="currentColor" />
        </svg>
        <span className="text-[10px] font-semibold">N</span>
      </div>
    </div>
  );
}

function MapButton({ label, onClick, children }: { label: string; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={onClick}
      className="flex h-8 w-8 items-center justify-center rounded-lg border border-black/[.08] bg-white/90 text-base text-zinc-700 backdrop-blur transition-colors hover:bg-white dark:border-white/[.145] dark:bg-zinc-950/90 dark:text-zinc-200 dark:hover:bg-zinc-900"
    >
      {children}
    </button>
  );
}
