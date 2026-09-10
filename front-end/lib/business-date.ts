/**
 * Today, as the branch reckons it.
 *
 * The API validates sale and receipt dates against its own `APP_TIMEZONE`, so a
 * form prefilled from the browser's UTC date hands an agent working late the
 * previous day. This must stay in step with `APP_TIMEZONE` in the backend .env.
 */
const BUSINESS_TIMEZONE = process.env.BUSINESS_TIMEZONE ?? "Africa/Harare";

/** en-CA formats as YYYY-MM-DD, which is what a date input expects. */
const isoDate = new Intl.DateTimeFormat("en-CA", {
  timeZone: BUSINESS_TIMEZONE,
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
});

export const businessToday = (): string => isoDate.format(new Date());
