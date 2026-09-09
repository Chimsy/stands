import { redirect } from "next/navigation";

import { ApiError, apiRequest, type Envelope } from "@/lib/api";
import type { MapPoint, SitePlan, Stand } from "@/types/stand";

/**
 * Data-access layer for the site map. Every read goes to the Laravel API, and a
 * rejected token sends the visitor back to the sign-in page rather than
 * surfacing an error they can do nothing about.
 */
async function readFromApi<T>(path: string, query?: Record<string, string>): Promise<T> {
  try {
    const { data } = await apiRequest<Envelope<T>>(path, { query });
    return data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      redirect("/login?expired=1");
    }
    throw error;
  }
}

export async function getSitePlan(): Promise<SitePlan> {
  return readFromApi<SitePlan>("/site-plan");
}

export async function getStands(): Promise<Stand[]> {
  return readFromApi<Stand[]>("/stands");
}

export async function getStandByNumber(standNumber: string): Promise<Stand | undefined> {
  try {
    return await readFromApi<Stand>(`/stands/${encodeURIComponent(standNumber)}`);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** Stands near a point, used to draw the locator map on a stand's profile. */
export async function getStandsNear(centre: MapPoint, radius: number): Promise<Stand[]> {
  return readFromApi<Stand[]>("/stands", {
    x: String(centre.x),
    y: String(centre.y),
    radius: String(radius),
  });
}
