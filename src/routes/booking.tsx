import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ArrowLeft, ArrowRight, Check } from "lucide-react";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { images } from "@/data/wiz";
import { bookingDetailsSchema, findBookableService } from "@/data/services";
import {
  BookingError,
  createBooking,
  fetchCalendar,
  fetchServices,
  formatPrice,
  isoToday,
  type ApiService,
  type CalendarDay,
} from "@/lib/booking-api";

export const Route = createFileRoute("/booking")({
  validateSearch: (s) => z.object({ service: z.string().optional() }).parse(s),
  head: () => ({
    meta: [
      { title: "Book an Appointment | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "Book your WIZ Beauty Salon appointment: choose a service, date and time in a few simple steps.",
      },
      { property: "og:title", content: "Book an Appointment | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Choose your service, date and time and book your WIZ moment.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Booking,
});

const steps = ["Service", "Date", "Time", "Details", "Summary"];
const CALENDAR_DAYS = 21;

type Details = { name: string; phone: string; email: string; notes: string };
type Confirmed = {
  reference: string;
  serviceId: number;
  date: string;
  time: string;
  details: Details;
};

const emptyDetails: Details = { name: "", phone: "", email: "", notes: "" };

/** "2026-10-22" → a local Date, so the weekday never shifts by a timezone. */
const parseIso = (iso: string) => new Date(`${iso}T00:00:00`);

const fmt = (iso: string) =>
  parseIso(iso).toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long" });

function Booking() {
  const search = Route.useSearch();
  const appliedSearch = useRef(false);

  const [services, setServices] = useState<ApiService[] | null>(null);
  const [loadError, setLoadError] = useState("");
  const [category, setCategory] = useState("");
  const [serviceId, setServiceId] = useState<number | null>(null);

  const [calendar, setCalendar] = useState<CalendarDay[]>([]);
  const [calendarBusy, setCalendarBusy] = useState(false);
  const [calendarError, setCalendarError] = useState("");

  const [step, setStep] = useState(0);
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [details, setDetails] = useState<Details>(emptyDetails);

  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [confirmed, setConfirmed] = useState<Confirmed | null>(null);

  const service = useMemo(
    () => services?.find((s) => s.id === serviceId) ?? null,
    [services, serviceId],
  );

  // ---------------------------------------------------------------- catalogue
  useEffect(() => {
    const controller = new AbortController();

    fetchServices(controller.signal)
      .then((list) => {
        setServices(list);
        setLoadError("");
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") return;
        setServices([]);
        setLoadError(
          error instanceof BookingError
            ? error.message
            : "The booking calendar is not available right now.",
        );
      });

    return () => controller.abort();
  }, []);

  // A link from the services page carries the chosen service — by database id,
  // or by the catalogue id the older pages still use.
  useEffect(() => {
    if (!services || appliedSearch.current || !search.service) return;

    appliedSearch.current = true;

    const byId = services.find((s) => String(s.id) === search.service);
    const catalogueName = findBookableService(search.service)?.name.toLowerCase();
    const byName = catalogueName
      ? services.find((s) => s.name.toLowerCase() === catalogueName)
      : undefined;
    const match = byId ?? byName;

    if (match) {
      setServiceId(match.id);
      setCategory(match.category);
      setStep(1);
    }
    // `services` and `search.service` both settle once; the ref stops re-runs.
  }, [services, search.service]);

  // ----------------------------------------------------------------- calendar
  const loadCalendar = useCallback((id: number, signal?: AbortSignal) => {
    setCalendarBusy(true);
    setCalendarError("");

    return fetchCalendar(id, isoToday(), CALENDAR_DAYS, signal)
      .then((days) => setCalendar(days))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") return;
        setCalendar([]);
        setCalendarError(
          error instanceof BookingError ? error.message : "We could not load the available times.",
        );
      })
      .finally(() => setCalendarBusy(false));
  }, []);

  useEffect(() => {
    if (serviceId === null) return undefined;

    const controller = new AbortController();
    void loadCalendar(serviceId, controller.signal);

    return () => controller.abort();
  }, [serviceId, loadCalendar]);

  // ------------------------------------------------------------------ choices
  const categories = useMemo(() => {
    const seen: string[] = [];

    for (const s of services ?? []) {
      if (!seen.includes(s.category)) seen.push(s.category);
    }

    return seen;
  }, [services]);

  const visible = useMemo(
    () => (services ?? []).filter((s) => category === "" || s.category === category),
    [services, category],
  );

  const selectedDay = calendar.find((d) => d.date === date) ?? null;
  const validDetails = bookingDetailsSchema.safeParse(details).success;

  const canContinue =
    [serviceId !== null, date !== "", time !== "", validDetails, true][step] ?? false;

  const chooseService = (id: number) => {
    setServiceId(id);
    setDate("");
    setTime("");
    setSubmitError("");
    setStep(1);
  };

  const chooseDate = (day: CalendarDay) => {
    if (day.slots.length === 0) return;

    setDate(day.date);
    setTime("");
    setStep(2);
  };

  const submit = async () => {
    if (serviceId === null || !date || !time) return;

    setSubmitting(true);
    setSubmitError("");

    try {
      const result = await createBooking({
        name: details.name.trim(),
        phone: details.phone.trim(),
        email: details.email.trim(),
        service_id: serviceId,
        date,
        time,
        notes: details.notes.trim(),
      });

      setConfirmed({ reference: result.reference, serviceId, date, time, details });
      setStep(5);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      const message =
        error instanceof BookingError
          ? error.message
          : "The request could not be sent. Please try again.";

      setSubmitError(message);

      // A taken slot means the calendar is stale: reload it and send the
      // customer back to the time step with the message in front of them.
      if (error instanceof BookingError && error.status === 422) {
        setTime("");
        setStep(2);
        void loadCalendar(serviceId);
      }
    } finally {
      setSubmitting(false);
    }
  };

  const restart = () => {
    setConfirmed(null);
    setSubmitError("");
    setServiceId(null);
    setCategory("");
    setDate("");
    setTime("");
    setDetails(emptyDetails);
    setStep(0);
  };

  const heading = (() => {
    if (step === 5) {
      return (
        <>
          Request
          <br />
          <em>received.</em>
        </>
      );
    }

    return (
      <>
        Book an
        <br />
        <em>appointment.</em>
      </>
    );
  })();

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <img src={images.portrait} alt="Demo editorial beauty portrait" />
          <div className="booking-visual-copy">
            <span className="overline">Book your moment</span>
            <p>
              Beauty,
              <br /> <em>your way.</em>
            </p>
          </div>
        </aside>

        <div className="booking-panel">
          <span className="overline">Booking</span>
          <h1>{heading}</h1>

          {step < 5 && (
            <ol className="stepper" aria-label="Booking progress">
              {steps.map((s, i) => (
                <li key={s} className={i === step ? "is-current" : i < step ? "is-done" : ""}>
                  <Button
                    variant="ghost"
                    className="h-auto rounded-none p-0 justify-start"
                    type="button"
                    disabled={i > step}
                    onClick={() => setStep(i)}
                  >
                    <span>{i < step ? <Check size={12} /> : i + 1}</span>
                    {s}
                  </Button>
                </li>
              ))}
            </ol>
          )}

          {loadError !== "" && (
            <p className="step-hint" role="alert">
              {loadError}
            </p>
          )}

          <div className="step-body" key={step}>
            {step === 0 && (
              <>
                {services === null && <p className="step-note">Loading services…</p>}

                {services !== null && services.length === 0 && loadError === "" && (
                  <p className="step-note">
                    Online booking is not open just yet. Please contact the salon and we will find
                    you a time.
                  </p>
                )}

                {services !== null && services.length > 0 && (
                  <>
                    <div className="wiz-form my-6">
                      <label className="full">
                        Category
                        <select
                          className="w-full min-w-0 border-b border-input bg-background py-3 text-base font-normal normal-case tracking-normal"
                          aria-label="Service category"
                          value={category}
                          onChange={(e) => setCategory(e.target.value)}
                        >
                          <option value="">All services</option>
                          {categories.map((c) => (
                            <option key={c} value={c}>
                              {c}
                            </option>
                          ))}
                        </select>
                      </label>
                    </div>

                    <fieldset className="svc-group">
                      <legend>{category === "" ? "All services" : category}</legend>
                      {visible.map((s) => {
                        const price = formatPrice(s.price_cents);

                        return (
                          <label
                            key={s.id}
                            className={`option ${serviceId === s.id ? "is-selected" : ""}`}
                          >
                            <input
                              type="radio"
                              name="service"
                              checked={serviceId === s.id}
                              onChange={() => chooseService(s.id)}
                            />
                            <span className="opt-name">{s.name}</span>
                            <span className="opt-meta">{price ?? "Price · to be confirmed"}</span>
                          </label>
                        );
                      })}
                    </fieldset>
                  </>
                )}
              </>
            )}

            {step === 1 && (
              <>
                {calendarBusy && <p className="step-note">Checking the calendar…</p>}

                {calendarError !== "" && (
                  <p className="step-note" role="alert">
                    {calendarError}{" "}
                    <button
                      type="button"
                      className="editorial-link"
                      onClick={() => serviceId !== null && void loadCalendar(serviceId)}
                    >
                      Try again
                    </button>
                  </p>
                )}

                {!calendarBusy && calendarError === "" && calendar.length > 0 && (
                  <>
                    <div className="date-grid" role="radiogroup" aria-label="Choose a date">
                      {calendar.map((day) => {
                        const d = parseIso(day.date);
                        const free = day.slots.length;
                        const full = free === 0;

                        return (
                          <Button
                            variant="ghost"
                            type="button"
                            role="radio"
                            aria-checked={date === day.date}
                            aria-disabled={full}
                            disabled={full}
                            title={full ? (day.reason ?? "Fully booked") : `${free} times free`}
                            key={day.date}
                            className={`date-cell h-auto rounded-none ${date === day.date ? "is-selected" : ""} ${full ? "is-full" : ""}`}
                            onClick={() => chooseDate(day)}
                          >
                            <span>{d.toLocaleDateString("en-GB", { weekday: "short" })}</span>
                            <strong>{d.getDate()}</strong>
                            <span>{d.toLocaleDateString("en-GB", { month: "short" })}</span>
                          </Button>
                        );
                      })}
                    </div>
                    <span className="demo-note">
                      Days the salon is closed are shown greyed out. Times are in Addis Ababa time.
                    </span>
                  </>
                )}
              </>
            )}

            {step === 2 && (
              <>
                <p className="step-hint">{date && fmt(date)}</p>

                {submitError !== "" && (
                  <p className="step-note" role="alert">
                    {submitError}
                  </p>
                )}

                {selectedDay !== null && selectedDay.slots.length > 0 ? (
                  <div className="time-grid" role="radiogroup" aria-label="Choose a time">
                    {selectedDay.slots.map((t) => (
                      <Button
                        variant="ghost"
                        type="button"
                        role="radio"
                        aria-checked={time === t}
                        key={t}
                        className={`chip h-auto rounded-none ${time === t ? "is-active" : ""}`}
                        onClick={() => setTime(t)}
                      >
                        {t}
                      </Button>
                    ))}
                  </div>
                ) : (
                  <p className="step-note">
                    {date === "" ? "Choose a day first." : "No times are left on that day."}{" "}
                    <button type="button" className="editorial-link" onClick={() => setStep(1)}>
                      Pick another day
                    </button>
                  </p>
                )}

                <span className="demo-note">
                  Times shown are still free right now. The salon confirms every request.
                </span>
              </>
            )}

            {step === 3 && (
              <div className="wiz-form">
                <label>
                  Full name *
                  <input
                    required
                    value={details.name}
                    onChange={(e) => setDetails({ ...details, name: e.target.value })}
                    autoComplete="name"
                  />
                </label>
                <label>
                  Phone *
                  <input
                    required
                    type="tel"
                    value={details.phone}
                    onChange={(e) => setDetails({ ...details, phone: e.target.value })}
                    autoComplete="tel"
                  />
                </label>
                <label className="full">
                  Email
                  <input
                    type="email"
                    value={details.email}
                    onChange={(e) => setDetails({ ...details, email: e.target.value })}
                    autoComplete="email"
                  />
                </label>
                <label className="full">
                  Notes
                  <textarea
                    rows={3}
                    value={details.notes}
                    onChange={(e) => setDetails({ ...details, notes: e.target.value })}
                    placeholder="Anything we should know?"
                  />
                </label>
                {!validDetails && (details.name !== "" || details.phone !== "") && (
                  <span className="demo-note full">
                    Please add your name and a phone number the salon can reach you on.
                  </span>
                )}
              </div>
            )}

            {step === 4 && service !== null && (
              <Summary service={service} date={date} time={time} details={details} />
            )}

            {step === 5 && confirmed !== null && (
              <div className="confirmation" role="status">
                <p className="conf-ref">
                  Reference <strong>{confirmed.reference}</strong>
                </p>
                <Summary
                  service={services?.find((s) => s.id === confirmed.serviceId) ?? null}
                  date={confirmed.date}
                  time={confirmed.time}
                  details={confirmed.details}
                />
                <p className="demo-note">
                  Your request is with the salon. We will call you to confirm the appointment — it
                  is not booked until we do.
                </p>
                <div className="manage-actions">
                  <Button
                    variant="ghost"
                    type="button"
                    className="btn btn-ink h-auto rounded-none"
                    onClick={restart}
                  >
                    Book another
                  </Button>
                  <Link to="/" className="editorial-link">
                    Back home <ArrowRight size={16} />
                  </Link>
                </div>
              </div>
            )}
          </div>

          {step < 5 && (
            <div className="step-nav">
              {step > 0 ? (
                <Button
                  variant="ghost"
                  type="button"
                  className="btn btn-outline h-auto rounded-none"
                  onClick={() => setStep(step - 1)}
                >
                  <ArrowLeft size={16} /> Back
                </Button>
              ) : (
                <span />
              )}
              {step < 4 ? (
                <Button
                  variant="ghost"
                  type="button"
                  className="btn btn-ink h-auto rounded-none"
                  disabled={!canContinue}
                  onClick={() => setStep(step + 1)}
                >
                  Continue <ArrowRight size={16} />
                </Button>
              ) : (
                <Button
                  variant="ghost"
                  type="button"
                  className="btn btn-ink h-auto rounded-none"
                  disabled={!canContinue || submitting}
                  onClick={() => void submit()}
                >
                  {submitting ? "Sending…" : "Request appointment"} <Check size={16} />
                </Button>
              )}
            </div>
          )}
        </div>
      </section>
    </SiteLayout>
  );
}

function Summary({
  service,
  date,
  time,
  details,
}: {
  service: ApiService | null;
  date: string;
  time: string;
  details: Details;
}) {
  const price = service === null ? null : formatPrice(service.price_cents);

  return (
    <dl className="summary">
      <div>
        <dt>Service</dt>
        <dd>
          {service?.name ?? "—"} {service && <small>{service.category}</small>}
        </dd>
      </div>
      <div>
        <dt>Date</dt>
        <dd>{date ? fmt(date) : "—"}</dd>
      </div>
      <div>
        <dt>Time</dt>
        <dd>{time || "—"}</dd>
      </div>
      <div>
        <dt>Name</dt>
        <dd>{details.name}</dd>
      </div>
      <div>
        <dt>Phone</dt>
        <dd>{details.phone}</dd>
      </div>
      {details.email && (
        <div>
          <dt>Email</dt>
          <dd>{details.email}</dd>
        </div>
      )}
      <div>
        <dt>Price</dt>
        <dd>{price ?? "Price · to be confirmed"}</dd>
      </div>
    </dl>
  );
}
