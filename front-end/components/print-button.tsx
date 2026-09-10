"use client";

/**
 * Downloading is the browser's own print dialog with "Save as PDF" chosen, so
 * the receipt becomes a real PDF without the app shipping a PDF renderer.
 */
export function PrintButton() {
  return (
    <button
      type="button"
      onClick={() => window.print()}
      className="rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
    >
      Download receipt
    </button>
  );
}
