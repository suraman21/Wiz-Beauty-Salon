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
  service_id?: number | null;
  service_ids?: number[];
  is_undecided?: boolean;
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

export type CalendarOpts = {
  serviceId?: number | null;
  serviceIds?: number[];
  isUndecided?: boolean;
  duration?: number;
};

export function fetchCalendar(
  serviceId: number | CalendarOpts,
  from: string,
  days = 21,
  signal?: AbortSignal,
): Promise<CalendarDay[]> {
  let opts: CalendarOpts;
  if (typeof serviceId === 'object' && serviceId !== null) {
    opts = serviceId as CalendarOpts;
  } else {
    opts = { serviceId: serviceId as number };
  }

  const query = new URLSearchParams({
    from,
    days: String(days),
  });

  if (opts.isUndecided) {
    query.set('is_undecided', '1');
  } else if (opts.serviceIds && opts.serviceIds.length > 0) {
    query.set('service_ids', opts.serviceIds.join(','));
  } else if (opts.serviceId != null && opts.serviceId > 0) {
    query.set('service_id', String(opts.serviceId));
  } else if (opts.duration && opts.duration > 0) {
    query.set('duration', String(opts.duration));
  } else if (typeof serviceId === 'number' && serviceId > 0) {
    query.set('service_id', String(serviceId));
  }

  return apiGet<{ days: CalendarDay[] }>(`/api/v1/calendar?${query.toString()}`, signal).then(
    (body) => body.days,
  );
}

export function fetchCalendarMulti(
  opts: CalendarOpts,
  from: string,
  days = 21,
  signal?: AbortSignal,
): Promise<CalendarDay[]> {
  return fetchCalendar(opts, from, days, signal);
}

export function createBooking(input: BookingInput): Promise<BookingResult> {
  const payload: Record<string, unknown> = {
    name: input.name,
    phone: input.phone,
    email: input.email,
    date: input.date,
    time: input.time,
    notes: input.notes,
    website: '',
  };
  if (input.is_undecided) {
    payload.is_undecided = 1;
    payload.isUndecided = 1;
  } else if (input.service_ids && input.service_ids.length > 0) {
    payload.service_ids = input.service_ids;
    payload.serviceIds = input.service_ids;
    payload.service_id = input.service_id ?? input.service_ids[0];
  } else if (input.service_id != null) {
    payload.service_id = input.service_id;
  }
  return apiPost<BookingResult>('/api/v1/bookings', payload);
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

export function recoverBookingsByPhone(phone: string): Promise<{ bookings: RecoverBooking[]; count: number }> {
  return apiPost<{ bookings: RecoverBooking[]; count: number }>('/api/v1/bookings/recover', {
    phone,
    website: '',
  });
}

export { ApiError };
