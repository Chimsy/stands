// Explicit locales keep server and client output identical during hydration.
const numberFormatter = new Intl.NumberFormat("en-US");
const priceFormatter = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 });

export const formatNumber = (value: number) => numberFormatter.format(value);
export const formatPrice = (value: number) => priceFormatter.format(value);

/** Statement figures arrive in minor units. */
export const formatCents = (cents: number) => priceFormatter.format(cents / 100);

/**
 * Thresholds for shortening a figure, largest first.
 */
const MAGNITUDES = [
  { unit: 1_000_000_000, suffix: "B" },
  { unit: 1_000_000, suffix: "M" },
  { unit: 1_000, suffix: "K" },
] as const;

/**
 * Axis ticks, where the exact cents would crowd out the shape of the data.
 *
 * Written out by hand rather than with `notation: "compact"`: that rounds
 * differently between Node's ICU and the browser's - the server renders `$0.0`
 * where the browser renders `$0` - and the mismatch fails hydration, which
 * silently kills every interaction on the page.
 */
export const formatCompactCents = (cents: number) => {
  const value = cents / 100;
  const magnitude = MAGNITUDES.find(({ unit }) => Math.abs(value) >= unit);

  if (!magnitude) {
    return priceFormatter.format(value);
  }

  const scaled = value / magnitude.unit;
  const decimals = Math.abs(scaled) < 10 && !Number.isInteger(scaled) ? 1 : 0;

  return `$${scaled.toFixed(decimals)}${magnitude.suffix}`;
};

const monthFormatter = new Intl.DateTimeFormat("en-GB", { month: "short" });

/** `2026-03` as `Mar`; the year is carried by the panel's subtitle instead. */
export const formatMonth = (month: string) => monthFormatter.format(new Date(`${month}-01T00:00:00`));

const dateFormatter = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" });

export const formatDate = (isoDate: string) => dateFormatter.format(new Date(`${isoDate}T00:00:00`));
