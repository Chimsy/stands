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
      className="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 dark:bg-brand-400 dark:text-brand-950 dark:hover:bg-brand-300"
    >
      Download receipt
    </button>
  );
}
