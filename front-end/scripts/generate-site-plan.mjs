#!/usr/bin/env node
/**
 * Generates the sample cadastral layout the backend seeds from:
 *   ../database/data/site-plan.json  - boundary, roads, zones and block labels
 *   ../database/data/stands.json     - individual numbered stands
 *
 * These are fixtures for `database/seeders/SitePlanSeeder.php`, not runtime
 * data. After regenerating, re-seed with `php artisan db:seed --class=SitePlanSeeder`.
 *
 * Geometry is authored in metres on an axis-aligned grid, clipped to the estate
 * boundary, then rotated so the plan sits on a surveyed bearing. Deterministic:
 * the same seed always produces the same layout.
 *
 * Run with: node scripts/generate-site-plan.mjs
 */
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

/**
 * Configuration comes from the environment so `generate-townships.mjs` can run
 * this once per township. The defaults reproduce the original Riverstone Park
 * fixture exactly.
 */
const env = (key, fallback) => process.env[key] ?? fallback;
const envInt = (key, fallback) => Number(env(key, fallback));
const envList = (key, fallback) => (process.env[key] ? JSON.parse(process.env[key]) : fallback);

const SLUG = env("PLAN_SLUG", "riverstone-park");
const DATA_DIR = resolve(dirname(fileURLToPath(import.meta.url)), "..", "..", "database", "data", SLUG);
const SEED = envInt("PLAN_SEED", 20260907);

// --- layout constants (metres) ---------------------------------------------
const PRECINCT = 238; // side of a residential superblock
const COLLECTOR = 16; // road between superblocks
const PERIMETER = 30; // arterial road around the estate
const MINOR = 10; // internal access road
const DEPTH = 26; // stand depth
const BAND = DEPTH * 2; // two rows of stands back to back
const FRONTAGE = 14; // nominal stand frontage
// Not configurable: the estate boundary and the AMENITIES keys below are
// authored against a 4x3 grid, so changing these would place superblocks
// outside the boundary.
const COLS = 4;
const ROWS = 3;
const ORIGIN = { x: 40, y: 40 };
const PITCH = PRECINCT + COLLECTOR;
const NORTH_ROTATION = envInt("PLAN_ROTATION", 5); // degrees the grid is rotated off true north

// --- deterministic randomness ----------------------------------------------
function mulberry32(seed) {
  let a = seed;
  return function random() {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
const rng = mulberry32(SEED);

// --- geometry helpers -------------------------------------------------------
const rect = (x0, y0, x1, y1) => [
  { x: x0, y: y0 },
  { x: x1, y: y0 },
  { x: x1, y: y1 },
  { x: x0, y: y1 },
];

function signedArea(points) {
  let sum = 0;
  for (let i = 0; i < points.length; i++) {
    const a = points[i];
    const b = points[(i + 1) % points.length];
    sum += a.x * b.y - b.x * a.y;
  }
  return sum / 2;
}

const area = (points) => Math.abs(signedArea(points));

function centroid(points) {
  const a = signedArea(points);
  if (Math.abs(a) < 1e-6) {
    const n = points.length;
    return {
      x: points.reduce((s, p) => s + p.x, 0) / n,
      y: points.reduce((s, p) => s + p.y, 0) / n,
    };
  }
  let cx = 0;
  let cy = 0;
  for (let i = 0; i < points.length; i++) {
    const p = points[i];
    const q = points[(i + 1) % points.length];
    const cross = p.x * q.y - q.x * p.y;
    cx += (p.x + q.x) * cross;
    cy += (p.y + q.y) * cross;
  }
  return { x: cx / (6 * a), y: cy / (6 * a) };
}

/** Convex hull (monotone chain), returned counter-clockwise in screen space. */
function convexHull(points) {
  const sorted = [...points].sort((a, b) => a.x - b.x || a.y - b.y);
  const cross = (o, a, b) => (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
  const build = (list) => {
    const stack = [];
    for (const point of list) {
      while (stack.length >= 2 && cross(stack[stack.length - 2], stack[stack.length - 1], point) <= 0) stack.pop();
      stack.push(point);
    }
    stack.pop();
    return stack;
  };
  return [...build(sorted), ...build([...sorted].reverse())];
}

/** Sutherland-Hodgman clip of any polygon against a convex clip polygon. */
function clipToConvex(subject, clip) {
  const inward = signedArea(clip) > 0 ? 1 : -1;
  let output = subject;

  for (let i = 0; i < clip.length && output.length; i++) {
    const a = clip[i];
    const b = clip[(i + 1) % clip.length];
    const side = (p) => inward * ((b.x - a.x) * (p.y - a.y) - (b.y - a.y) * (p.x - a.x));
    const input = output;
    output = [];

    for (let j = 0; j < input.length; j++) {
      const p = input[j];
      const q = input[(j + 1) % input.length];
      const sp = side(p);
      const sq = side(q);
      if (sp >= 0) output.push(p);
      if ((sp >= 0) !== (sq >= 0)) {
        const t = sp / (sp - sq);
        output.push({ x: p.x + (q.x - p.x) * t, y: p.y + (q.y - p.y) * t });
      }
    }
  }
  return output;
}

// --- estate boundary --------------------------------------------------------
const BOUNDARY = convexHull([
  { x: 90, y: 24 },
  { x: 520, y: 0 },
  { x: 1010, y: 58 },
  { x: 1096, y: 360 },
  { x: 1052, y: 690 },
  { x: 700, y: 812 },
  { x: 240, y: 790 },
  { x: 22, y: 520 },
  { x: 6, y: 200 },
]);

// --- road network -----------------------------------------------------------
const ARTERIAL_NAMES = envList("PLAN_ARTERIAL_NAMES", ["Chiremba Drive", "Twentydales Road", "Nyanga Drive", "Mazowe Drive"]);
const COLLECTOR_NAMES = envList("PLAN_COLLECTOR_NAMES", [
  "Msasa Road",
  "Mukuyu Road",
  "Muhacha Road",
  "Mubvamaropa Road",
  "Muonde Road",
]);
const MINOR_NAMES = envList("PLAN_MINOR_NAMES", [
  "Jacaranda Close",
  "Flamboyant Way",
  "Munhondo Close",
  "Mutamba Way",
  "Muzhanje Close",
  "Mutohwe Way",
  "Mubvee Close",
  "Muhwiti Way",
  "Mutsamvi Close",
  "Mutarara Way",
  "Mukamba Close",
  "Mutiti Way",
  "Muchakata Close",
  "Mutsvairo Way",
  "Mutowa Close",
  "Muzeze Way",
  "Muunga Close",
  "Mutufu Way",
  "Mubvunzwa Close",
  "Mushuma Way",
  "Mutundu Close",
  "Mupfuti Way",
  "Muwonde Close",
  "Mukwa Way",
  "Mutsviri Close",
  "Musasa Grove",
  "Mupangara Close",
  "Mutongoni Way",
  "Muzhanje Grove",
  "Mubayamhondoro Close",
  "Mutswiri Way",
  "Mudonda Close",
]);

const GRID = {
  left: ORIGIN.x,
  top: ORIGIN.y,
  right: ORIGIN.x + COLS * PITCH - COLLECTOR,
  bottom: ORIGIN.y + ROWS * PITCH - COLLECTOR,
};

const roads = [];
let minorNameIndex = 0;
const nextMinorName = () => MINOR_NAMES[minorNameIndex++ % MINOR_NAMES.length];

function addRoad({ id, name, kind, x0, y0, x1, y1, orientation }) {
  const clipped = clipToConvex(rect(x0, y0, x1, y1), BOUNDARY);
  if (clipped.length < 3 || area(clipped) < 200) return null;
  const label = centroid(clipped);
  roads.push({
    id,
    name,
    kind,
    orientation,
    width: orientation === "h" ? y1 - y0 : x1 - x0,
    points: clipped,
    label,
  });
  return { name, span: { x0, y0, x1, y1 } };
}

// Perimeter arterials
addRoad({ id: "road-arterial-n", name: ARTERIAL_NAMES[0], kind: "arterial", orientation: "h", x0: -40, y0: GRID.top - PERIMETER, x1: 1140, y1: GRID.top });
addRoad({ id: "road-arterial-e", name: ARTERIAL_NAMES[1], kind: "arterial", orientation: "v", x0: GRID.right, y0: -40, x1: GRID.right + PERIMETER, y1: 880 });
addRoad({ id: "road-arterial-s", name: ARTERIAL_NAMES[2], kind: "arterial", orientation: "h", x0: -40, y0: GRID.bottom, x1: 1140, y1: GRID.bottom + PERIMETER });
addRoad({ id: "road-arterial-w", name: ARTERIAL_NAMES[3], kind: "arterial", orientation: "v", x0: GRID.left - PERIMETER, y0: -40, x1: GRID.left, y1: 880 });

// Collector roads between superblocks. Names are reused by the stands fronting them.
const verticalRoadNames = [ARTERIAL_NAMES[3]];
for (let c = 0; c < COLS - 1; c++) {
  const x0 = ORIGIN.x + c * PITCH + PRECINCT;
  const name = COLLECTOR_NAMES[c % COLLECTOR_NAMES.length];
  addRoad({ id: `road-collector-v-${c}`, name, kind: "collector", orientation: "v", x0, y0: GRID.top, x1: x0 + COLLECTOR, y1: GRID.bottom });
  verticalRoadNames.push(name);
}
verticalRoadNames.push(ARTERIAL_NAMES[1]);

const horizontalRoadNames = [ARTERIAL_NAMES[0]];
for (let r = 0; r < ROWS - 1; r++) {
  const y0 = ORIGIN.y + r * PITCH + PRECINCT;
  const name = COLLECTOR_NAMES[(r + COLS) % COLLECTOR_NAMES.length];
  addRoad({ id: `road-collector-h-${r}`, name, kind: "collector", orientation: "h", x0: GRID.left, y0, x1: GRID.right, y1: y0 + COLLECTOR });
  horizontalRoadNames.push(name);
}
horizontalRoadNames.push(ARTERIAL_NAMES[2]);

// --- superblocks ------------------------------------------------------------
const AMENITIES = {
  "1,1": [{ name: "Open Space", kind: "open-space", share: 1 }],
  "3,2": [
    { name: "Primary School", kind: "institutional", share: 0.58 },
    { name: "Shopping Centre", kind: "commercial", share: 0.42 },
  ],
  "0,2": [
    { name: "Creche", kind: "institutional", share: 0.34 },
    { name: "Church", kind: "institutional", share: 0.3 },
  ],
};

const BLOCK_LETTERS = "ABCDEFGHIJKL";
const zones = [];
const blocks = [];
const stands = [];

let standNumber = envInt("PLAN_FIRST_STAND", 2001);
let blockIndex = 0;

/** Splits a run of `length` metres into stand frontages that sum exactly. */
function splitFrontages(length) {
  const count = Math.max(1, Math.round(length / FRONTAGE));
  const base = length / count;
  const cuts = [0];
  for (let i = 1; i < count; i++) {
    const jitter = (rng() - 0.5) * 2.4;
    cuts.push(Math.min(length - 6, Math.max(cuts[i - 1] + 8, i * base + jitter)));
  }
  cuts.push(length);
  return cuts;
}

function pushStand(points, { road, block }) {
  const clipped = clipToConvex(points, BOUNDARY);
  const nominal = area(points);
  const clippedArea = area(clipped);
  // Drop slivers left by the boundary; keep genuinely odd-shaped edge stands.
  if (clipped.length < 3 || clippedArea < nominal * 0.45 || clippedArea < 150) return false;

  const c = centroid(clipped);
  const distanceFromEntrance = Math.min(
    1,
    Math.hypot(c.x - GRID.right, c.y - GRID.bottom) / Math.hypot(GRID.right - GRID.left, GRID.bottom - GRID.top),
  );

  const soldProbability = 0.78 * Math.pow(1 - distanceFromEntrance, 1.3);
  const progressProbability = 0.3 * Math.exp(-Math.pow((distanceFromEntrance - 0.42) / 0.22, 2));
  const roll = rng();
  const status = roll < soldProbability ? "sold" : roll < soldProbability + progressProbability ? "in-progress" : "available";

  const areaSqm = Math.round(clippedArea);
  const rate = 26 + 12 * (1 - distanceFromEntrance);
  const daysAgo = Math.floor(rng() * 210);
  const updatedAt = new Date(Date.UTC(2026, 8, 7) - daysAgo * 86400000).toISOString().slice(0, 10);

  stands.push({
    id: `stand-${standNumber}`,
    standNumber: String(standNumber),
    status,
    block,
    road,
    areaSqm,
    price: Math.round((areaSqm * rate) / 100) * 100,
    updatedAt,
    centroid: { x: round(c.x), y: round(c.y) },
    points: clipped.map((p) => ({ x: round(p.x), y: round(p.y) })),
  });
  standNumber += 1;
  return true;
}

const round = (value) => Math.round(value * 10) / 10;

function buildResidentialPrecinct(col, row) {
  const x0 = ORIGIN.x + col * PITCH;
  const y0 = ORIGIN.y + row * PITCH;
  const x1 = x0 + PRECINCT;
  const y1 = y0 + PRECINCT;
  const block = `Block ${BLOCK_LETTERS[blockIndex++]}`;
  const horizontalBands = (col + row) % 2 === 0;

  // Roads bounding this precinct, used to name the stands that front them.
  const edgeRoads = horizontalBands
    ? { start: horizontalRoadNames[row], end: horizontalRoadNames[row + 1] }
    : { start: verticalRoadNames[col], end: verticalRoadNames[col + 1] };

  const spanStart = horizontalBands ? y0 : x0;
  const spanEnd = horizontalBands ? y1 : x1;
  const runStart = horizontalBands ? x0 : y0;
  const runLength = (horizontalBands ? x1 : y1) - runStart;

  const bands = [];
  let cursor = spanStart;
  while (cursor + BAND <= spanEnd + 0.5) {
    const bandStart = cursor;
    cursor += BAND;
    let trailingRoad = null;
    if (cursor + MINOR + BAND <= spanEnd + 0.5) {
      trailingRoad = { start: cursor, end: cursor + MINOR, name: nextMinorName() };
      cursor += MINOR;
    }
    bands.push({ start: bandStart, trailingRoad });
  }

  bands.forEach((band, index) => {
    const roadBefore = index === 0 ? edgeRoads.start : bands[index - 1].trailingRoad.name;
    const roadAfter = band.trailingRoad ? band.trailingRoad.name : edgeRoads.end;

    if (band.trailingRoad) {
      const { start, end, name } = band.trailingRoad;
      addRoad({
        id: `road-minor-${col}-${row}-${index}`,
        name,
        kind: "minor",
        orientation: horizontalBands ? "h" : "v",
        x0: horizontalBands ? runStart : start,
        y0: horizontalBands ? start : runStart,
        x1: horizontalBands ? runStart + runLength : end,
        y1: horizontalBands ? end : runStart + runLength,
      });
    }

    const cuts = splitFrontages(runLength);
    const frontRow = [];
    const backRow = [];

    for (let i = 0; i < cuts.length - 1; i++) {
      const a = runStart + cuts[i];
      const b = runStart + cuts[i + 1];
      if (horizontalBands) {
        frontRow.push({ points: rect(a, band.start, b, band.start + DEPTH), road: roadBefore });
        backRow.push({ points: rect(a, band.start + DEPTH, b, band.start + BAND), road: roadAfter });
      } else {
        frontRow.push({ points: rect(band.start, a, band.start + DEPTH, b), road: roadBefore });
        backRow.push({ points: rect(band.start + DEPTH, a, band.start + BAND, b), road: roadAfter });
      }
    }

    // Numbering runs down one side of the band and back up the other, the way
    // stands are numbered on a surveyed plan.
    for (const stand of frontRow) pushStand(stand.points, { road: stand.road, block });
    for (const stand of backRow.reverse()) pushStand(stand.points, { road: stand.road, block });
  });

  const outline = clipToConvex(rect(x0, y0, x1, y1), BOUNDARY);
  if (outline.length >= 3) {
    const c = centroid(outline);
    blocks.push({ id: `block-${col}-${row}`, name: block, x: round(c.x), y: round(c.y) });
  }
}

function buildAmenityPrecinct(col, row, parts) {
  const x0 = ORIGIN.x + col * PITCH;
  const y0 = ORIGIN.y + row * PITCH;
  let cursor = y0;

  parts.forEach((part, index) => {
    const height = PRECINCT * part.share;
    const outline = clipToConvex(rect(x0, cursor, x0 + PRECINCT, cursor + height), BOUNDARY);
    cursor += height + (index < parts.length - 1 ? MINOR : 0);
    if (outline.length < 3 || area(outline) < 400) return;
    const c = centroid(outline);
    zones.push({
      id: `zone-${col}-${row}-${index}`,
      name: part.name,
      kind: part.kind,
      points: outline.map((p) => ({ x: round(p.x), y: round(p.y) })),
      label: { x: round(c.x), y: round(c.y) },
    });
  });

  // Any remainder of an amenity superblock still gets residential frontage.
  const used = parts.reduce((sum, part) => sum + part.share, 0);
  if (used < 0.95) {
    const remainderTop = y0 + PRECINCT * used + MINOR;
    const block = `Block ${BLOCK_LETTERS[blockIndex++]}`;
    let bandTop = remainderTop;
    while (bandTop + BAND <= y0 + PRECINCT + 0.5) {
      const cuts = splitFrontages(PRECINCT);
      const front = [];
      const back = [];
      for (let i = 0; i < cuts.length - 1; i++) {
        const a = x0 + cuts[i];
        const b = x0 + cuts[i + 1];
        front.push(rect(a, bandTop, b, bandTop + DEPTH));
        back.push(rect(a, bandTop + DEPTH, b, bandTop + BAND));
      }
      const roadName = horizontalRoadNames[row + 1];
      for (const points of front) pushStand(points, { road: roadName, block });
      for (const points of back.reverse()) pushStand(points, { road: roadName, block });
      bandTop += BAND + MINOR;
    }
    const outline = clipToConvex(rect(x0, remainderTop, x0 + PRECINCT, y0 + PRECINCT), BOUNDARY);
    if (outline.length >= 3) {
      const c = centroid(outline);
      blocks.push({ id: `block-${col}-${row}`, name: block, x: round(c.x), y: round(c.y) });
    }
  }
}

for (let row = 0; row < ROWS; row++) {
  for (let col = 0; col < COLS; col++) {
    const amenity = AMENITIES[`${col},${row}`];
    if (amenity) buildAmenityPrecinct(col, row, amenity);
    else buildResidentialPrecinct(col, row);
  }
}

// --- rotate the finished plan onto its survey bearing -----------------------
const theta = (NORTH_ROTATION * Math.PI) / 180;
const cos = Math.cos(theta);
const sin = Math.sin(theta);
const pivot = { x: 545, y: 400 };

function rotatePoint(p) {
  const dx = p.x - pivot.x;
  const dy = p.y - pivot.y;
  return { x: pivot.x + dx * cos - dy * sin, y: pivot.y + dx * sin + dy * cos };
}

const everyPoint = [];
function transform(points) {
  const rotated = points.map((p) => {
    const r = rotatePoint(p);
    everyPoint.push(r);
    return r;
  });
  return rotated;
}

const boundaryRotated = transform(BOUNDARY);
for (const road of roads) road.points = transform(road.points);
for (const zone of zones) zone.points = transform(zone.points);
for (const stand of stands) stand.points = transform(stand.points);

const MARGIN = 24;
const minX = Math.min(...everyPoint.map((p) => p.x)) - MARGIN;
const minY = Math.min(...everyPoint.map((p) => p.y)) - MARGIN;
const maxX = Math.max(...everyPoint.map((p) => p.x)) + MARGIN;
const maxY = Math.max(...everyPoint.map((p) => p.y)) + MARGIN;

function normalise(points) {
  return points.map((p) => ({ x: round(p.x - minX), y: round(p.y - minY) }));
}
function normalisePoint(p) {
  const r = rotatePoint(p);
  return { x: round(r.x - minX), y: round(r.y - minY) };
}

const sitePlan = {
  name: env("PLAN_NAME", "Riverstone Park Estate"),
  subtitle: env("PLAN_SUBTITLE", "Proposed medium density residential township"),
  authority: env("PLAN_AUTHORITY", "City of Harare"),
  note: "Sample layout for demonstration - not a survey document",
  northRotation: NORTH_ROTATION,
  bounds: { width: round(maxX - minX), height: round(maxY - minY) },
  boundary: normalise(boundaryRotated),
  roads: roads.map((road) => ({
    id: road.id,
    name: road.name,
    kind: road.kind,
    width: Math.round(road.width),
    points: normalise(road.points),
    label: { ...normalisePoint(road.label), angle: road.orientation === "h" ? NORTH_ROTATION : NORTH_ROTATION - 90 },
  })),
  zones: zones.map((zone) => ({ ...zone, points: normalise(zone.points), label: normalisePoint(zone.label) })),
  blocks: blocks.map((block) => ({ ...block, ...normalisePoint(block) })),
};

const standsOut = stands.map((stand) => ({
  ...stand,
  points: normalise(stand.points),
  centroid: normalisePoint(stand.centroid),
}));

mkdirSync(DATA_DIR, { recursive: true });
writeFileSync(resolve(DATA_DIR, "site-plan.json"), JSON.stringify(sitePlan));
writeFileSync(resolve(DATA_DIR, "stands.json"), JSON.stringify(standsOut));

const counts = standsOut.reduce((acc, stand) => ({ ...acc, [stand.status]: (acc[stand.status] ?? 0) + 1 }), {});
const totalArea = standsOut.reduce((sum, stand) => sum + stand.areaSqm, 0);
console.log(`\n${sitePlan.name}  ->  database/data/${SLUG}/`);
console.log(`stands:     ${standsOut.length}`);
console.log(`statuses:   ${JSON.stringify(counts)}`);
console.log(`blocks:     ${sitePlan.blocks.length}, roads: ${sitePlan.roads.length}, zones: ${sitePlan.zones.length}`);
console.log(`plan size:  ${sitePlan.bounds.width}m x ${sitePlan.bounds.height}m`);
console.log(`stand area: avg ${Math.round(totalArea / standsOut.length)} sqm, total ${(totalArea / 10000).toFixed(1)} ha`);
console.log(`numbers:    ${standsOut[0].standNumber} - ${standsOut[standsOut.length - 1].standNumber}`);
