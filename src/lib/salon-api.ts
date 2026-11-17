/**
 * What the Contact page prints: the salon's own details and opening hours.
 *
 *   GET /api/v1/salon   { contact, hours, closures }
 *
 * All of it is edited on the panel's Settings screen, so the page never has to
 * be redeployed when the salon changes its number or its hours. A blank field
 * means "not published yet" and is left out rather than shown as an empty row.
 */

import { apiGet } from "@/lib/api";
import {
  formatEthiopian,
  formatEthiopianClockRange,
  formatGregorian,
} from "@/lib/ethiopian-calendar";

export type SalonContact = {
  phone: string;
  whatsapp: string;
  email: string;
  instagram: string;
  tiktok: string;
  address: string;
};

export type SalonHour = {
  /** 0 = Sunday … 6 = Saturday, matching the panel. */
  day: number;
  opens_at: string | null;
  closes_at: string | null;
  is_closed: boolean;
};

export type SalonClosure = { from: string; to: string; reason: string };

export type SalonInfo = {
  contact: SalonContact;
  hours: SalonHour[];
  closures: SalonClosure[];
};

export type HoursRow = { day: string; time: string };

const DAY_NAMES = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

/** Monday first, which is how the salon reads its own week. */
const WEEK = [1, 2, 3, 4, 5, 6, 0];

export function fetchSalon(signal?: AbortSignal): Promise<SalonInfo> {
  return apiGet<Partial<SalonInfo>>("/api/v1/salon", signal).then((body) => ({
    contact: {
      phone: body.contact?.phone ?? "",
      whatsapp: body.contact?.whatsapp ?? "",
      email: body.contact?.email ?? "",
      instagram: body.contact?.instagram ?? "",
      tiktok: body.contact?.tiktok ?? "",
      address: body.contact?.address ?? "",
    },
    hours: Array.isArray(body.hours) ? body.hours : [],
    closures: Array.isArray(body.closures) ? body.closures : [],
  }));
}

/**
 * A WhatsApp link that actually opens a chat.
 *
 * Staff usually type the number the way they say it — 09xx xxx xxx — but
 * wa.me only understands international form. Local numbers gain the country
 * code; anything already international is left alone.
 */
export function whatsappHref(value: string): string {
  let digits = value.replace(/[^\d]/g, "");

  if (digits === "") return "";

  if (digits.startsWith("00")) digits = digits.slice(2);
  if (digits.startsWith("0")) digits = `251${digits.slice(1)}`;

  return `https://wa.me/${digits}`;
}

export function hasDetails(contact: SalonContact): boolean {
  return Object.values(contact).some((value) => value.trim() !== "");
}

/**
 * "Tik 25–26, 2018 (25–26 Dec) · Holiday" — the way the salon would say it out
 * loud, in the calendar it keeps. A one-day closure prints once, not twice.
 *
 * The Gregorian dates stay in brackets because a closure is the one date a
 * customer may have been told over the phone in either calendar.
 */
export function closureLabel(closure: SalonClosure): string {
  const ethiopian = (iso: string) => formatEthiopian(iso, "medium", iso);
  const gregorian = (iso: string) => formatGregorian(iso, iso);

  const sameDay = closure.from === closure.to || ethiopian(closure.to) === ethiopian(closure.from);

  // An unreadable date comes back as the raw string from both formatters;
  // print it once rather than twice.
  const withGregorian = (ethiopianText: string, gregorianText: string) =>
    ethiopianText === gregorianText ? ethiopianText : `${ethiopianText} (${gregorianText})`;

  const days = sameDay
    ? withGregorian(ethiopian(closure.from), gregorian(closure.from))
    : `${ethiopian(closure.from)} – ${ethiopian(closure.to)} (${gregorian(closure.from)} – ${gregorian(
        closure.to,
      )})`;

  const reason = closure.reason.trim();

  return reason === "" ? days : `${days} · ${reason}`;
}

/**
 * "3:00 ጠዋት – 2:00 ማታ (09:00 – 20:00)", or "Closed".
 *
 * The salon sets 24-hour times; the customer reads the Ethiopian clock with
 * the 24-hour time in brackets, so nobody has to convert in their head.
 */
function timeFor(hour: SalonHour): string {
  if (hour.is_closed || hour.opens_at === null || hour.closes_at === null) return "Closed";

  return formatEthiopianClockRange(hour.opens_at, hour.closes_at);
}

/**
 * Collapse the week into as few rows as it honestly needs: identical days in a
 * run become one row ("Monday – Friday"), so a normal week is three lines and
 * an unusual one still reads correctly day by day.
 */
export function hoursRows(hours: SalonHour[]): HoursRow[] {
  const byDay = new Map(hours.map((hour) => [hour.day, hour]));
  const week = WEEK.map((day) => byDay.get(day)).filter(
    (hour): hour is SalonHour => hour !== undefined,
  );

  const rows: HoursRow[] = [];
  let runStart: SalonHour | null = null;
  let runEnd: SalonHour | null = null;

  const flush = () => {
    if (runStart === null || runEnd === null) return;

    const first = DAY_NAMES[runStart.day] ?? "";
    const last = DAY_NAMES[runEnd.day] ?? "";

    const day = runStart.day === runEnd.day ? first : `${first} – ${last}`;

    rows.push({ day, time: timeFor(runStart) });
    runStart = null;
    runEnd = null;
  };

  for (const hour of week) {
    // The loop walks consecutive days, so a matching time always extends the
    // run that is currently open.
    const same = runStart !== null && timeFor(runStart) === timeFor(hour);

    if (same) {
      runEnd = hour;
      continue;
    }

    flush();
    runStart = hour;
    runEnd = hour;
  }

  flush();

  return rows;
}
