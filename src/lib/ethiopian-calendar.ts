/**
 * ============================================================
 * Ethiopian calendar & clock — the one place this site converts dates
 * ============================================================
 * Ported from the FKSS platform engine (admin/backend/ethiopian_date.php,
 * admin/js/wbws-calendar.js, Mobile/.../ethiopian_calendar.dart) so the salon
 * and the school read dates the same way. The algorithm is kept identical to
 * those sources on purpose — do not "improve" it in isolation.
 *
 * HOW THE SITE USES DATES (same rule as FKSS ADR-0008):
 *   1. STORAGE AND THE WIRE ARE GREGORIAN. `/api/v1/calendar` answers
 *      "2026-11-24" and `/api/v1/bookings` accepts the same. Date columns,
 *      slots, ".ics" files and the panel's own maths stay Gregorian.
 *   2. EVERYTHING A PERSON READS IS ETHIOPIAN. The date is derived from the
 *      stored Gregorian value at render time, here, in the browser.
 *   3. TIMES ARE SHOWN BOTH WAYS, Ethiopian clock first: "3:00 ሰዓት (09:00)".
 *      Ethiopian 12 o'clock is 6:00 — the day starts six hours earlier.
 *
 * ACCURACY: exact by construction, with no horizon.
 *
 * FKSS derives Meskerem 1 from the *Gregorian* leap rule — "11 September, or
 * the 12th when February following is a leap day". That quietly assumes the
 * Gregorian year and the Ethiopian year stay in step, which they do until
 * Gregorian 2100, the first century year that is not a leap year. Ethiopian
 * 2092 is a full 365-day year that opens on 12 September 2099 and closes on
 * 11 September 2100, so from 2100 onward FKSS is a day out, and it never
 * recovers — by 2400 its Meskerem 1 lands on 15 September.
 *
 * This port counts days inside the Ethiopian calendar instead: a year is 365
 * days, or 366 when the year leaves a remainder of 3 in the four-year cycle
 * (that is where Pagume 6 lives), anchored on 1 Meskerem 2015 EC = 11
 * September 2022. Both directions are Julian-day arithmetic against that one
 * anchor, so round trips are exact, Pagume 6 appears exactly once every four
 * years, and Meskerem 1 is right forever. The tests prove this agrees with the
 * FKSS Gregorian-derived rule for every year 1900–2091 — every date this salon
 * can reach — and that it is the FKSS rule, not this one, that drifts after.
 * ============================================================
 */

export type EthiopianDate = { year: number; month: number; day: number };

/** 1 = Meskerem … 13 = Pagume. Index 0 is unused, as in the source. */
export const EC_MONTHS_AM = [
  "",
  "መስከረም",
  "ጥቅምት",
  "ህዳር",
  "ታህሳስ",
  "ጥር",
  "የካቲት",
  "መጋቢት",
  "ሚያዝያ",
  "ግንቦት",
  "ሰኔ",
  "ሐምሌ",
  "ነሐሴ",
  "ጳጉሜ",
] as const;

export const EC_MONTHS_EN = [
  "",
  "Meskerem",
  "Tikimt",
  "Hidar",
  "Tahsas",
  "Tir",
  "Yekatit",
  "Megabit",
  "Miyazya",
  "Ginbot",
  "Sene",
  "Hamle",
  "Nehasse",
  "Pagume",
] as const;

export const EC_MONTHS_SHORT = [
  "",
  "Mes",
  "Tik",
  "Hid",
  "Tah",
  "Tir",
  "Yek",
  "Meg",
  "Miy",
  "Gin",
  "Sen",
  "Ham",
  "Neh",
  "Pag",
] as const;

/** Sunday first, matching both calendars — the weekday itself never shifts. */
export const EC_DAYS_AM = ["እሑድ", "ሰኞ", "ማክሰኞ", "ረቡዕ", "ሐሙስ", "ዓርብ", "ቅዳሜ"] as const;
export const EC_DAYS_SHORT_AM = ["እሑ", "ሰኞ", "ማክ", "ረቡ", "ሐሙ", "ዓር", "ቅዳ"] as const;

/** The Ethiopian day periods, keyed by the Gregorian hour that starts them. */
export type DayPeriod = "ጠዋት" | "ከሰዓት" | "ማታ" | "ሌሊት";

// ─────────────────────────── conversion ───────────────────────────

/**
 * 1 Meskerem 2015 EC = 11 September 2022 — an Ethiopian public holiday, and
 * the only date this module asserts. Everything else is counted from it.
 */
const ETHIOPIAN_ANCHOR = { year: 2015, month: 1, day: 1 };
const GREGORIAN_ANCHOR = { year: 2022, month: 9, day: 11 };

/** Julian Day Number of a proleptic Gregorian date. */
function gregorianToJdn(year: number, month: number, day: number): number {
  const a = Math.floor((14 - month) / 12);
  const y = year + 4800 - a;
  const m = month + 12 * a - 3;

  return (
    day +
    Math.floor((153 * m + 2) / 5) +
    365 * y +
    Math.floor(y / 4) -
    Math.floor(y / 100) +
    Math.floor(y / 400) -
    32045
  );
}

function jdnToGregorian(jdn: number): { year: number; month: number; day: number } {
  const a = jdn + 32044;
  const b = Math.floor((4 * a + 3) / 146097);
  const c = a - Math.floor((146097 * b) / 4);
  const d = Math.floor((4 * c + 3) / 1461);
  const e = c - Math.floor((1461 * d) / 4);
  const m = Math.floor((5 * e + 2) / 153);

  return {
    year: 100 * b + d - 4800 + Math.floor(m / 10),
    month: m + 3 - 12 * Math.floor(m / 10),
    day: e - Math.floor((153 * m + 2) / 5) + 1,
  };
}

/**
 * Days elapsed in the Ethiopian calendar since 1 Meskerem 1 EC.
 *
 * Twelve months of thirty days, then Pagume: the year gains a day when it is
 * the fourth in the cycle, which is why the leap test is on the year before.
 */
function ethiopianDayNumber(date: EthiopianDate): number {
  return 365 * (date.year - 1) + Math.floor(date.year / 4) + 30 * (date.month - 1) + (date.day - 1);
}

const ETHIOPIAN_EPOCH =
  gregorianToJdn(GREGORIAN_ANCHOR.year, GREGORIAN_ANCHOR.month, GREGORIAN_ANCHOR.day) -
  ethiopianDayNumber(ETHIOPIAN_ANCHOR);

/** Meskerem 1 of an Ethiopian year, as a local-midnight Date. */
export function ethiopianNewYear(ethiopianYear: number): Date {
  const gregorian = jdnToGregorian(
    ETHIOPIAN_EPOCH + ethiopianDayNumber({ year: ethiopianYear, month: 1, day: 1 }),
  );

  return new Date(gregorian.year, gregorian.month - 1, gregorian.day);
}

/** Local midnight — the whole module works in calendar days, never instants. */
function atMidnight(input: Date | string): Date | null {
  const date = input instanceof Date ? new Date(input.getTime()) : new Date(input);

  if (Number.isNaN(date.getTime())) return null;

  return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

/** `null` when the input is not a readable date, so callers must decide. */
export function toEthiopian(input: Date | string): EthiopianDate | null {
  const date = atMidnight(input);

  if (date === null) return null;

  const elapsed =
    gregorianToJdn(date.getFullYear(), date.getMonth() + 1, date.getDate()) - ETHIOPIAN_EPOCH;

  // A close estimate, then at most a step either way — no assumption about
  // which Gregorian year holds Meskerem 1, so nothing breaks at 2100.
  let year = Math.floor(elapsed / 365.25) + 1;

  while (ethiopianDayNumber({ year, month: 1, day: 1 }) > elapsed) year -= 1;
  while (ethiopianDayNumber({ year: year + 1, month: 1, day: 1 }) <= elapsed) year += 1;

  const dayOfYear = elapsed - ethiopianDayNumber({ year, month: 1, day: 1 });

  return { year, month: Math.floor(dayOfYear / 30) + 1, day: (dayOfYear % 30) + 1 };
}

export function toGregorian(date: EthiopianDate): Date {
  const gregorian = jdnToGregorian(ETHIOPIAN_EPOCH + ethiopianDayNumber(date));

  return new Date(gregorian.year, gregorian.month - 1, gregorian.day);
}

/** Pagume has 5 days, or 6 when the Ethiopian year leaves a remainder of 3. */
export function daysInEthiopianMonth(month: number, year: number): number {
  if (month >= 1 && month <= 12) return 30;
  if (month === 13) return year % 4 === 3 ? 6 : 5;

  return 30;
}

export function isValidEthiopianDate(date: EthiopianDate): boolean {
  const { year, month, day } = date;

  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return false;
  if (year < 1 || month < 1 || month > 13 || day < 1) return false;

  return day <= daysInEthiopianMonth(month, year);
}

/** "2018-02-12" — the Ethiopian calendar's own ISO-ish ordering. */
export function toEthiopianIso(date: EthiopianDate): string {
  return `${date.year}-${String(date.month).padStart(2, "0")}-${String(date.day).padStart(2, "0")}`;
}

/** Reads "2018-02-12" (or "2018/2/12"). `null` when it is not a real date. */
export function parseEthiopianIso(value: string): EthiopianDate | null {
  const match = /^(\d{1,4})[-/](\d{1,2})[-/](\d{1,2})$/.exec(value.trim());

  if (match === null) return null;

  const date = { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) };

  return isValidEthiopianDate(date) ? date : null;
}

// ─────────────────────────── formatting ───────────────────────────

export type EthiopianDateStyle = "short" | "medium" | "long" | "full" | "amharic" | "month-year";

function formatFromParts(date: EthiopianDate, weekday: number, style: EthiopianDateStyle): string {
  const { year, month, day } = date;

  switch (style) {
    case "short":
      return `${String(day).padStart(2, "0")}/${String(month).padStart(2, "0")}/${year}`;
    case "medium":
      return `${EC_MONTHS_SHORT[month]} ${day}, ${year}`;
    case "long":
      return `${EC_MONTHS_EN[month]} ${day}, ${year} EC`;
    case "full":
      return `${EC_DAYS_AM[weekday]} ${EC_MONTHS_AM[month]} ${day}, ${year} ዓ.ም`;
    case "amharic":
      return `${EC_MONTHS_AM[month]} ${day}, ${year} ዓ.ም`;
    case "month-year":
      return `${EC_MONTHS_EN[month]} ${year}`;
    default:
      return `${EC_MONTHS_SHORT[month]} ${day}, ${year}`;
  }
}

/**
 * The Ethiopian reading of a stored Gregorian date.
 *
 *   'short'   12/02/2018        'medium'  Tik 12, 2018
 *   'long'    Tikimt 12, 2018 EC
 *   'full'    ማክሰኞ ጥቅምት 12, 2018 ዓ.ም
 *   'amharic' ጥቅምት 12, 2018 ዓ.ም
 */
export function formatEthiopian(
  input: Date | string,
  style: EthiopianDateStyle = "medium",
  fallback = "—",
): string {
  const date = atMidnight(input);

  if (date === null) return fallback;

  const ethiopian = toEthiopian(date);

  if (ethiopian === null) return fallback;

  return formatFromParts(ethiopian, date.getDay(), style);
}

/** The Gregorian counterpart, kept small — the cross-check beside an EC date. */
export function formatGregorian(input: Date | string, fallback = "—"): string {
  const date = atMidnight(input);

  if (date === null) return fallback;

  return date.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
}

/** "1:00 ሰዓት – 6:30 ሰዓት (07:00 – 12:30)" for a run of days, unchanged. */
export function ethiopianToday(style: EthiopianDateStyle = "long"): string {
  return formatEthiopian(new Date(), style);
}

export function currentEthiopianYear(): number {
  return toEthiopian(new Date())?.year ?? 0;
}

export function currentEthiopianMonth(): number {
  return toEthiopian(new Date())?.month ?? 0;
}

// ─────────────────────────── the clock ───────────────────────────

/**
 * Ethiopian time runs twelve hours from dawn: 6:00 is Ethiopian 12 o'clock,
 * 7:00 is 1:00, noon is 6:00 and 6 in the evening is 12:00 again. The period
 * word (ጠዋት / ከሰዓት / ማታ / ሌሊት) is what tells the two twelves apart.
 *
 * NOTE: the FKSS sources convert dates only. This clock is the salon's
 * addition, kept beside the ported maths so both live in one file.
 */
export type EthiopianClock = {
  hour: number;
  minute: number;
  period: DayPeriod;
  /** "3:00 ሰዓት" — Ethiopian o'clock, no period word. */
  label: string;
  /** "3:00 ሰዓት ጠዋት" — Ethiopian o'clock with the period word. */
  full: string;
};

function periodFor(gregorianHour: number): DayPeriod {
  if (gregorianHour >= 6 && gregorianHour < 12) return "ጠዋት";
  if (gregorianHour >= 12 && gregorianHour < 18) return "ከሰዓት";
  if (gregorianHour >= 18) return "ማታ";

  return "ሌሊት";
}

/**
 * "09:00" → 3:00 ሰዓት ጠዋት. Accepts "09:00", "09:00:00" or "9:00".
 * `null` when the string is not a time, so callers can show the raw value.
 */
export function toEthiopianClock(time: string): EthiopianClock | null {
  const match = /^(\d{1,2}):(\d{2})(?::\d{2})?$/.exec(time.trim());

  if (match === null) return null;

  const gregorianHour = Number(match[1]);
  const minute = Number(match[2]);

  if (gregorianHour > 23 || minute > 59) return null;

  const raw = (gregorianHour + 6) % 12;
  const hour = raw === 0 ? 12 : raw;
  const period = periodFor(gregorianHour);
  const label = `${hour}:${String(minute).padStart(2, "0")} ሰዓት`;

  return { hour, minute, period, label, full: `${label} ${period}` };
}

/**
 * The default reading everywhere a time is printed:
 *   "3:00 ሰዓት (09:00)"
 * An unreadable time is printed as it came — never swallowed.
 */
export function formatEthiopianClock(time: string, options?: { withPeriod?: boolean }): string {
  const clock = toEthiopianClock(time);

  if (clock === null) return time;

  const ethiopian = options?.withPeriod === true ? clock.full : clock.label;

  return `${ethiopian} (${time.slice(0, 5)})`;
}

/**
 * "3:00 ጠዋት – 2:00 ማታ (09:00 – 20:00)" for a pair such as opening hours.
 *
 * Both ends carry their period word. A single time can lean on "ሰዓት" alone,
 * but in a range the first end would otherwise read as the wrong half of the
 * day ("8:00 – 4:00 ማታ" could be 8 in the morning or 8 in the evening), so the
 * range is spelled out in full and "ሰዓት" is left out to keep it short.
 */
export function formatEthiopianClockRange(opensAt: string, closesAt: string): string {
  const from = toEthiopianClock(opensAt);
  const to = toEthiopianClock(closesAt);

  if (from === null || to === null) return `${opensAt} – ${closesAt}`;

  const clock = (time: EthiopianClock) =>
    `${time.hour}:${String(time.minute).padStart(2, "0")} ${time.period}`;

  return `${clock(from)} – ${clock(to)} (${opensAt.slice(0, 5)} – ${closesAt.slice(0, 5)})`;
}
