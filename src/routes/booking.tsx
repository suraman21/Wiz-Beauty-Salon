import { createFileRoute, Link, Outlet, useRouterState } from "@tanstack/react-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CalendarPlus,
  Check,
  Clock3,
  Copy,
  ExternalLink,
  HelpCircle,
  MessageCircle,
  ShieldCheck,
  Sparkles,
  X,
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
  serviceId: number | null;
  serviceIds: number[];
  isUndecided: boolean;
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
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  // When visiting /booking/recover the child route should render, not the booking flow.
  // Without this, the parent would swallow the child (TanStack dot nesting requires Outlet).
  if (pathname === "/booking/recover") {
    return <Outlet />;
  }
  const images = useSiteImages();
  const search = Route.useSearch();
  const appliedSearch = useRef(false);

  const [services, setServices] = useState<ApiService[] | null>(null);
  const [loadError, setLoadError] = useState("");
  const [category, setCategory] = useState("");
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [isUndecided, setIsUndecided] = useState(false);

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

  const serviceId = isUndecided ? null : (selectedIds[0] ?? null);
  const service = useMemo(
    () => services?.find((s) => s.id === serviceId) ?? null,
    [services, serviceId],
  );
  const selectedServices = useMemo(
    () => (services ? services.filter((s) => selectedIds.includes(s.id)) : []),
    [services, selectedIds],
  );
  const totalDuration = useMemo(() => {
    if (isUndecided) return 60;
    if (selectedServices.length === 0) return 0;
    return selectedServices.reduce((sum, s) => sum + (s.duration_minutes > 0 ? s.duration_minutes : 30), 0);
  }, [selectedServices, isUndecided]);
  const totalPrice = useMemo(() => {
    if (isUndecided) return null;
    if (selectedServices.length === 0) return null;
    let sum = 0;
    let hasNull = false;
    for (const s of selectedServices) {
      if (s.price_cents == null) hasNull = true;
      else sum += s.price_cents;
    }
    return hasNull ? null : sum;
  }, [selectedServices, isUndecided]);

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
        setServices(fallbackServices());
        setLoadError("");
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
      setSelectedIds([match.id]);
      setIsUndecided(false);
      setCategory(match.category);
      setStep(1);
    }
  }, [services, search.service]);

  const loadCalendar = useCallback(
    (opts: { serviceId?: number | null; serviceIds?: number[]; isUndecided?: boolean }, signal?: AbortSignal) => {
      setCalendarBusy(true);
      setCalendarError("");
      const calendarOpts = {
        serviceId: opts.serviceId ?? undefined,
        serviceIds: opts.serviceIds,
        isUndecided: opts.isUndecided,
      };
      return fetchCalendar(calendarOpts, isoToday(), CALENDAR_DAYS, signal)
        .then((days) => setCalendar(days))
        .catch((error: unknown) => {
          if (error instanceof DOMException && error.name === "AbortError") return;
          setCalendar([]);
          setCalendarError(
            error instanceof ApiError ? error.message : "We could not load the available times.",
          );
        })
        .finally(() => setCalendarBusy(false));
    },
    [],
  );

  useEffect(() => {
    if (isUndecided) {
      const controller = new AbortController();
      void loadCalendar({ isUndecided: true }, controller.signal);
      return () => controller.abort();
    }
    if (selectedIds.length === 0) return undefined;
    const controller = new AbortController();
    if (selectedIds.length === 1) {
      void loadCalendar({ serviceId: selectedIds[0] }, controller.signal);
    } else {
      void loadCalendar({ serviceIds: selectedIds }, controller.signal);
    }
    return () => controller.abort();
  }, [selectedIds, isUndecided, loadCalendar]);

  const categories = useMemo(() => {
    const seen: string[] = [];
    for (const s of services ?? []) if (!seen.includes(s.category)) seen.push(s.category);
    return seen;
  }, [services]);

  const visible = useMemo(
    () => (services ?? []).filter((s) => category === "" || s.category === category),
    [services, category],
  );

  // For clean dropdown: show only not yet selected
  const availableToAdd = useMemo(
    () => visible.filter((s) => !selectedIds.includes(s.id)),
    [visible, selectedIds],
  );

  const selectedDay = calendar.find((d) => d.date === date) ?? null;
  const validDetails = bookingDetailsSchema.safeParse(details).success;
  const canContinue =
    [isUndecided || selectedIds.length > 0, date !== "", time !== "", validDetails, true][
      step
    ] ?? false;

  const addService = (id: number) => {
    if (isUndecided) setIsUndecided(false);
    setSelectedIds((prev) => (prev.includes(id) ? prev : [...prev, id]));
    setDate("");
    setTime("");
  };

  const removeService = (id: number) => {
    setSelectedIds((prev) => prev.filter((x) => x !== id));
    setDate("");
    setTime("");
  };

  const toggleUndecided = () => {
    if (isUndecided) {
      setIsUndecided(false);
      setSelectedIds([]);
    } else {
      setIsUndecided(true);
      setSelectedIds([]);
      setDate("");
      setTime("");
      setSubmitError("");
    }
  };

  const handleCategoryChange = (value: string) => {
    const next = value === "__all" ? "" : value;
    setCategory(next);
  };

  const chooseDate = (day: CalendarDay) => {
    if (day.slots.length === 0) return;
    setDate(day.date);
    setTime("");
    setStep(2);
  };

  const submit = async () => {
    if ((selectedIds.length === 0 && !isUndecided) || !date || !time) return;
    setSubmitting(true);
    setSubmitError("");
    try {
      const result = await createBooking({
        name: details.name.trim(),
        phone: details.phone.trim(),
        email: details.email.trim(),
        service_id: isUndecided ? null : selectedIds[0] ?? null,
        service_ids: isUndecided ? undefined : selectedIds.length > 1 ? selectedIds : undefined,
        is_undecided: isUndecided,
        date,
        time,
        notes: details.notes.trim(),
      });
      setConfirmed({
        reference: result.reference,
        serviceId: selectedIds[0] ?? null,
        serviceIds: [...selectedIds],
        isUndecided,
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
        if (isUndecided) void loadCalendar({ isUndecided: true });
        else if (selectedIds.length > 1) void loadCalendar({ serviceIds: selectedIds });
        else if (selectedIds[0]) void loadCalendar({ serviceId: selectedIds[0] });
      }
    } finally {
      setSubmitting(false);
    }
  };

  const restart = () => {
    setConfirmed(null);
    setSubmitError("");
    setSelectedIds([]);
    setIsUndecided(false);
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

  const confirmedServices = useMemo(() => {
    if (!confirmed) return [];
    if (confirmed.isUndecided) return [];
    return (services ?? []).filter((s) => confirmed.serviceIds.includes(s.id));
  }, [confirmed, services]);
  const confirmedDuration = useMemo(() => {
    if (!confirmed || confirmed.isUndecided) return 60;
    if (confirmedServices.length === 0) return 60;
    return confirmedServices.reduce((sum, s) => sum + (s.duration_minutes > 0 ? s.duration_minutes : 30), 0);
  }, [confirmed, confirmedServices]);
  const confirmedName = useMemo(() => {
    if (!confirmed) return "Appointment";
    if (confirmed.isUndecided) return "Not sure — we'll help you decide";
    if (confirmedServices.length === 0) {
      const svc = services?.find((s) => s.id === confirmed.serviceId);
      return svc?.name ?? "Appointment";
    }
    return confirmedServices.map((s) => s.name).join(" + ");
  }, [confirmed, confirmedServices, services]);

  const buildIcs = () => {
    if (!confirmed) return;
    const duration = confirmed.isUndecided ? 60 : confirmedDuration ?? 60;
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
      `SUMMARY:WIZ Beauty Salon - ${confirmedName}`,
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
    const duration = confirmed.isUndecided ? 60 : confirmedDuration ?? 60;
    const start = new Date(`${confirmed.date}T${confirmed.time}:00`);
    const end = new Date(start.getTime() + duration * 60000);
    const pad = (n: number) => String(n).padStart(2, "0");
    const fmtG = (d: Date) =>
      `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(d.getHours())}${pad(d.getMinutes())}00`;
    const params = new URLSearchParams({
      action: "TEMPLATE",
      text: `WIZ Beauty Salon - ${confirmedName} (${confirmed.reference})`,
      dates: `${fmtG(start)}/${fmtG(end)}`,
      details: `Reference ${confirmed.reference} - Manage: ${confirmed.manageUrl ?? ""}`,
      location: "WIZ Beauty Salon, Addis Ababa",
    });
    return `https://calendar.google.com/calendar/render?${params.toString()}`;
  })();

  const grouped = useMemo(() => {
    const map = new Map<string, ApiService[]>();
    for (const c of categories)
      map.set(
        c,
        visible.filter((s) => s.category === c),
      );
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
                      Add services as you like — pick one or combine. Not sure? Just book a
                      consultation.
                    </p>

                    <div className="booking-manage-card">
                      <span>Already have a booking?</span>
                      <Link to="/booking/recover" className="editorial-link" style={{ fontSize: "13px" }}>
                        Find your booking with phone or email <ArrowRight size={12} />
                      </Link>
                    </div>

                    {!isUndecided ? (
                      <>
                        <div className="booking-clean-fields">
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
                                  All — {services.length} services
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
                          </div>

                          <div className="booking-field">
                            <label htmlFor="wiz-service">Add a service</label>
                            <Select
                              value=""
                              onValueChange={(v) => addService(Number(v))}
                            >
                              <SelectTrigger id="wiz-service" className="booking-select-trigger">
                                <SelectValue placeholder={selectedIds.length === 0 ? "Choose a service" : "Add another"} />
                              </SelectTrigger>
                              <SelectContent
                                className="booking-select-content"
                                style={{ maxHeight: "22rem" }}
                              >
                                {availableToAdd.length === 0 ? (
                                  <div style={{ padding: "0.75rem 1rem", fontSize: "13px", color: "var(--muted-foreground)" }}>
                                    {visible.length === 0 ? "No services in this category" : "All services from this category added"}
                                  </div>
                                ) : (
                                  grouped
                                    .map(([cat, list]) => {
                                      const filtered = list.filter((s) => !selectedIds.includes(s.id));
                                      if (filtered.length === 0) return null;
                                      return (
                                        <SelectGroup key={cat}>
                                          <SelectLabel className="booking-select-label">
                                            {cat}
                                          </SelectLabel>
                                          {filtered.map((s) => {
                                            const price = formatPrice(s.price_cents);
                                            return (
                                              <SelectItem key={s.id} value={String(s.id)}>
                                                <span className="svc-select-item">
                                                  <span>{s.name}</span>
                                                  <span className="svc-select-meta">
                                                    {price ?? "Price on request"} · {s.duration_minutes || 30} min
                                                  </span>
                                                </span>
                                              </SelectItem>
                                            );
                                          })}
                                        </SelectGroup>
                                      );
                                    })
                                    .filter(Boolean)
                                )}
                              </SelectContent>
                            </Select>
                            <span className="field-hint">
                              {selectedIds.length === 0
                                ? `${visible.length} services · pick one or many`
                                : `${selectedIds.length} selected · ${totalDuration} min`}
                            </span>
                          </div>
                        </div>

                        {selectedServices.length > 0 && (
                          <div className="booking-clean-list">
                            {selectedServices.map((s) => {
                              const price = formatPrice(s.price_cents);
                              return (
                                <div key={s.id} className="booking-clean-item">
                                  <span className="booking-clean-dot" aria-hidden>
                                    <Sparkles size={12} />
                                  </span>
                                  <span className="booking-clean-main">
                                    <strong>{s.name}</strong>
                                    <span>
                                      {s.category} · {s.duration_minutes || 30} min · {price ?? "Price on request"}
                                    </span>
                                  </span>
                                  <button
                                    type="button"
                                    className="booking-clean-remove"
                                    aria-label={`Remove ${s.name}`}
                                    onClick={() => removeService(s.id)}
                                  >
                                    <X size={14} />
                                  </button>
                                </div>
                              );
                            })}
                            <div className="booking-clean-total">
                              <span>
                                {selectedServices.length} service{selectedServices.length > 1 ? "s" : ""} · {totalDuration} min
                              </span>
                              <strong>{totalPrice != null ? formatPrice(totalPrice) ?? "—" : "Price on request"}</strong>
                            </div>
                          </div>
                        )}

                        <button
                          type="button"
                          className="wiz-undecided-link"
                          onClick={toggleUndecided}
                        >
                          <HelpCircle size={14} /> Not sure what you need? <span>Book a consultation — we’ll help you decide</span>
                        </button>
                      </>
                    ) : (
                      <div className="booking-consultation-card">
                        <div className="booking-consultation-icon">
                          <HelpCircle size={18} />
                        </div>
                        <div className="booking-consultation-main">
                          <strong>Consultation — we’ll help you decide</strong>
                          <span>60 min · Talk through what suits you at the salon · Price to be confirmed</span>
                        </div>
                        <button
                          type="button"
                          className="booking-consultation-change"
                          onClick={toggleUndecided}
                        >
                          Change
                        </button>
                      </div>
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
                      onClick={() => {
                        if (isUndecided) void loadCalendar({ isUndecided: true });
                        else if (selectedIds.length > 1) void loadCalendar({ serviceIds: selectedIds });
                        else if (selectedIds[0]) void loadCalendar({ serviceId: selectedIds[0] });
                      }}
                    >
                      Try again
                    </button>
                  </p>
                )}
                {!calendarBusy && calendarError === "" && calendar.length > 0 && (
                  <>
                    {(isUndecided || selectedServices.length > 0) && (
                      <p className="step-service-hint">
                        {isUndecided ? (
                          <>
                            For <strong>Consultation (not sure yet)</strong> · 60 min
                          </>
                        ) : selectedServices.length === 1 ? (
                          <>
                            For <strong>{selectedServices[0].name}</strong> · {selectedServices[0].category} · {totalDuration} min
                          </>
                        ) : (
                          <>
                            For <strong>{selectedServices.map((s) => s.name).join(" + ")}</strong> · {totalDuration} min
                          </>
                        )}
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
                    placeholder={
                      isUndecided
                        ? "Tell us what you’re hoping for — we’ll help you decide at the salon."
                        : selectedServices.length > 1
                          ? "Anything we should know about your services?"
                          : "Anything we should know?"
                    }
                  />
                </label>
                {!validDetails && (details.name !== "" || details.phone !== "") && (
                  <span className="demo-note full">
                    Please add your name and a phone number the salon can reach you on.
                  </span>
                )}
              </div>
            )}

            {step === 4 && (isUndecided || selectedServices.length > 0) && (
              <Summary
                services={isUndecided ? [] : selectedServices}
                isUndecided={isUndecided}
                totalDuration={totalDuration}
                totalPrice={totalPrice}
                date={date}
                time={time}
                details={details}
              />
            )}

            {step === 5 && confirmed !== null && (
              <div className="confirmation" role="status">
                <p className="conf-ref">
                  Reference <strong>{confirmed.reference}</strong>
                </p>
                <Summary
                  services={confirmed.isUndecided ? [] : confirmedServices}
                  isUndecided={confirmed.isUndecided}
                  totalDuration={confirmedDuration}
                  totalPrice={
                    confirmed.isUndecided
                      ? null
                      : confirmedServices.reduce((sum, s) => {
                          if (s.price_cents == null) return sum;
                          return sum + s.price_cents;
                        }, 0) || null
                  }
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
                    Find your booking through your phone number or email — no password needed.
                    Keep your reference <strong>{confirmed.reference}</strong> handy, or find it now.
                  </p>
                  <div className="recover-actions">
                    <Link to="/booking/recover" className="editorial-link">
                      Find your booking <ArrowRight size={14} />
                    </Link>
                    <Link to="/contact" className="editorial-link">
                      Contact salon <ArrowRight size={14} />
                    </Link>
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
          {step < 5 && (
            <p style={{ marginTop: "1.1rem", textAlign: "center" }}>
              <Link to="/booking/recover" className="editorial-link" style={{ fontSize: "13px" }}>
                Find your booking with phone or email <ArrowRight size={12} />
              </Link>
            </p>
          )}
        </div>
      </section>
    </SiteLayout>
  );
}

function Summary({
  services,
  isUndecided,
  totalDuration,
  totalPrice,
  date,
  time,
  details,
}: {
  services: ApiService[];
  isUndecided: boolean;
  totalDuration: number;
  totalPrice: number | null;
  date: string;
  time: string;
  details: Details;
}) {
  const price = totalPrice != null ? formatPrice(totalPrice) : null;
  const name = isUndecided
    ? "Not sure — we'll help you decide"
    : services.length === 0
      ? "—"
      : services.length === 1
        ? services[0].name
        : services.map((s) => s.name).join(" + ");
  const cat = isUndecided
    ? "Consultation · We'll decide together"
    : services.length === 1
      ? services[0].category
      : `${services.length} services · ${totalDuration} min`;
  return (
    <dl className="summary">
      <div>
        <dt>Service</dt>
        <dd>
          {name} <small>{cat}</small>
        </dd>
      </div>
      {services.length > 1 && (
        <div>
          <dt>Selected</dt>
          <dd>
            {services.map((s) => s.name).join(" + ")}
            <small>
              {services.map((s) => `${s.category} · ${s.duration_minutes || 30} min`).join(" · ")}
            </small>
          </dd>
        </div>
      )}
      <div>
        <dt>Date</dt>
        <dd>{date ? fmt(date) : "—"}</dd>
      </div>
      <div>
        <dt>Time</dt>
        <dd>
          {time || "—"} {totalDuration ? <small>· {totalDuration} min</small> : null}
        </dd>
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
        <dd>{isUndecided ? "To be confirmed at salon" : (price ?? "Price on request")}</dd>
      </div>
    </dl>
  );
}
