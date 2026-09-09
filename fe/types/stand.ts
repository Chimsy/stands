export type StandStatus = "available" | "sold" | "in-progress";

export interface MapPoint {
  x: number;
  y: number;
}

/** Coordinates are metres on the site plan, origin at the plan's top-left corner. */
export interface Stand {
  id: string;
  standNumber: string;
  status: StandStatus;
  block: string;
  road: string;
  areaSqm: number;
  price: number;
  updatedAt: string;
  centroid: MapPoint;
  points: MapPoint[];
}

export type RoadKind = "arterial" | "collector" | "minor";

export interface Road {
  id: string;
  name: string;
  kind: RoadKind;
  width: number;
  points: MapPoint[];
  label: MapPoint & { angle: number };
}

export type ZoneKind = "open-space" | "institutional" | "commercial";

export interface Zone {
  id: string;
  name: string;
  kind: ZoneKind;
  points: MapPoint[];
  label: MapPoint;
}

export interface BlockLabel {
  id: string;
  name: string;
  x: number;
  y: number;
}

export interface SitePlan {
  name: string;
  subtitle: string;
  authority: string;
  note: string;
  northRotation: number;
  bounds: { width: number; height: number };
  boundary: MapPoint[];
  roads: Road[];
  zones: Zone[];
  blocks: BlockLabel[];
}
