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
  /** The service the customer picked, by its id in the panel. */
  category_id?: number | null | undefined;
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
};

export function fetchCalendar(
  opts: CalendarOpts,
  from: string,
  days = 21,
  signal?: AbortSignal,
): Promise<CalendarDay[]> {
  const query = new URLSearchParams({
    from,
    days: String(days),
  });

  if (opts.categoryId != null && opts.categoryId > 0) {
    query.set("category_id", String(opts.categoryId));
  }

  return apiGet<{ days?: CalendarDay[] }>(`/api/v1/calendar?${query.toString()}`, signal).then(
    (body) => body.days ?? [],
  );
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
  if (input.category_id != null) {
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
