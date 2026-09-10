// Explicit locales keep server and client output identical during hydration.
const numberFormatter = new Intl.NumberFormat("en-US");
const priceFormatter = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 });

export const formatNumber = (value: number) => numberFormatter.format(value);
export const formatPrice = (value: number) => priceFormatter.format(value);

/** Statement figures arrive in minor units. */
export const formatCents = (cents: number) => priceFormatter.format(cents / 100);

const dateFormatter = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" });

export const formatDate = (isoDate: string) => dateFormatter.format(new Date(`${isoDate}T00:00:00`));
