import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, formatPrice } from "@/lib/api";
import { createBooking, fetchCalendar, fetchServices } from "@/lib/booking-api";

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("booking API client", () => {
  it("reads the live catalogue from /api/v1/services", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        services: [
          {
            id: 4,
            name: "Wave",
            category: "Hair Styling",
            duration_minutes: 60,
            price_cents: null,
          },
        ],
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const services = await fetchServices();

    expect(services).toHaveLength(1);
    expect(services[0]?.name).toBe("Wave");
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/v1/services");
  });

  it("asks for the calendar with the service, the start date and the number of days", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        days: [{ date: "2026-10-22", open: true, reason: null, slots: ["09:00"] }],
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const result = await fetchCalendar({ categoryId: 4 }, "2026-10-22", 7);

    expect(result.days[0]?.slots).toEqual(["09:00"]);

    const url = String(fetchMock.mock.calls[0]?.[0]);
    expect(url).toContain("/api/v1/calendar?");
    expect(url).toContain("category_id=4");
    expect(url).toContain("from=2026-10-22");
    expect(url).toContain("days=7");
  });

  it("asks for several services in one run, and reads back how long it is", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        service: { id: 4, name: "Manicure + Pedicure", duration_minutes: 60 },
        days: [{ date: "2026-10-22", open: true, reason: null, slots: ["09:00"] }],
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const result = await fetchCalendar({ categoryIds: [4, 9] }, "2026-10-22", 7);

    expect(result.days[0]?.slots).toEqual(["09:00"]);
    expect(result.service?.duration_minutes).toBe(60);

    const url = String(fetchMock.mock.calls[0]?.[0]);
    expect(url).toContain("category_ids=4%2C9");
    expect(url).not.toContain("category_id=4");
  });

  it("keeps one service on the old single-service spelling", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ days: [] }));
    vi.stubGlobal("fetch", fetchMock);

    await fetchCalendar({ categoryIds: [4] }, "2026-10-22", 7);

    const url = String(fetchMock.mock.calls[0]?.[0]);
    expect(url).toContain("category_id=4");
    expect(url).not.toContain("category_ids");
  });

  it("posts several services as category_ids", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(
        jsonResponse({ ok: true, reference: "WZ123", status: "requested", message: "" }),
      );
    vi.stubGlobal("fetch", fetchMock);

    await createBooking({
      name: "Hanna",
      phone: "0911223344",
      email: "",
      category_ids: [4, 9],
      date: "2026-10-22",
      time: "09:00",
      notes: "",
    });

    const body = JSON.parse(String((fetchMock.mock.calls[0]?.[1] as RequestInit).body)) as Record<
      string,
      unknown
    >;

    expect(body["category_ids"]).toBe("4,9");
    expect(body["category_id"]).toBeUndefined();
  });

  it("sends the booking as JSON and returns the reference", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(
        jsonResponse(
          { ok: true, reference: "WZ261019-AC4K", status: "requested", message: "Thanks" },
          201,
        ),
      );
    vi.stubGlobal("fetch", fetchMock);

    const result = await createBooking({
      name: "Hanna",
      phone: "0911223344",
      email: "",
      category_id: 4,
      date: "2026-10-22",
      time: "10:00",
      notes: "",
    });

    expect(result.reference).toBe("WZ261019-AC4K");

    const init = fetchMock.mock.calls[0]?.[1] as RequestInit;
    expect(init.method).toBe("POST");
    expect(JSON.parse(String(init.body))).toMatchObject({ category_id: 4, time: "10:00" });
  });

  it("surfaces the server's own words when a slot has gone", async () => {
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockResolvedValue(
          jsonResponse({ error: "That time is no longer available. Please choose another." }, 422),
        ),
    );

    await expect(
      createBooking({
        name: "Hanna",
        phone: "0911223344",
        email: "",
        category_id: 4,
        date: "2026-10-22",
        time: "10:00",
        notes: "",
      }),
    ).rejects.toMatchObject({
      name: "ApiError",
      status: 422,
      message: "That time is no longer available. Please choose another.",
    });
  });

  it("explains a dead connection in words a customer understands", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("failed to fetch")));

    await expect(fetchServices()).rejects.toBeInstanceOf(ApiError);
    await expect(fetchServices()).rejects.toThrow(/could not reach the salon/i);
  });

  it("prints a price only when the salon has set one", () => {
    expect(formatPrice(120000)).toBe("1,200 ETB");
    expect(formatPrice(null)).toBeNull();
    expect(formatPrice(0)).toBeNull();
  });
});
