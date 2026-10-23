import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api";
import { recoverBookings, recoverBookingsByEmail, recoverBookingsByPhone } from "@/lib/booking-api";

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("manage booking — find with phone or email", () => {
  it("sends identifier (phone) to /api/v1/bookings/recover", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({ ok: true, bookings: [{ reference: "WZ1", service_name: "Silk press", booking_date: "2026-10-30", start_time: "10:00", end_time: "11:00", status: "requested", manage_url: "https://wiz.arkeonethiopia.com/booking/manage/token" }], count: 1 }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const res = await recoverBookings("0911223344");

    expect(res.bookings).toHaveLength(1);
    expect(res.count).toBe(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe("/api/v1/bookings/recover");
    expect(init.method).toBe("POST");
    const body = JSON.parse(String(init.body));
    expect(body.identifier).toBe("0911223344");
    expect(body.website).toBe("");
  });

  it("sends identifier (email) same endpoint", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true, bookings: [], count: 0 }));
    vi.stubGlobal("fetch", fetchMock);

    await recoverBookings("hanna@example.com");

    const body = JSON.parse(String((fetchMock.mock.calls[0] as [unknown, RequestInit])[1].body));
    expect(body.identifier).toBe("hanna@example.com");
  });

  it("backwards compat aliases send via identifier", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true, bookings: [], count: 0 }));
    vi.stubGlobal("fetch", fetchMock);

    await recoverBookingsByPhone("0911223344");
    let body = JSON.parse(String((fetchMock.mock.calls[0] as [unknown, RequestInit])[1].body));
    expect(body.identifier).toBe("0911223344");

    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse({ ok: true, bookings: [], count: 0 })));
    const m2 = vi.fn().mockResolvedValue(jsonResponse({ ok: true, bookings: [], count: 0 }));
    vi.stubGlobal("fetch", m2);
    await recoverBookingsByEmail("Hanna@Example.com");
    body = JSON.parse(String((m2.mock.calls[0] as [unknown, RequestInit])[1].body));
    expect(body.identifier).toBe("Hanna@Example.com");
  });

  it("surfaces server validation error for bad identifier", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse({ error: "Phone number must be a valid phone number. Or enter your email address." }, 422)));
    await expect(recoverBookings("bad")).rejects.toMatchObject({ name: "ApiError", status: 422 });
  });

  it("explains rate limit in friendly words", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse({ error: "Too many tries. Please wait a few minutes and try again." }, 429)));
    const err = await recoverBookings("0911223344").catch((e) => e as ApiError);
    expect(err.message).toMatch(/wait a few minutes/i);
    expect(err.status).toBe(429);
  });

  it("handles network failure with salon message", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("failed to fetch")));
    await expect(recoverBookings("0911223344")).rejects.toThrow(/could not reach the salon/i);
  });
});

describe("manage page copy — not saying recover", () => {
  it("expects new booking-manage-card wording", async () => {
    // This is a contract: booking step 0 must say Find your booking with phone or email, not Recover
    const bookingSrc = await import("node:fs").then((fs) => fs.readFileSync("src/routes/booking.tsx", "utf8"));
    expect(bookingSrc).toContain("Find your booking with phone or email");
    expect(bookingSrc).not.toContain("Recover with phone");
    expect(bookingSrc).toContain("Find your booking through your phone number or email");
  });

  it("manage page says Find your booking not Lost your link as title", async () => {
    const src = await import("node:fs").then((fs) => fs.readFileSync("src/routes/booking.recover.tsx", "utf8"));
    expect(src).toContain("Find your");
    expect(src).toContain("booking");
    expect(src).toContain("Phone number or email");
    expect(src).toContain("0912345678 or you@email.com");
    // Old copy must be gone
    expect(src).not.toContain("Lost your");
    expect(src).not.toContain("Recover booking");
    expect(src).not.toContain("Phone recovery");
  });
});
