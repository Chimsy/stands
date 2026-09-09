// Explicit locales keep server and client output identical during hydration.
const numberFormatter = new Intl.NumberFormat("en-US");
const priceFormatter = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 });

export const formatNumber = (value: number) => numberFormatter.format(value);
export const formatPrice = (value: number) => priceFormatter.format(value);
