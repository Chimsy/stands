/**
 * Beyond Reality identity, drawn rather than bitmapped so it stays sharp at
 * favicon size and on a printed receipt, and so the two inks can be re-stepped
 * for the dark surface instead of sitting on it as a pale rectangle.
 *
 * `tone="fixed"` is for the receipt, which is a document the buyer keeps: it is
 * white paper in either theme, so the logo there must not follow the viewer's.
 * The standalone copies in `public/logo.svg` and `app/icon.svg` carry the same
 * literal hex for the same shapes, because a detached SVG has no page to
 * inherit `--brand-*` from.
 */

type Tone = "auto" | "fixed";

const INK: Record<Tone, { green: string; blue: string; sky: string }> = {
  auto: { green: "var(--brand-green)", blue: "var(--brand-blue)", sky: "var(--brand-sky)" },
  fixed: { green: "#12873f", blue: "#1179bd", sky: "#56b3ec" },
};

export function BrandMark({
  className = "h-9 w-9",
  tone = "auto",
}: {
  className?: string;
  tone?: Tone;
}) {
  const ink = INK[tone];
  const gradientId = `br-sky-${tone}`;
  const clipId = `br-roundel-${tone}`;

  return (
    <svg viewBox="0 0 64 64" role="presentation" aria-hidden="true" className={className} fill="none">
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={ink.sky} />
          <stop offset="100%" stopColor={ink.blue} />
        </linearGradient>
        <clipPath id={clipId}>
          <circle cx="32" cy="32" r="25" />
        </clipPath>
      </defs>

      {/* The swoosh that wraps the roundel from the left round to the right. */}
      <path d="M6.6 41.2a27 27 0 0 0 50.8 0" stroke={ink.green} strokeWidth="5.5" strokeLinecap="round" />

      <circle cx="32" cy="32" r="25" fill={`url(#${gradientId})`} />

      {/* Hill, clipped to the roundel so it reads as ground inside the sky. */}
      <g clipPath={`url(#${clipId})`}>
        <path d="M2 34c10 8 20 8 30 4s20-4 32 2v30H2Z" fill={ink.green} />
      </g>

      {/* House: white against the sky, outlined so it survives a mono print. */}
      <g fill="#ffffff" stroke={ink.blue} strokeWidth="1.4" strokeLinejoin="round">
        <path d="M38 16h3.5v9H38z" />
        <path d="M32 15 45.5 28H18.5z" />
        <path d="M22.5 27.5h19v12h-19z" />
      </g>
      <path d="M29 32h6v7.5h-6z" fill={ink.blue} />
    </svg>
  );
}

/**
 * The full lock-up. `compact` drops the tagline for the app header, where the
 * three lines of the full mark would out-shout the navigation beside it.
 */
export function BrandLogo({
  compact = false,
  tone = "auto",
  className = "",
}: {
  compact?: boolean;
  tone?: Tone;
  className?: string;
}) {
  const fixed = tone === "fixed";

  return (
    <span className={`inline-flex items-center gap-2.5 ${className}`}>
      <BrandMark tone={tone} className={compact ? "h-8 w-8 shrink-0" : "h-12 w-12 shrink-0"} />

      <span className="flex flex-col leading-none">
        <span className={`font-brand font-bold tracking-tight ${compact ? "text-base" : "text-2xl"}`}>
          <span className={fixed ? "text-brand-700" : "text-brand-700 dark:text-brand-400"}>BEYOND</span>{" "}
          <span className={fixed ? "text-marine-700" : "text-marine-700 dark:text-marine-400"}>REALITY</span>
        </span>

        {!compact && (
          <span
            className={`mt-1 font-brand text-[0.6rem] font-semibold tracking-[0.18em] uppercase ${
              fixed ? "text-marine-700" : "text-marine-700 dark:text-marine-300"
            }`}
          >
            Housing{" "}
            <span className={fixed ? "text-brand-600" : "text-brand-600 dark:text-brand-400"}>&amp;</span> Land
            Developers
          </span>
        )}
      </span>
    </span>
  );
}
