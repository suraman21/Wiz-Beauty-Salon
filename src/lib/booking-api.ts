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

export function fetchServices(signal?: AbortSignal): Promise<ApiService[]> {
  return apiGet<{ services: ApiService[] }>("/api/v1/services", signal).then(
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

  return apiGet<{ days: CalendarDay[] }>(`/api/v1/calendar?${query.toString()}`, signal).then(
    (body) => body.days,
  );
}

export function createBooking(input: BookingInput): Promise<BookingResult> {
  return apiPost<BookingResult>("/api/v1/bookings", { ...input, website: "" });
}

export { ApiError };
