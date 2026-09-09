import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
      <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Stand not found</h1>
      <p className="text-sm text-zinc-500 dark:text-zinc-400">We couldn&apos;t find a stand with that reference.</p>
      <Link href="/" className="text-sm font-medium text-zinc-900 underline underline-offset-4 dark:text-zinc-50">
        Back to site map
      </Link>
    </div>
  );
}
