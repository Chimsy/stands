import Link from "next/link";
import type { Metadata } from "next";

import { formatCents, formatDate } from "@/lib/format";
import { getAuthenticatedUser } from "@/lib/auth";
import { getBalanceSheet, getIncomeStatement, getReceivablesAgeing, getTrialBalance, type StatementScope } from "@/lib/reports";
import { getBranches } from "@/lib/trading";
import { AGEING_BUCKET_LABEL, type AgeingBucket, type StatementLine } from "@/types/reports";

export const metadata: Metadata = { title: "Financial statements" };

export default async function StatementsPage(props: PageProps<"/statements">) {
  const { branch, to } = await props.searchParams;
  const user = await getAuthenticatedUser();

  const scope: StatementScope = {
    ...(typeof branch === "string" ? { branch } : {}),
    ...(typeof to === "string" ? { to } : {}),
  };

  /**
   * All four are read in the same request, so every panel on the page reports
   * the same instant. They are independent queries, so they run together.
   */
  const [branches, trialBalance, incomeStatement, balanceSheet, ageing] = await Promise.all([
    getBranches(),
    getTrialBalance(scope),
    getIncomeStatement(scope),
    getBalanceSheet(scope),
    getReceivablesAgeing(scope),
  ]);

  const ownCode = user?.branch?.code;
  const isGroup = trialBalance.branch === null;
  const scopeLabel = isGroup
    ? "All branches consolidated"
    : (branches.find((option) => option.code === trialBalance.branch)?.name ?? trialBalance.branch);

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-8">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Financial statements</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">
            {scopeLabel} · as at {formatDate(balanceSheet.asAt)} · read from the ledger just now
          </p>
        </div>

        <nav className="flex items-center gap-1.5">
          {ownCode && (
            <ScopeLink href={buildHref(ownCode, to)} active={!isGroup}>
              My branch
            </ScopeLink>
          )}
          <ScopeLink href={buildHref("group", to)} active={isGroup}>
            Group ({branches.length} branches)
          </ScopeLink>
        </nav>
      </header>

      <BalanceWarning
        trialBalanced={trialBalance.inBalance}
        sheetBalanced={balanceSheet.inBalance}
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel
          title="Income statement"
          subtitle={`${formatDate(incomeStatement.from)} – ${formatDate(incomeStatement.to)}`}
        >
          <Rows lines={incomeStatement.revenue} />
          <Rows lines={incomeStatement.expenses} negate />
          <Total label="Net income" cents={incomeStatement.netIncomeCents} />
        </Panel>

        <Panel title="Balance sheet" subtitle={`As at ${formatDate(balanceSheet.asAt)}`}>
          <SectionHeading>Assets</SectionHeading>
          <Rows lines={balanceSheet.assets} />
          <Total label="Total assets" cents={balanceSheet.assetsCents} muted />

          {balanceSheet.liabilities.length > 0 && (
            <>
              <SectionHeading>Liabilities</SectionHeading>
              <Rows lines={balanceSheet.liabilities} />
            </>
          )}

          <SectionHeading>Equity</SectionHeading>
          <Rows lines={balanceSheet.equity} />
          <Total label="Liabilities and equity" cents={balanceSheet.liabilitiesCents + balanceSheet.equityCents} />
        </Panel>

        <Panel title="Trial balance" subtitle={`As at ${formatDate(trialBalance.asAt)}`}>
          <table className="w-full text-sm">
            <thead className="text-left text-xs text-zinc-500 dark:text-zinc-400">
              <tr>
                <th className="pb-2 font-medium">Account</th>
                <th className="pb-2 text-right font-medium">Debit</th>
                <th className="pb-2 text-right font-medium">Credit</th>
              </tr>
            </thead>
            <tbody>
              {trialBalance.rows.map((row) => (
                <tr key={row.code} className="border-t border-black/[.05] dark:border-white/[.08]">
                  <td className="py-1.5 text-zinc-700 dark:text-zinc-300">
                    <span className="text-zinc-400">{row.code}</span> {row.name}
                  </td>
                  <td className="py-1.5 text-right tabular-nums">{row.debitCents ? formatCents(row.debitCents) : "—"}</td>
                  <td className="py-1.5 text-right tabular-nums">{row.creditCents ? formatCents(row.creditCents) : "—"}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="border-t border-black/10 font-medium dark:border-white/20">
                <td className="pt-2">Totals</td>
                <td className="pt-2 text-right tabular-nums">{formatCents(trialBalance.totalDebitCents)}</td>
                <td className="pt-2 text-right tabular-nums">{formatCents(trialBalance.totalCreditCents)}</td>
              </tr>
            </tfoot>
          </table>
        </Panel>

        <Panel
          title="Receivables ageing"
          subtitle={`${formatCents(ageing.overdueCents)} of ${formatCents(ageing.totalCents)} is overdue`}
        >
          <ul className="flex flex-col gap-1.5 text-sm">
            {(Object.keys(AGEING_BUCKET_LABEL) as AgeingBucket[]).map((bucket) => (
              <li key={bucket} className="flex items-center justify-between gap-3">
                <span className="text-zinc-600 dark:text-zinc-400">{AGEING_BUCKET_LABEL[bucket]}</span>
                <span
                  className={`tabular-nums ${
                    bucket !== "notYetDue" && ageing.buckets[bucket] > 0
                      ? "text-rose-700 dark:text-rose-400"
                      : "text-zinc-900 dark:text-zinc-50"
                  }`}
                >
                  {formatCents(ageing.buckets[bucket])}
                </span>
              </li>
            ))}
          </ul>
          <p className="mt-3 border-t border-black/[.08] pt-2 text-xs text-zinc-400 dark:border-white/[.145]">
            Ties back to Accounts Receivable on the balance sheet.
          </p>
        </Panel>
      </div>
    </div>
  );
}

const buildHref = (branch: string, to: unknown) =>
  `/statements?${new URLSearchParams({ branch, ...(typeof to === "string" ? { to } : {}) })}`;

function BalanceWarning({ trialBalanced, sheetBalanced }: { trialBalanced: boolean; sheetBalanced: boolean }) {
  if (trialBalanced && sheetBalanced) {
    return (
      <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400">
        Debits equal credits and the balance sheet balances.
      </p>
    );
  }

  return (
    <p role="alert" className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/50 dark:text-rose-400">
      The ledger does not balance. Something has written to it outside the posting rules — stop and investigate before
      relying on these figures.
    </p>
  );
}

function ScopeLink({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className={`rounded-full border px-3 py-1.5 text-xs font-medium ${
        active
          ? "border-transparent bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900"
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
      <p className="mb-3 text-xs text-zinc-500 dark:text-zinc-400">{subtitle}</p>
      {children}
    </section>
  );
}

function SectionHeading({ children }: { children: React.ReactNode }) {
  return <h3 className="mt-3 text-xs uppercase tracking-widest text-zinc-400 first:mt-0">{children}</h3>;
}

function Rows({ lines, negate = false }: { lines: StatementLine[]; negate?: boolean }) {
  if (lines.length === 0) {
    return <p className="py-1 text-sm text-zinc-400">Nothing yet.</p>;
  }

  return (
    <ul className="flex flex-col gap-1 py-1 text-sm">
      {lines.map((line) => (
        <li key={line.code} className="flex items-center justify-between gap-3">
          <span className="text-zinc-600 dark:text-zinc-400">
            <span className="text-zinc-400">{line.code}</span> {line.name}
          </span>
          <span className="tabular-nums text-zinc-900 dark:text-zinc-50">
            {negate ? `(${formatCents(line.amountCents)})` : formatCents(line.amountCents)}
          </span>
        </li>
      ))}
    </ul>
  );
}

function Total({ label, cents, muted = false }: { label: string; cents: number; muted?: boolean }) {
  return (
    <div
      className={`mt-2 flex items-center justify-between gap-3 border-t pt-2 text-sm font-medium ${
        muted ? "border-black/[.05] text-zinc-600 dark:border-white/[.08] dark:text-zinc-400" : "border-black/10 text-zinc-900 dark:border-white/20 dark:text-zinc-50"
      }`}
    >
      <span>{label}</span>
      <span className="tabular-nums">{formatCents(cents)}</span>
    </div>
  );
}
