import sitePlanData from "@/data/site-plan.json";
import standsData from "@/data/stands.json";
import type { SitePlan, Stand } from "@/types/stand";

/**
 * Data-access layer for the site map. Every function is async so swapping the
 * JSON imports below for network calls against the backend API won't change
 * any caller. When the API lands, `getStands` is also the place to switch to
 * bounding-box or paginated queries instead of loading the whole township.
 */

export async function getSitePlan(): Promise<SitePlan> {
  return sitePlanData as SitePlan;
}

export async function getStands(): Promise<Stand[]> {
  return standsData as Stand[];
}

export async function getStandById(id: string): Promise<Stand | undefined> {
  const stands = await getStands();
  return stands.find((stand) => stand.id === id);
}

/** Stands near a point, used to draw the locator map on a stand's profile. */
export async function getStandsNear(centre: { x: number; y: number }, radius: number): Promise<Stand[]> {
  const stands = await getStands();
  return stands.filter((stand) => Math.hypot(stand.centroid.x - centre.x, stand.centroid.y - centre.y) <= radius);
}
