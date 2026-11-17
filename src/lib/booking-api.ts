/**
 * The booking API, as the public site sees it.
 *
 *   GET  /api/v1/services     the live catalogue
 *   GET  /api/v1/calendar     free times for a run of days
 *   POST /api/v1/bookings     ask for an appointment
 *   POST /api/v1/bookings/recover  find your bookings with phone or email
 */

import { ApiError, apiGet, apiPost } from "@/lib/api";

/**
 * One of the salon's services — the row staff add in the panel.
 *
 * There is no price and no running time: both are settled with the customer at
 * the salon, so the site never shows them.
 */
export type ApiService = {
  id: number;
  name: string;
  slug: string;
  amharic: string;
  description: string;
  /** The photo staff uploaded for this service, if any. */
  image_url: string | null;
  /** True when staff ticked this service for the home page in the panel. */
  show_on_home?: boolean | undefined;
};

/**
 * The Ethiopian reading of a Gregorian day, as the API sends it.
 *
 * The site derives its own Ethiopian dates from `date` — same algorithm, and
 * it has to work when the API is unreachable and the bundled catalogue is
 * shown instead — so these fields are informational. They exist so the panel,
 * exports and future clients can read the day without carrying a converter.
 */
export type ApiEthiopianDate = {
  year: number;
  month: number;
  day: number;
  iso: string;
  label: string;
  label_am: string;
  gregorian: string;
};

export type CalendarDay = {
  /** Gregorian "YYYY-MM-DD" — what the site posts back and the database stores. */
  date: string;
  open: boolean;
  reason: string | null;
  slots: string[];
  /** Additive: the same day in the Ethiopian calendar. */
  ethiopian?: ApiEthiopianDate | null;
  /** Additive: "Hidar 8, 2019 EC". */
  label?: string | null;
  /** Additive: each slot in the Ethiopian clock, index-matched with `slots`. */
  slots_ethiopian?: string[];
};

export type BookingInput = {
  name: string;
  phone: string;
  email: string;
  /** The service the customer picked, by its id in the panel. */
  category_id?: number | null | undefined;
  /** Several services in one appointment; wins over `category_id` when set. */
  category_ids?: number[] | undefined;
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
  /** Additive: the day and time as received, plus their Ethiopian reading. */
  date?: string;
  time?: string;
  ethiopian?: { date: ApiEthiopianDate | null; time: string } | null;
};

export type ServiceCatalogue = {
  services: ApiService[];
};

/**
 * The whole public catalogue in one request: every visible service, in the
 * order the salon arranged them in the panel.
 */
export function fetchCatalogue(signal?: AbortSignal): Promise<ServiceCatalogue> {
  return apiGet<Partial<ServiceCatalogue>>("/api/v1/services", signal).then((body) => ({
    services: body.services ?? [],
  }));
}

export function fetchServices(signal?: AbortSignal): Promise<ApiService[]> {
  return fetchCatalogue(signal).then((body) => body.services);
}

export type CalendarOpts = {
  /** The service the customer picked. */
  categoryId?: number | null | undefined;
  /** Several services in one appointment — the whole run has to fit. */
  categoryIds?: number[] | undefined;
};

/**
 * The calendar, plus what the chosen services add up to.
 *
 * `service.duration_minutes` is the whole run — one service, or several booked
 * together. The site prints it as a rough length so the customer knows what
 * they are committing to, without the panel publishing prices.
 */
export type CalendarResult = {
  days: CalendarDay[];
  service: { id: number; name: string; duration_minutes: number } | null;
};

export function fetchCalendar(
  opts: CalendarOpts,
  from: string,
  days = 21,
  signal?: AbortSignal,
): Promise<CalendarResult> {
  const query = new URLSearchParams({
    from,
    days: String(days),
  });

  // One service, or several at once. The API understands both spellings.
  const ids = (opts.categoryIds ?? (opts.categoryId != null ? [opts.categoryId] : [])).filter(
    (id) => Number.isFinite(id) && id > 0,
  );

  if (ids.length === 1) {
    query.set("category_id", String(ids[0]));
  } else if (ids.length > 1) {
    query.set("category_ids", ids.join(","));
  }

  return apiGet<{ days?: CalendarDay[]; service?: CalendarResult["service"] }>(
    `/api/v1/calendar?${query.toString()}`,
    signal,
  ).then((body) => ({ days: body.days ?? [], service: body.service ?? null }));
}

export function createBooking(input: BookingInput): Promise<BookingResult> {
  const payload: Record<string, unknown> = {
    name: input.name,
    phone: input.phone,
    email: input.email,
    date: input.date,
    time: input.time,
    notes: input.notes,
    website: "",
  };
  if (input.category_ids != null && input.category_ids.length > 0) {
    payload["category_ids"] = input.category_ids.join(",");
  } else if (input.category_id != null) {
    payload["category_id"] = input.category_id;
  }
  return apiPost<BookingResult>("/api/v1/bookings", payload);
}

export type RecoverBooking = {
  reference: string;
  service_name: string;
  booking_date: string;
  start_time: string;
  end_time: string;
  status: string;
  manage_url: string | null;
  /** Additive: the same booking in the Ethiopian calendar and clock. */
  booking_date_ec?: string | null;
  ethiopian?: ApiEthiopianDate | null;
  start_time_label?: string;
  end_time_label?: string;
};

/**
 * Find bookings with phone number OR email address.
 * identifier can be either — server detects by presence of @.
 */
export function recoverBookings(
  identifier: string,
): Promise<{ bookings: RecoverBooking[]; count: number }> {
  return apiPost<{ bookings: RecoverBooking[]; count: number }>("/api/v1/bookings/recover", {
    identifier: identifier.trim(),
    website: "",
  });
}

/** Backwards compat alias — kept so existing call sites don't break. */
export function recoverBookingsByPhone(
  phone: string,
): Promise<{ bookings: RecoverBooking[]; count: number }> {
  return recoverBookings(phone);
}

export function recoverBookingsByEmail(
  email: string,
): Promise<{ bookings: RecoverBooking[]; count: number }> {
  return recoverBookings(email);
}

export { ApiError };
