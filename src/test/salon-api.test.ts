import { afterEach, describe, expect, it, vi } from "vitest";
import { fetchSalon, hasDetails, hoursRows, whatsappHref, type SalonHour } from "@/lib/salon-api";

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
      { day: "Monday – Friday", time: "09:00 – 20:00" },
      { day: "Saturday", time: "09:00 – 18:00" },
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
      { day: "Monday", time: "09:00 – 20:00" },
      { day: "Tuesday", time: "11:00 – 20:00" },
      { day: "Wednesday", time: "09:00 – 20:00" },
      { day: "Thursday", time: "Closed" },
      { day: "Friday – Sunday", time: "09:00 – 20:00" },
    ]);
  });

  it("orders Monday first whatever order the API used", () => {
    const shuffled = [...week("10:00", "19:00")].reverse();

    expect(hoursRows(shuffled)).toEqual([{ day: "Monday – Sunday", time: "10:00 – 19:00" }]);
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
