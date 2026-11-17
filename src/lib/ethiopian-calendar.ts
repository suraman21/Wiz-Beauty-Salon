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
 * ACCURACY: correct for every date this salon will trade, today through 2099.
 * Gregorian 2100 is not a leap year but the Ethiopian year spanning it is, so
 * a one-day drift begins there. When that comes, the leap inference for the
 * month length must use the true Ethiopian rule (`year % 4 === 3`) everywhere.
 * FKSS documents the same boundary; it is not a bug to fix now.
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

function isGregorianLeap(year: number): boolean {
  return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

/** Meskerem 1 is 11 September, or the 12th when the next Gregorian year leaps. */
function meskerem1Day(gregorianYear: number): number {
  return isGregorianLeap(gregorianYear + 1) ? 12 : 11;
}

function meskerem1(gregorianYear: number): Date {
  return new Date(gregorianYear, 8, meskerem1Day(gregorianYear));
}

/** Local midnight — the whole module works in calendar days, never instants. */
function atMidnight(input: Date | string): Date | null {
  const date = input instanceof Date ? new Date(input.getTime()) : new Date(input);

  if (Number.isNaN(date.getTime())) return null;

  return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

export function isGregorianLeapYear(year: number): boolean {
  return isGregorianLeap(year);
}

/** `null` when the input is not a readable date, so callers must decide. */
export function toEthiopian(input: Date | string): EthiopianDate | null {
  const date = atMidnight(input);

  if (date === null) return null;

  const gregorianYear = date.getFullYear();
  let start = meskerem1(gregorianYear);
  let year: number;

  if (date < start) {
    start = meskerem1(gregorianYear - 1);
    year = gregorianYear - 8;
  } else {
    year = gregorianYear - 7;
  }

  // Rounded, not floored: a DST shift is 60 minutes, never half a day.
  const daysSince = Math.round((date.getTime() - start.getTime()) / 86_400_000);
  let month = Math.floor(daysSince / 30) + 1;
  let day = (daysSince % 30) + 1;

  if (month > 13) {
    month = 13;
    day = daysSince - 360 + 1;
  }

  if (month < 1) month = 1;
  if (day < 1) day = 1;

  return { year, month, day };
}

export function toGregorian(date: EthiopianDate): Date {
  const start = meskerem1(date.year + 7);
  const result = new Date(start);

  result.setDate(result.getDate() + (date.month - 1) * 30 + (date.day - 1));

  return result;
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
