import { afterEach, describe, expect, it, vi } from "vitest";
import {
  closureLabel,
  fetchSalon,
  hasDetails,
  hoursRows,
  whatsappHref,
  type SalonHour,
} from "@/lib/salon-api";

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

const week = (opens: string, closes: string, closedDays: number[] = []): SalonHour[] =>
  [0, 1, 2, 3, 4, 5, 6].map((day) => ({
    day,
    opens_at: closedDays.includes(day) ? null : opens,
    closes_at: closedDays.includes(day) ? null : closes,
    is_closed: closedDays.includes(day),
  }));

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("salon API client", () => {
  it("reads the details the panel publishes", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        contact: {
          phone: "+251 91 234 5678",
          whatsapp: "0911223344",
          email: "hello@wiz.et",
          instagram: "wiz.beauty",
          tiktok: "",
          address: "Bole, Addis Ababa",
        },
        hours: week("09:00", "20:00", [0]),
        closures: [{ from: "2026-12-25", to: "2026-12-26", reason: "Holiday" }],
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const salon = await fetchSalon();

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/v1/salon",
      expect.objectContaining({ headers: { Accept: "application/json" } }),
    );
    expect(salon.contact.phone).toBe("+251 91 234 5678");
    expect(salon.hours).toHaveLength(7);
    expect(hasDetails(salon.contact)).toBe(true);
  });

  it("answers with blanks, not invented details, when the salon published none", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse({
          contact: {
            phone: "",
            whatsapp: "",
            email: "",
            instagram: "",
            tiktok: "",
            address: "",
          },
          hours: [],
          closures: [],
        }),
      ),
    );

    const salon = await fetchSalon();

    expect(hasDetails(salon.contact)).toBe(false);
    expect(hoursRows(salon.hours)).toEqual([]);
  });
});

describe("opening hours rows", () => {
  it("collapses a normal week into three honest lines", () => {
    const hours = week("09:00", "20:00", [0]).map((hour) =>
      hour.day === 6 ? { ...hour, closes_at: "18:00" } : hour,
    );

    expect(hoursRows(hours)).toEqual([
      { day: "Monday – Friday", time: "3:00 ጠዋት – 2:00 ማታ (9 o'clock AM – 8 o'clock PM)" },
      { day: "Saturday", time: "3:00 ጠዋት – 12:00 ማታ (9 o'clock AM – 6 o'clock PM)" },
      { day: "Sunday", time: "Closed" },
    ]);
  });

  it("keeps an unusual week day by day", () => {
    const hours = week("09:00", "20:00").map((hour) => {
      if (hour.day === 2) return { ...hour, opens_at: "11:00" };
      if (hour.day === 4) return { ...hour, is_closed: true, opens_at: null, closes_at: null };
      return hour;
    });

    expect(hoursRows(hours)).toEqual([
      { day: "Monday", time: "3:00 ጠዋት – 2:00 ማታ (9 o'clock AM – 8 o'clock PM)" },
      // 11:00 is Ethiopian 5 o'clock, still morning — the period word keeps
      // the two halves of the day apart.
      { day: "Tuesday", time: "5:00 ጠዋት – 2:00 ማታ (11 o'clock AM – 8 o'clock PM)" },
      { day: "Wednesday", time: "3:00 ጠዋት – 2:00 ማታ (9 o'clock AM – 8 o'clock PM)" },
      { day: "Thursday", time: "Closed" },
      { day: "Friday – Sunday", time: "3:00 ጠዋት – 2:00 ማታ (9 o'clock AM – 8 o'clock PM)" },
    ]);
  });

  it("orders Monday first whatever order the API used", () => {
    const shuffled = [...week("10:00", "19:00")].reverse();

    expect(hoursRows(shuffled)).toEqual([
      { day: "Monday – Sunday", time: "4:00 ጠዋት – 1:00 ማታ (10 o'clock AM – 7 o'clock PM)" },
    ]);
  });
});

describe("WhatsApp links", () => {
  it("accepts the number the way the salon says it", () => {
    expect(whatsappHref("0911223344")).toBe("https://wa.me/251911223344");
    expect(whatsappHref("0911 223 344")).toBe("https://wa.me/251911223344");
  });

  it("leaves an international number alone", () => {
    expect(whatsappHref("+251 91 122 3344")).toBe("https://wa.me/251911223344");
    expect(whatsappHref("00251911223344")).toBe("https://wa.me/251911223344");
  });

  it("returns nothing when there is no number", () => {
    expect(whatsappHref("")).toBe("");
  });
});

/**
 * A closure is the one date a customer may have been told over the phone in
 * either calendar, so the Ethiopian dates lead and the Gregorian days follow
 * in brackets. 25 and 26 December 2026 are Tahsas 16 and 17, 2019.
 */
describe("closure labels", () => {
  it("prints a run of days once", () => {
    expect(closureLabel({ from: "2026-12-25", to: "2026-12-26", reason: "Holiday" })).toBe(
      "Tah 16, 2019 – Tah 17, 2019 (25 Dec 2026 – 26 Dec 2026) · Holiday",
    );
  });

  it("does not repeat a single day", () => {
    expect(closureLabel({ from: "2027-01-07", to: "2027-01-07", reason: "" })).toBe(
      "Tah 29, 2019 (7 Jan 2027)",
    );
  });

  it("keeps the reason when there is one", () => {
    expect(closureLabel({ from: "2027-03-01", to: "2027-03-01", reason: "  Training  " })).toBe(
      "Yek 22, 2019 (1 Mar 2027) · Training",
    );
  });

  it("still shows an unreadable date as it came", () => {
    expect(closureLabel({ from: "not-a-date", to: "not-a-date", reason: "" })).toBe("not-a-date");
  });
});
