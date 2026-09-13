import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";

import { MonthlyChart } from "@/components/dashboard/monthly-chart";
import { SaleMix } from "@/components/dashboard/sale-mix";
import { StatTile } from "@/components/dashboard/stat-tile";
import { TakeUp } from "@/components/dashboard/take-up";
import { ValueBars } from "@/components/dashboard/value-bars";
import { getAuthenticatedUser } from "@/lib/auth";
import { share } from "@/lib/chart-scale";
import { getDashboard } from "@/lib/dashboard";
import { formatCents, formatDate, formatNumber } from "@/lib/format";
import { SALE_STATUS_LABEL, SALE_TYPE_LABEL } from "@/types/trading";

export const metadata: Metadata = { title: "Dashboard" };

export default async function DashboardPage(props: PageProps<"/dashboard">) {
  const { branch, from, to } = await props.searchParams;
  const user = await getAuthenticatedUser();

  /**
   * The API refuses this to an agent, but rendering the page for one would flash
   * a screen they cannot have; the nav does not offer it either.
   */
  if (!user?.isAdmin) {
    notFound();
  }

  const scope = {
    ...(typeof branch === "string" ? { branch } : {}),
    ...(typeof from === "string" ? { from } : {}),
    ...(typeof to === "string" ? { to } : {}),
  };

  const dashboard = await getDashboard(scope);
  const { totals } = dashboard;

  const isGroup = dashboard.branch === null;
  const scopeName = isGroup
    ? `All ${formatNumber(dashboard.branches.length)} branches`
    : (dashboard.branches[0]?.name ?? dashboard.branch);

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Sales dashboard</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">
            {scopeName} · {formatDate(dashboard.from)} – {formatDate(dashboard.to)}
          </p>
        </div>

        <nav className="flex items-center gap-1.5">
          <ScopeLink href={buildHref({ ...scope, branch: user.branch?.code })} active={!isGroup}>
            {user.branch?.name ?? "This branch"}
          </ScopeLink>
          <ScopeLink href={buildHref({ ...scope, branch: "group" })} active={isGroup}>
            Group
          </ScopeLink>
        </nav>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile
          label="Value signed"
          value={formatCents(totals.valueCents)}
          note={`${formatNumber(totals.salesCount)} ${totals.salesCount === 1 ? "sale" : "sales"} in the period`}
          hero
        />
        <StatTile
          label="Cash collected"
          value={formatCents(totals.collectedCents)}
          note={`${Math.round(share(totals.collectedCents, totals.valueCents) * 100)}% of the value signed`}
        />
        <StatTile
          label="Still owed"
          value={formatCents(totals.outstandingCents)}
          note={`Whole book as at ${formatDate(dashboard.to)}`}
        />
        <StatTile
          label="Stands taken"
          value={formatNumber(totals.standsSold + totals.standsInProgress)}
          note={`${formatNumber(totals.standsAvailable)} of ${formatNumber(totals.standsTotal)} still available`}
        />
      </div>

      <Panel
        title="Signings and collections"
        subtitle="Value signed against cash received, by month. Revenue is recognised at signing, so the two rarely land in the same month."
      >
        <MonthlyChart points={dashboard.monthly} />
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title="Branch performance" subtitle="Value signed in the period">
          <ValueBars
            rows={dashboard.branches
              .map((office) => ({
                key: office.code,
                label: office.name,
                note: `${formatCents(office.collectedCents)} collected`,
                cents: office.valueCents,
                count: office.salesCount,
              }))
              .sort((a, b) => b.cents - a.cents)}
            empty="No branch traded in this period."
          />
        </Panel>

        <Panel title="Stock take-up" subtitle="Sold and reserved against the whole estate">
          <TakeUp branches={dashboard.branches} />
        </Panel>

        <Panel title="How it was sold" subtitle={`Value signed ${formatDate(dashboard.from)} – ${formatDate(dashboard.to)}`}>
          <SaleMix mix={dashboard.mix} />
        </Panel>

        <Panel title="Leading agents" subtitle="By value signed in the period">
          <ValueBars
            rows={dashboard.topAgents.map((agent) => ({
              key: `${agent.name}-${agent.branch}`,
              label: agent.name,
              note: agent.branch ?? undefined,
              cents: agent.valueCents,
              count: agent.salesCount,
            }))}
            empty="No sale in this period was attributed to an agent."
          />
        </Panel>
      </div>

      <Panel title="Latest signings" subtitle="The most recent sales, whenever they were booked">
        <div className="overflow-x-auto">
          <table className="w-full min-w-xl text-sm">
            <thead className="text-left text-xs text-zinc-500 dark:text-zinc-400">
              <tr>
                <th className="pb-2 font-medium">Reference</th>
                <th className="pb-2 font-medium">Stand</th>
                <th className="pb-2 font-medium">Buyer</th>
                <th className="pb-2 font-medium">Date</th>
                <th className="pb-2 font-medium">Terms</th>
                <th className="pb-2 text-right font-medium">Price</th>
                <th className="pb-2 text-right font-medium">Owing</th>
              </tr>
            </thead>
            <tbody>
              {dashboard.recentSales.map((sale) => (
                <tr key={sale.reference} className="border-t border-black/[.05] dark:border-white/[.08]">
                  <td className="py-1.5">
                    <Link href={`/sales/${sale.reference}`} className="text-zinc-900 hover:underline dark:text-zinc-50">
                      {sale.reference}
                    </Link>
                  </td>
                  <td className="py-1.5 text-zinc-600 dark:text-zinc-400">{sale.standNumber ?? "—"}</td>
                  <td className="py-1.5 text-zinc-600 dark:text-zinc-400">{sale.buyerName ?? "—"}</td>
                  <td className="py-1.5 text-zinc-600 dark:text-zinc-400">{formatDate(sale.saleDate)}</td>
                  <td className="py-1.5 text-zinc-600 dark:text-zinc-400">
                    {SALE_TYPE_LABEL[sale.type]}
                    <span className="ml-1.5 text-xs text-zinc-400">{SALE_STATUS_LABEL[sale.status]}</span>
                  </td>
                  <td className="py-1.5 text-right tabular-nums">{formatCents(sale.valueCents)}</td>
                  <td
                    className={`py-1.5 text-right tabular-nums ${
                      sale.outstandingCents > 0 ? "text-zinc-900 dark:text-zinc-50" : "text-zinc-400"
                    }`}
                  >
                    {sale.outstandingCents > 0 ? formatCents(sale.outstandingCents) : "Settled"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
    </div>
  );
}

const buildHref = (scope: Record<string, string | undefined>) =>
  `/dashboard?${new URLSearchParams(
    Object.entries(scope).filter((entry): entry is [string, string] => Boolean(entry[1])),
  )}`;

function ScopeLink({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className={`rounded-full border px-3 py-1.5 text-xs font-medium ${
        active
          ? "border-transparent bg-marine-600 text-white dark:bg-marine-400 dark:text-marine-950"
          : "border-black/[.08] text-zinc-600 hover:bg-black/[.04] dark:border-white/[.145] dark:text-zinc-400 dark:hover:bg-white/[.08]"
      }`}
    >
      {children}
    </Link>
  );
}

function Panel({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col rounded-xl border border-black/[.08] bg-white p-5 dark:border-white/[.145] dark:bg-zinc-950">
      <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{title}</h2>
      <p className="mb-4 text-xs text-zinc-500 dark:text-zinc-400">{subtitle}</p>
      {children}
    </section>
  );
}
