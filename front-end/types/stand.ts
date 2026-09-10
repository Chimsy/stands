export type StandStatus = "available" | "sold" | "in-progress";

export interface MapPoint {
  x: number;
  y: number;
}

/** Coordinates are metres on the site plan, origin at the plan's top-left corner. */
export interface Stand {
  /** Unique across the township, and the identifier the API routes on. */
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

export interface AuthenticatedUser {
  id: number;
  name: string;
  email: string;
  /** Null only for an account that has not been assigned an office yet. */
  branch: { code: string; name: string; city: string } | null;
}
