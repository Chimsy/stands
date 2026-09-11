/**
 * Axis ticks that land on numbers a reader recognises.
 *
 * A chart's top gridline is rounded up to the next 1, 2 or 5 times a power of
 * ten, so the labels read 0 / 50,000 / 100,000 rather than whatever the largest
 * bar happened to be.
 */
export interface Scale {
  max: number;
  ticks: number[];
}

const STEPS = [1, 2, 2.5, 5, 10];

export function niceScale(largest: number, divisions = 4): Scale {
  if (largest <= 0) {
    return { max: divisions, ticks: Array.from({ length: divisions + 1 }, (_, index) => index) };
  }

  const rough = largest / divisions;
  const magnitude = 10 ** Math.floor(Math.log10(rough));
  const step = (STEPS.find((candidate) => candidate * magnitude >= rough) ?? 10) * magnitude;
  const max = step * divisions;

  return { max, ticks: Array.from({ length: divisions + 1 }, (_, index) => index * step) };
}

/** Share of `total`, safe when there is nothing to divide by. */
export const share = (part: number, total: number): number => (total > 0 ? part / total : 0);
