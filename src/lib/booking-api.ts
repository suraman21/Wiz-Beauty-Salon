/**
 * The booking API, as the public site sees it.
 *
 * Four endpoints, all public, all JSON:
 *
 *   GET  /api/v1/services     the live catalogue
 *   GET  /api/v1/calendar     free times for a run of days
 *   GET  /api/v1/availability free times for one day
 *   POST /api/v1/bookings     ask for an appointment
 *
 * The site is served from the same host as the panel, so the base URL is empty
 * by default and every call is same-origin. `VITE_API_BASE_URL` only exists for
 * pointing a local dev server at the PHP process on another port.
 */

const BASE = ((import.meta.env["VITE_API_BASE_URL"] as string | undefined) ?? "").replace(
  /\/+$/,
  "",
);

export type ApiService = {
  id: number;
  name: string;
  category: string;
  duration_minutes: number;
  price_cents: number | null;
};

export type CalendarDay = {
  date: string;
  open: boolean;
  reason: string | null;
  slots: string[];
};

export type BookingInput = {
  name: string;
  phone: string;
  email: string;
  service_id: number;
  date: string;
  time: string;
  notes: string;
};

export type BookingResult = {
  reference: string;
  status: string;
  message: string;
  /** A signed link the customer can keep to cancel or move the booking. */
  manage_url?: string | null;
};

/** An error the server described in words we can show a customer. */
export class BookingError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = "BookingError";
    this.status = status;
  }
}

const NETWORK_MESSAGE = "We could not reach the salon. Please check your connection and try again.";

async function getJson<T>(path: string, signal?: AbortSignal): Promise<T> {
  let response: Response;

  try {
    response = await fetch(`${BASE}${path}`, {
      headers: { Accept: "application/json" },
      ...(signal ? { signal } : {}),
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") throw error;
    throw new BookingError(NETWORK_MESSAGE, 0);
  }

  if (!response.ok) {
    throw new BookingError(await readError(response), response.status);
  }

  return (await response.json()) as T;
}

async function readError(response: Response): Promise<string> {
  try {
    const body = (await response.json()) as { error?: unknown };

    if (typeof body.error === "string" && body.error.trim() !== "") return body.error;
  } catch {
    // Not JSON, or not our shape — fall through to the generic message.
  }

  return "Something went wrong. Please try again.";
}

export function fetchServices(signal?: AbortSignal): Promise<ApiService[]> {
  return getJson<{ services: ApiService[] }>("/api/v1/services", signal).then(
    (body) => body.services,
  );
}

export function fetchCalendar(
  serviceId: number,
  from: string,
  days = 21,
  signal?: AbortSignal,
): Promise<CalendarDay[]> {
  const query = new URLSearchParams({
    service_id: String(serviceId),
    from,
    days: String(days),
  });

  return getJson<{ days: CalendarDay[] }>(`/api/v1/calendar?${query.toString()}`, signal).then(
    (body) => body.days,
  );
}

export async function createBooking(input: BookingInput): Promise<BookingResult> {
  let response: Response;

  try {
    response = await fetch(`${BASE}/api/v1/bookings`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ ...input, website: "" }),
    });
  } catch {
    throw new BookingError(NETWORK_MESSAGE, 0);
  }

  if (!response.ok) {
    throw new BookingError(await readError(response), response.status);
  }

  return (await response.json()) as BookingResult;
}

/** "1,200 ETB", or null when the salon has not set a price. */
export function formatPrice(cents: number | null): string | null {
  if (cents === null || cents <= 0) return null;

  return `${new Intl.NumberFormat("en-GB").format(Math.round(cents / 100))} ETB`;
}

/** Local calendar date, not the UTC one — Addis Ababa is UTC+3. */
export function isoToday(): string {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");

  return `${now.getFullYear()}-${month}-${day}`;
}
