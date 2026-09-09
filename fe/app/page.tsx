import { SiteMapExplorer } from "@/components/site-map/site-map-explorer";
import { getSitePlan, getStands } from "@/lib/stands";

export default async function Home() {
  const [plan, stands] = await Promise.all([getSitePlan(), getStands()]);

  return (
    <div className="flex flex-1 flex-col bg-zinc-50 dark:bg-black">
      <SiteMapExplorer plan={plan} stands={stands} />
    </div>
  );
}
