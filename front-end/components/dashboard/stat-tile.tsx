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
    <div className="flex flex-col gap-1 rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-zinc-950">
      <p className="text-xs text-zinc-500 dark:text-zinc-400">{label}</p>
      <p
        className={`font-semibold text-zinc-900 dark:text-zinc-50 ${hero ? "text-4xl" : "text-2xl"}`}
      >
        {value}
      </p>
      {note && <p className="text-xs text-zinc-500 dark:text-zinc-400">{note}</p>}
    </div>
  );
}
