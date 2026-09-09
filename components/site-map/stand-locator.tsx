import { STAND_STATUS_FILL } from "@/lib/stand-status";
import type { MapPoint, SitePlan, Stand } from "@/types/stand";

const toPath = (points: MapPoint[]) => points.map((p) => `${p.x},${p.y}`).join(" ");

/** Static extract of the plan around one stand, used on the stand profile. */
export function StandLocator({ plan, stand, neighbours, radius }: { plan: SitePlan; stand: Stand; neighbours: Stand[]; radius: number }) {
  const view = { x: stand.centroid.x - radius, y: stand.centroid.y - radius * 0.62, width: radius * 2, height: radius * 1.24 };

  return (
    <svg
      viewBox={`${view.x} ${view.y} ${view.width} ${view.height}`}
      className="h-auto w-full rounded-xl border border-black/[.08] bg-[var(--map-paper)] dark:border-white/[.145]"
      role="img"
      aria-label={`Location of stand ${stand.standNumber} on the ${plan.name} plan`}
    >
      {plan.roads.map((road) => (
        <polygon key={road.id} points={toPath(road.points)} fill="var(--map-road)" stroke="var(--map-road-line)" strokeWidth={0.4} />
      ))}
      {plan.zones.map((zone) => (
        <polygon key={zone.id} points={toPath(zone.points)} fill="var(--map-open-space)" stroke="var(--map-line)" strokeWidth={0.4} />
      ))}
      {neighbours.map((neighbour) => (
        <polygon key={neighbour.id} points={toPath(neighbour.points)} fill={STAND_STATUS_FILL[neighbour.status]} stroke="var(--map-line)" strokeWidth={0.4} />
      ))}
      {neighbours.map((neighbour) => (
        <text
          key={neighbour.id}
          x={neighbour.centroid.x}
          y={neighbour.centroid.y}
          fontSize={5}
          textAnchor="middle"
          dominantBaseline="middle"
          className="fill-[var(--map-ink)] opacity-70"
        >
          {neighbour.standNumber}
        </text>
      ))}
      <polygon points={toPath(stand.points)} fill="var(--map-selected-fill)" stroke="var(--map-selected)" strokeWidth={1.6} />
      <text
        x={stand.centroid.x}
        y={stand.centroid.y}
        fontSize={6}
        textAnchor="middle"
        dominantBaseline="middle"
        className="fill-[var(--map-ink)] font-semibold"
      >
        {stand.standNumber}
      </text>
    </svg>
  );
}
