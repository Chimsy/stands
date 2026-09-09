import { SiteMapExplorer } from "@/components/site-map/site-map-explorer";
import { getSitePlan, getStands } from "@/lib/stands";

export default async function Home() {
  const [plan, stands] = await Promise.all([getSitePlan(), getStands()]);

  return <SiteMapExplorer plan={plan} stands={stands} />;
}
