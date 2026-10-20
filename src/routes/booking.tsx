import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CalendarPlus,
  Check,
  Clock3,
  Copy,
  ExternalLink,
  MessageCircle,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useSiteImages } from "@/lib/site-content";
import { bookableServices, bookingDetailsSchema, findBookableService } from "@/data/services";
import { ApiError, formatPrice, isoToday } from "@/lib/api";
import {
  createBooking,
  fetchCalendar,
  fetchServices,
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
  manageUrl: string | null;
};

const emptyDetails: Details = { name: "", phone: "", email: "", notes: "" };
const parseIso = (iso: string) => new Date(`${iso}T00:00:00`);
const fmt = (iso: string) =>
  parseIso(iso).toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long" });

function Booking() {
  const images = useSiteImages();
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
  const [copied, setCopied] = useState(false);

  const service = useMemo(
    () => services?.find((s) => s.id === serviceId) ?? null,
    [services, serviceId],
  );

  // Fallback for preview / offline: use local catalogue if API is unavailable
  const fallbackServices = (): ApiService[] =>
    bookableServices.map((s, idx) => ({
      id: idx + 1,
      name: s.name,
      slug: s.id,
      category: s.category,
      description: "",
      duration_minutes: 60,
      price_cents: null,
      featured: false,
      image_url: null,
    }));

  useEffect(() => {
    const controller = new AbortController();
    fetchServices(controller.signal)
      .then((list) => {
        if (list.length > 0) {
          setServices(list);
        } else {
          setServices(fallbackServices());
        }
        setLoadError("");
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") return;
        // Use local catalogue so the booking UI remains usable even when API is down
        setServices(fallbackServices());
        setLoadError("");
        if (error instanceof ApiError && error.message) {
          // keep silently — the demo catalogue is shown instead of an error wall
        }
      });
    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (!services || appliedSearch.current || !search.service) return;
    appliedSearch.current = true;
    const bySlug = services.find((s) => s.slug === search.service);
    const byId = services.find((s) => String(s.id) === search.service);
    const catalogueName = findBookableService(search.service)?.name.toLowerCase();
    const byName = catalogueName
      ? services.find((s) => s.name.toLowerCase() === catalogueName)
      : undefined;
    const match = bySlug ?? byId ?? byName;
    if (match) {
      setServiceId(match.id);
      setCategory(match.category);
      setStep(1);
    }
  }, [services, search.service]);

  const loadCalendar = useCallback((id: number, signal?: AbortSignal) => {
    setCalendarBusy(true);
    setCalendarError("");
    return fetchCalendar(id, isoToday(), CALENDAR_DAYS, signal)
      .then((days) => setCalendar(days))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") return;
        setCalendar([]);
        setCalendarError(
          error instanceof ApiError ? error.message : "We could not load the available times.",
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

  const categories = useMemo(() => {
    const seen: string[] = [];
    for (const s of services ?? []) if (!seen.includes(s.category)) seen.push(s.category);
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

  const handleCategoryChange = (value: string) => {
    const next = value === "__all" ? "" : value;
    setCategory(next);
    // If current service doesn't belong to new category, clear selection
    if (serviceId !== null) {
      const current = services?.find((s) => s.id === serviceId);
      if (current && next !== "" && current.category !== next) setServiceId(null);
    }
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
      setConfirmed({
        reference: result.reference,
        serviceId,
        date,
        time,
        details,
        manageUrl: result.manage_url ?? null,
      });
      setStep(5);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      const message =
        error instanceof ApiError
          ? error.message
          : "The request could not be sent. Please try again.";
      setSubmitError(message);
      if (error instanceof ApiError && error.status === 422) {
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
    if (step === 5)
      return (
        <>
          Request
          <br />
          <em>received.</em>
        </>
      );
    return (
      <>
        Book an
        <br />
        <em>appointment.</em>
      </>
    );
  })();

  const copyManageLink = async () => {
    if (!confirmed?.manageUrl) return;
    try {
      await navigator.clipboard.writeText(confirmed.manageUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      window.prompt("Copy this link:", confirmed.manageUrl);
    }
  };

  const whatsAppHref =
    confirmed?.manageUrl && confirmed
      ? `https://wa.me/?text=${encodeURIComponent(`My WIZ booking ${confirmed.reference}: ${confirmed.manageUrl}`)}`
      : "";

  const buildIcs = () => {
    if (!confirmed) return;
    const svc = services?.find((s) => s.id === confirmed.serviceId) ?? null;
    const duration = svc?.duration_minutes ?? 60;
    const start = new Date(`${confirmed.date}T${confirmed.time}:00`);
    const end = new Date(start.getTime() + duration * 60000);
    const pad = (n: number) => String(n).padStart(2, "0");
    const fmtIcs = (d: Date) =>
      `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(d.getHours())}${pad(d.getMinutes())}00`;
    const ics = [
      "BEGIN:VCALENDAR",
      "VERSION:2.0",
      "PRODID:-//WIZ Beauty Salon//Booking//EN",
      "BEGIN:VEVENT",
      `UID:${confirmed.reference}@wiz.arkeonethiopia.com`,
      `DTSTAMP:${fmtIcs(new Date())}`,
      `DTSTART:${fmtIcs(start)}`,
      `DTEND:${fmtIcs(end)}`,
      `SUMMARY:WIZ Beauty Salon - ${svc?.name ?? "Appointment"}`,
      `DESCRIPTION:Reference ${confirmed.reference} - Manage: ${confirmed.manageUrl ?? ""}`,
      `LOCATION:WIZ Beauty Salon, Addis Ababa`,
      "END:VEVENT",
      "END:VCALENDAR",
    ].join("\r\n");
    const blob = new Blob([ics], { type: "text/calendar" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `WIZ-${confirmed.reference}.ics`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const googleCalendarHref = (() => {
    if (!confirmed) return "";
    const svc = services?.find((s) => s.id === confirmed.serviceId) ?? null;
    const duration = svc?.duration_minutes ?? 60;
    const start = new Date(`${confirmed.date}T${confirmed.time}:00`);
    const end = new Date(start.getTime() + duration * 60000);
    const pad = (n: number) => String(n).padStart(2, "0");
    const fmtG = (d: Date) =>
      `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(d.getHours())}${pad(d.getMinutes())}00`;
    const params = new URLSearchParams({
      action: "TEMPLATE",
      text: `WIZ Beauty Salon - ${svc?.name ?? "Appointment"} (${confirmed.reference})`,
      dates: `${fmtG(start)}/${fmtG(end)}`,
      details: `Reference ${confirmed.reference} - Manage: ${confirmed.manageUrl ?? ""}`,
      location: "WIZ Beauty Salon, Addis Ababa",
    });
    return `https://calendar.google.com/calendar/render?${params.toString()}`;
  })();

  // Group services by category for the "All" view
  const grouped = useMemo(() => {
    const map = new Map<string, ApiService[]>();
    for (const c of categories)
      map.set(
        c,
        visible.filter((s) => s.category === c),
      );
    // when category === "" we show all groups, else only one
    if (category !== "") return [[category, visible]] as [string, ApiService[]][];
    return Array.from(map.entries()).filter(([, list]) => list.length > 0);
  }, [categories, visible, category]);

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <div className="booking-visual-frame">
            <img src={images.portrait.url} alt={images.portrait.alt} fetchPriority="high" />
          </div>
          <div className="booking-visual-copy">
            <span className="overline">Book your moment</span>
            <p>
              Beauty,
              <br /> <em>your way.</em>
            </p>
            <span className="booking-visual-note">Addis Ababa · By appointment</span>
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
                    <p className="step-intro">
                      Choose a category, then pick the service that fits your moment. You can change
                      it anytime.
                    </p>

                    <div className="booking-selects">
                      <div className="booking-field">
                        <label htmlFor="wiz-category">Category</label>
                        <Select
                          value={category === "" ? "__all" : category}
                          onValueChange={handleCategoryChange}
                        >
                          <SelectTrigger id="wiz-category" className="booking-select-trigger">
                            <SelectValue placeholder="All services" />
                          </SelectTrigger>
                          <SelectContent className="booking-select-content">
                            <SelectItem value="__all">
                              All services — {services.length} options
                            </SelectItem>
                            {categories.map((c) => {
                              const count = services.filter((s) => s.category === c).length;
                              return (
                                <SelectItem key={c} value={c}>
                                  {c} · {count}
                                </SelectItem>
                              );
                            })}
                          </SelectContent>
                        </Select>
                        <span className="field-hint">
                          {categories.length} categories · Tap to filter
                        </span>
                      </div>

                      <div className="booking-field">
                        <label htmlFor="wiz-service">Service</label>
                        <Select
                          value={serviceId !== null ? String(serviceId) : ""}
                          onValueChange={(v) => chooseService(Number(v))}
                        >
                          <SelectTrigger id="wiz-service" className="booking-select-trigger">
                            <SelectValue placeholder="Choose a service" />
                          </SelectTrigger>
                          <SelectContent
                            className="booking-select-content"
                            style={{ maxHeight: "22rem" }}
                          >
                            {grouped.map(([cat, list]) => (
                              <SelectGroup key={cat}>
                                <SelectLabel className="booking-select-label">
                                  {cat} — {list.length}
                                </SelectLabel>
                                {list.map((s) => {
                                  const price = formatPrice(s.price_cents);
                                  return (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                      <span className="svc-select-item">
                                        <span>{s.name}</span>
                                        <span className="svc-select-meta">
                                          {price ?? "Price on request"}
                                        </span>
                                      </span>
                                    </SelectItem>
                                  );
                                })}
                              </SelectGroup>
                            ))}
                          </SelectContent>
                        </Select>
                        <span className="field-hint">
                          {visible.length} services{category ? ` in ${category}` : ""} ·{" "}
                          {service ? `Selected: ${service.name}` : "Choose to continue"}
                        </span>
                      </div>
                    </div>

                    {service && (
                      <div className="service-preview">
                        <div className="service-preview-icon">
                          <Sparkles size={18} />
                        </div>
                        <div className="service-preview-main">
                          <strong>{service.name}</strong>
                          <span>
                            {service.category}{" "}
                            {service.duration_minutes ? `· ${service.duration_minutes} min` : ""}
                          </span>
                        </div>
                        <div className="service-preview-price">
                          <span>{formatPrice(service.price_cents) ?? "Price on request"}</span>
                          <small>Est.</small>
                        </div>
                      </div>
                    )}

                    {!service && (
                      <p className="demo-note" style={{ marginTop: "1rem" }}>
                        Not sure? Pick the closest match — you can add notes later and the salon
                        will confirm.
                      </p>
                    )}
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
                    {service && (
                      <p className="step-service-hint">
                        For <strong>{service.name}</strong> · {service.category}
                      </p>
                    )}
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
                        <Clock3 size={14} /> {t}
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

                <div className="confirm-banner">
                  <ShieldCheck size={18} />
                  <div>
                    <strong>Request received — we’ll call to confirm</strong>
                    <span>It is not booked until the salon confirms. You can manage it below.</span>
                  </div>
                </div>

                {confirmed.manageUrl ? (
                  <div className="manage-card">
                    <div className="manage-card-head">
                      <h3>Keep your manage link</h3>
                      <span className="manage-badge">
                        <ShieldCheck size={12} /> Valid 24 hours
                      </span>
                    </div>
                    <p className="manage-card-copy">
                      This signed link lets you reschedule or cancel without calling. Save it — it
                      works even if you close this page.
                    </p>
                    <div className="manage-link-row">
                      <code className="manage-link-url" title={confirmed.manageUrl}>
                        {confirmed.manageUrl}
                      </code>
                      <Button
                        variant="ghost"
                        type="button"
                        className="btn btn-outline h-auto rounded-md manage-copy-btn"
                        onClick={copyManageLink}
                      >
                        {copied ? <Check size={14} /> : <Copy size={14} />}{" "}
                        {copied ? "Copied" : "Copy"}
                      </Button>
                    </div>
                    <div className="manage-actions-grid">
                      <a href={confirmed.manageUrl} className="btn btn-ink manage-primary">
                        <ExternalLink size={14} /> Open manage page
                      </a>
                      <a
                        href={whatsAppHref}
                        target="_blank"
                        rel="noreferrer"
                        className="btn btn-outline"
                      >
                        <MessageCircle size={14} /> Share on WhatsApp
                      </a>
                    </div>
                    <div className="manage-actions-grid manage-actions-grid--2">
                      <button type="button" className="btn btn-outline" onClick={buildIcs}>
                        <CalendarPlus size={14} /> Add to calendar (.ics)
                      </button>
                      <a
                        href={googleCalendarHref}
                        target="_blank"
                        rel="noreferrer"
                        className="btn btn-outline"
                      >
                        <CalendarPlus size={14} /> Google Calendar
                      </a>
                    </div>
                    <p className="manage-policy">
                      Free to change or cancel until 24 hours before your time. After that, please
                      call the salon.
                    </p>
                  </div>
                ) : (
                  <div className="manage-card manage-card--muted">
                    <h3>Manage link unavailable</h3>
                    <p>
                      We couldn’t create a manage link for this request. Check your email/SMS or
                      call the salon and give your reference.
                    </p>
                  </div>
                )}

                <div className="recover-card">
                  <h4>Lost your link?</h4>
                  <p>
                    You can recover any booking with your phone number — no password needed. For
                    now, keep your reference <strong>{confirmed.reference}</strong> and call the
                    salon if you need to make a change.
                  </p>
                  <div className="recover-actions">
                    <Link to="/contact" className="editorial-link">
                      Contact salon <ArrowRight size={14} />
                    </Link>
                    <span className="recover-hint">Recover via phone — coming soon</span>
                  </div>
                </div>

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
        <dd>{price ?? "Price on request"}</dd>
      </div>
    </dl>
  );
}
