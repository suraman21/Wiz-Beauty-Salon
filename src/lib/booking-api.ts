/**
 * The booking API, as the public site sees it.
 *
 *   GET  /api/v1/services     the live catalogue
 *   GET  /api/v1/calendar     free times for a run of days
 *   POST /api/v1/bookings     ask for an appointment
 */

import { ApiError, apiGet, apiPost } from "@/lib/api";

export type ApiService = {
  id: number;
  name: string;
  slug: string;
  category: string;
  description: string;
  duration_minutes: number;
  price_cents: number | null;
  featured: boolean;
  /** The photo staff uploaded for this service, if any. */
  image_url: string | null;
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

export type ApiCategory = {
  id: number;
  name: string;
  slug: string;
  amharic: string;
  description: string;
  sort_order: number;
  /** The illustration the salon chose for this category, if any. */
  image_url: string | null;
};

export type ServiceCatalogue = {
  services: ApiService[];
  categories: ApiCategory[];
};

/**
 * The whole public catalogue in one request: every visible service and every
 * visible category, in the order the salon arranged them in the panel.
 */
export function fetchCatalogue(signal?: AbortSignal): Promise<ServiceCatalogue> {
  return apiGet<Partial<ServiceCatalogue>>("/api/v1/services", signal).then((body) => ({
    services: body.services ?? [],
    categories: body.categories ?? [],
  }));
}

export function fetchServices(signal?: AbortSignal): Promise<ApiService[]> {
  return fetchCatalogue(signal).then((body) => body.services);
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

  return apiGet<{ days: CalendarDay[] }>(`/api/v1/calendar?${query.toString()}`, signal).then(
    (body) => body.days,
  );
}

export function createBooking(input: BookingInput): Promise<BookingResult> {
  return apiPost<BookingResult>("/api/v1/bookings", { ...input, website: "" });
}

export { ApiError };
