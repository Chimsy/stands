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

/** An agent works one branch; an administrator may work any of them. */
export type UserRole = "sales" | "admin";

export interface AuthenticatedUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  isAdmin: boolean;
  /** The office this request answered for, not necessarily the user's home one. Null only for an account with no office. */
  branch: { code: string; name: string; city: string } | null;
  /** The offices this user may switch between: one for an agent, all of them for an administrator. */
  branches: { code: string; name: string; city: string }[];
}
