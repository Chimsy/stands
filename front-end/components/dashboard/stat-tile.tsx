/**
 * A figure that needs no chart. The value is the point; the note underneath
 * says what it is measured against, because a number without a denominator is
 * not a fact about the business.
 */
export function StatTile({
  label,
  value,
  note,
  hero = false,
}: {
  label: string;
  value: string;
  note?: string;
  hero?: boolean;
}) {
  return (
    <div
      className={`flex flex-col gap-1 rounded-xl border p-4 ${
        hero
          ? "border-brand-600/20 bg-brand-50 dark:border-brand-400/25 dark:bg-brand-950/50"
          : "border-black/[.08] bg-white dark:border-white/[.145] dark:bg-zinc-950"
      }`}
    >
      <p
        className={`text-xs ${hero ? "text-brand-800 dark:text-brand-300" : "text-zinc-500 dark:text-zinc-400"}`}
      >
        {label}
      </p>
      <p
        className={`font-semibold ${
          hero ? "text-4xl text-brand-800 dark:text-brand-300" : "text-2xl text-zinc-900 dark:text-zinc-50"
        }`}
      >
        {value}
      </p>
      {note && (
        <p
          className={`text-xs ${hero ? "text-brand-700 dark:text-brand-400" : "text-zinc-500 dark:text-zinc-400"}`}
        >
          {note}
        </p>
      )}
    </div>
  );
}
