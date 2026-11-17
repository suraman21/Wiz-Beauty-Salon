import { createFileRoute, Link } from "@tanstack/react-router";
import { SmartImage } from "@/components/site/SmartImage";
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
} from "lucide-react";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { useBookingPolicy, useSiteImages } from "@/lib/site-content";
import { bookingDetailsSchema } from "@/data/services";
import { ApiError, isoToday } from "@/lib/api";
import {
  EC_MONTHS_EN,
  formatEthiopian,
  formatEthiopianClock,
  formatGregorian,
  gregorianClockLabel,
  toEthiopian,
  toEthiopianClock,
} from "@/lib/ethiopian-calendar";
import {
  createBooking,
  fetchCalendar,
  fetchCatalogue,
  type ApiService,
  type CalendarDay,
} from "@/lib/booking-api";
import { localServices } from "@/lib/service-pages";

/** As many services as one visit can reasonably hold. */
const MAX_SERVICES = 6;

/** 01, 02 … for unchosen cards; 1, 2 … shows the order picked. */
const pickedMark = (position: number): string => String(position + 1);

/**
 * "about an hour" reads better than "45 minutes" when the salon may run over.
 * Anything under an hour is exact, because there the number is the point.
 */
function describeMinutes(minutes: number): string {
  if (minutes < 60) return `${minutes} minutes`;
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  const hourText = hours === 1 ? "an hour" : `${hours} hours`;

  return rest === 0 ? hourText : `${hourText} ${rest} minutes`;
}

export const Route = createFileRoute("/booking")({
  validateSearch: (s) => z.object({ service: z.string().optional() }).parse(s),
  head: () => ({
    meta: [
      { title: "Book an appointment | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "Book your WIZ Beauty Salon appointment: choose your service, then a day and a time. The salon confirms every request.",
      },
      { property: "og:title", content: "Book an appointment | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Choose your service, day and time — the salon confirms every request.",
      },
      { property: "og:type", content: "website" },
      { property: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Booking,
});

const steps = ["Service", "Date", "Time", "Details", "Summary"];
const CONFIRMED_STEP = 5;
const CALENDAR_DAYS = 21;

type Details = { name: string; phone: string; email: string; notes: string };
type Confirmed = {
  reference: string;
  serviceName: string;
  date: string;
  time: string;
  details: Details;
  manageUrl: string | null;
};

const emptyDetails: Details = { name: "", phone: "", email: "", notes: "" };
const parseIso = (iso: string) => new Date(`${iso}T00:00:00`);

/**
 * The day, as the salon keeps it.
 *
 * "ማክሰኞ ህዳር 8, 2019 ዓ.ም · 17 Nov 2026" — Ethiopian calendar first, because
 * that is the day the customer means, with the Gregorian one beside it so a
 * booking can be matched either way. Storage and the API stay Gregorian.
 */
const fmt = (iso: string) => {
  const ethiopian = formatEthiopian(iso, "full", "");

  if (ethiopian === "") return iso;

  return `${ethiopian} · ${formatGregorian(iso, iso)}`;
};

/** The compact month and weekday the date cells have room for. */
/**
 * One date cell: the Ethiopian day is the headline, the Ethiopian month is
 * spelled out in full (a three-letter "HID" beside seven neighbours reads as a
 * code, not a month), and the Gregorian date sits underneath so nobody has to
 * hover to find out which day of the salon's calendar they just picked.
 */
const cellParts = (iso: string) => {
  const date = parseIso(iso);
  const ethiopian = toEthiopian(date);

  return {
    weekday: date.toLocaleDateString("en-GB", { weekday: "short" }),
    day: ethiopian === null ? date.getDate() : ethiopian.day,
    month: ethiopian === null ? "" : EC_MONTHS_EN[ethiopian.month],
    monthAm: ethiopian === null ? "" : (formatEthiopian(iso, "amharic", "").split(" ")[0] ?? ""),
    gregorian: date.toLocaleDateString("en-GB", { day: "numeric", month: "short" }),
    title: fmt(iso),
  };
};

function Booking() {
  const images = useSiteImages();
  const search = Route.useSearch();
  const appliedSearch = useRef(false);

  const [services, setServices] = useState<ApiService[] | null>(null);
  const [loadError, setLoadError] = useState("");
  // One appointment can carry several services: hair and nails on the same
  // Friday afternoon is one visit, not two. The ids stay in the order the
  // customer picked them, because that is the order they will be done in.
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [run, setRun] = useState<{ name: string; minutes: number } | null>(null);

  const [calendar, setCalendar] = useState<CalendarDay[]>([]);
  const [calendarBusy, setCalendarBusy] = useState(false);
  const [calendarError, setCalendarError] = useState("");

  const [step, setStep] = useState(0);
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [details, setDetails] = useState<Details>(emptyDetails);

  const policy = useBookingPolicy();
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [confirmed, setConfirmed] = useState<Confirmed | null>(null);
  const [copied, setCopied] = useState(false);

  const chosen = useMemo(
    () => (services ?? []).filter((s) => selectedIds.includes(s.id)),
    [services, selectedIds],
  );

  // What the salon will call this visit — "Make Up + Manicure".
  const serviceName = useMemo(() => chosen.map((s) => s.name).join(" + "), [chosen]);

  // Until the panel answers, the bundled list keeps the page usable.
  const fallback = (): ApiService[] =>
    localServices().map((s, index) => ({
      id: index + 1,
      name: s.name,
      slug: s.id,
      amharic: s.amharic,
      description: s.description,
      image_url: null,
      show_on_home: s.showOnHome,
    }));

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        if (catalogue.services.length > 0) setServices(catalogue.services);
        else setServices(fallback());
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") return;
        setServices(fallback());
        setLoadError("");
      });

    return () => controller.abort();
  }, []);

  // A link from the services page: /booking?service=<slug or name>
  useEffect(() => {
    if (!services || appliedSearch.current || !search.service) return;
    appliedSearch.current = true;

    const wanted = search.service.trim().toLowerCase();
    const match =
      services.find((s) => s.slug === search.service) ??
      services.find((s) => String(s.id) === search.service) ??
      services.find((s) => s.name.trim().toLowerCase() === wanted);

    if (match) setSelectedIds([match.id]);
  }, [services, search.service]);

  const loadCalendar = useCallback((categoryIds: number[], signal?: AbortSignal) => {
    setCalendarBusy(true);
    setCalendarError("");

    return fetchCalendar({ categoryIds }, isoToday(), CALENDAR_DAYS, signal)
      .then((result) => {
        setCalendar(result.days);
        // The API answers with the whole run, so the page never has to guess
        // how long several services take together.
        setRun(
          result.service !== null && result.service.name !== ""
            ? { name: result.service.name, minutes: result.service.duration_minutes }
            : null,
        );
      })
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
    if (selectedIds.length === 0) return undefined;

    const controller = new AbortController();
    void loadCalendar(selectedIds, controller.signal);

    return () => controller.abort();
  }, [selectedIds, loadCalendar]);

  const selectedDay = calendar.find((d) => d.date === date) ?? null;
  const validDetails = bookingDetailsSchema.safeParse(details).success;

  const canContinue =
    (step === 0 && selectedIds.length > 0) ||
    (step === 1 && date !== "") ||
    (step === 2 && time !== "") ||
    (step === 3 && validDetails) ||
    step === 4;

  /**
   * Add or remove one service. Choosing a second one widens the run, so any
   * date or time already picked has to go — the calendar is re-fetched for the
   * new length and the times that no longer fit disappear.
   */
  const toggleService = (id: number) => {
    setSelectedIds((previous) => {
      const next = previous.includes(id)
        ? previous.filter((chosenId) => chosenId !== id)
        : [...previous, id].slice(0, MAX_SERVICES);

      if (next.length !== previous.length || previous.includes(id)) {
        setCalendar([]);
        setDate("");
        setTime("");
      }

      return next;
    });
  };

  const chooseDate = (day: CalendarDay) => {
    setDate(day.date);
    setTime("");
    setStep(2);
  };

  const submit = () => {
    if (selectedIds.length === 0 || date === "" || time === "") return;
    setSubmitting(true);
    setSubmitError("");

    createBooking({
      name: details.name,
      phone: details.phone,
      email: details.email,
      category_ids: selectedIds,
      date,
      time,
      notes: details.notes,
    })
      .then((result) => {
        setConfirmed({
          reference: result.reference,
          serviceName: serviceName === "" ? "Appointment" : serviceName,
          date,
          time,
          details,
          manageUrl: result.manage_url ?? null,
        });
        setStep(CONFIRMED_STEP);
      })
      .catch((error: unknown) => {
        setSubmitError(
          error instanceof ApiError
            ? error.message
            : "We could not send the request. Please try again or call the salon.",
        );
      })
      .finally(() => setSubmitting(false));
  };

  const restart = () => {
    setConfirmed(null);
    setStep(0);
    setSelectedIds([]);
    setRun(null);
    setDate("");
    setTime("");
    setDetails(emptyDetails);
    setCalendar([]);
  };

  const copyManageLink = () => {
    if (!confirmed?.manageUrl) return;
    void navigator.clipboard.writeText(confirmed.manageUrl).then(() => {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    });
  };

  const heading =
    confirmed !== null
      ? "You're in the book."
      : (["Which service?", "Which day?", "What time?", "Your details", "Everything right?"][
          step
        ] ?? "Book your moment");

  const start = confirmed === null ? null : new Date(`${confirmed.date}T${confirmed.time}:00`);
  const endOfVisit = start === null ? null : new Date(start.getTime() + 60 * 60 * 1000);

  const icsHref = (() => {
    if (confirmed === null || start === null || endOfVisit === null) return "";
    const pad = (n: number) => String(n).padStart(2, "0");
    const stamp = (d: Date) =>
      `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(d.getHours())}${pad(d.getMinutes())}00`;
    const body = [
      "BEGIN:VCALENDAR",
      "VERSION:2.0",
      "PRODID:-//WIZ Beauty Salon//Booking//EN",
      "BEGIN:VEVENT",
      `UID:${confirmed.reference}@wizbeautysalon`,
      `DTSTAMP:${stamp(new Date())}`,
      `DTSTART:${stamp(start)}`,
      `DTEND:${stamp(endOfVisit)}`,
      `SUMMARY:WIZ Beauty Salon - ${confirmed.serviceName}`,
      // DTSTART/DTEND stay Gregorian — that is what calendar apps read — but
      // the human-readable lines carry the day as the salon says it.
      `DESCRIPTION:Reference ${confirmed.reference} · ${formatEthiopian(confirmed.date, "long", "")} · ${formatEthiopianClock(confirmed.time)}`,
      "LOCATION:WIZ Beauty Salon, Addis Ababa",
      "END:VEVENT",
      "END:VCALENDAR",
    ].join("\r\n");

    return `data:text/calendar;charset=utf-8,${encodeURIComponent(body)}`;
  })();

  const whatsAppHref =
    confirmed?.manageUrl != null
      ? `https://wa.me/?text=${encodeURIComponent(
          `My WIZ booking ${confirmed.reference}: ${confirmed.manageUrl}`,
        )}`
      : "";

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <div className="booking-visual-frame">
            <SmartImage
              src={images.portrait.url}
              alt={images.portrait.alt}
              loading="eager"
              fetchPriority="high"
            />
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

          {step < CONFIRMED_STEP && (
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

                {services !== null && services.length === 0 && (
                  <p className="step-note">
                    Online booking is not open just yet. Please contact the salon and we will find
                    you a time.
                  </p>
                )}

                {services !== null && services.length > 0 && (
                  <>
                    <p className="step-intro">
                      Pick the service you are coming in for — or more than one if you are having
                      them together. We book them in one go, back to back, so you only come once.
                      How long it takes and what it costs is settled with you at the salon.
                    </p>

                    <div className="booking-manage-card">
                      <span>Already have a booking?</span>
                      <Link
                        to="/booking/recover"
                        className="editorial-link"
                        style={{ fontSize: "13px" }}
                      >
                        Find your booking with phone or email <ArrowRight size={12} />
                      </Link>
                    </div>

                    <div
                      className="booking-service-grid"
                      role="group"
                      aria-label="Services — choose as many as you need"
                    >
                      {services.map((s, index) => {
                        const picked = selectedIds.includes(s.id);
                        const position = selectedIds.indexOf(s.id);

                        return (
                          <button
                            key={s.id}
                            type="button"
                            aria-pressed={picked}
                            className={`booking-service${picked ? " is-selected" : ""}`}
                            onClick={() => toggleService(s.id)}
                          >
                            <span className="booking-service-index" aria-hidden="true">
                              {picked ? pickedMark(position) : String(index + 1).padStart(2, "0")}
                            </span>
                            <span className="booking-service-name">{s.name}</span>
                            {s.amharic !== "" && (
                              <span lang="am" className="booking-service-am">
                                {s.amharic}
                              </span>
                            )}
                          </button>
                        );
                      })}
                    </div>

                    <p className="booking-pick-hint" role="status">
                      {selectedIds.length === 0 ? (
                        <>Tap a service to start. You can add more than one.</>
                      ) : (
                        <>
                          <strong>
                            {selectedIds.length === 1
                              ? serviceName
                              : `${selectedIds.length} services — ${serviceName}`}
                          </strong>
                          {run !== null && run.minutes > 0 && (
                            <> · about {describeMinutes(run.minutes)}</>
                          )}
                          {"  "}
                          <button
                            type="button"
                            className="editorial-link"
                            onClick={() => {
                              setSelectedIds([]);
                              setCalendar([]);
                              setDate("");
                              setTime("");
                            }}
                          >
                            Clear
                          </button>
                        </>
                      )}
                    </p>
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
                        if (selectedIds.length > 0) void loadCalendar(selectedIds);
                      }}
                    >
                      Try again
                    </button>
                  </p>
                )}
                {!calendarBusy && calendarError === "" && calendar.length > 0 && (
                  <>
                    <p className="step-service-hint">
                      For <strong>{run?.name ?? serviceName ?? "your appointment"}</strong>
                      {run !== null && run.minutes > 0 && (
                        <> · about {describeMinutes(run.minutes)}</>
                      )}
                    </p>
                    <div className="date-grid" role="radiogroup" aria-label="Choose a date">
                      {calendar.map((day) => {
                        const cell = cellParts(day.date);
                        const full = day.slots.length === 0;

                        return (
                          <Button
                            variant="ghost"
                            type="button"
                            role="radio"
                            aria-checked={date === day.date}
                            aria-disabled={full}
                            disabled={full}
                            title={`${cell.title}${full ? ` · ${day.reason ?? "Fully booked"}` : ` · ${day.slots.length} times free`}`}
                            key={day.date}
                            className={`date-cell h-auto rounded-none ${date === day.date ? "is-selected" : ""} ${full ? "is-full" : ""}`}
                            onClick={() => chooseDate(day)}
                          >
                            <span className="date-cell__weekday">{cell.weekday}</span>
                            <strong>{cell.day}</strong>
                            <span className="date-cell__month">{cell.month}</span>
                            <span className="date-cell__gregorian">{cell.gregorian}</span>
                          </Button>
                        );
                      })}
                    </div>
                    <div className="calendar-note">
                      <p>
                        <strong>Reading these dates.</strong> The big number is the day in the
                        Ethiopian calendar, the one the salon books by; the month is written out in
                        full and its Amharic name is in the tooltip. The small grey date underneath
                        is the same day on the Gregorian calendar your phone uses.
                      </p>
                      <p>
                        Greyed cells are days the salon is closed, or days we cannot fill in time.
                        Today is {formatEthiopian(isoToday(), "long", "")}{" "}
                        <span className="calendar-note__gc">({formatGregorian(isoToday())})</span>.
                      </p>
                    </div>
                  </>
                )}
              </>
            )}

            {step === 2 && (
              <>
                <p className="step-hint">{date !== "" ? fmt(date) : ""}</p>
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
                        aria-label={formatEthiopianClock(t)}
                        key={t}
                        className={`chip chip--time h-auto rounded-none ${time === t ? "is-active" : ""}`}
                        onClick={() => setTime(t)}
                      >
                        <Clock3 size={14} />
                        <span className="chip__ethiopian">{toEthiopianClock(t)?.label ?? t}</span>
                        <span className="chip__gregorian">{gregorianClockLabel(t)}</span>
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
                <div className="calendar-note">
                  <p>
                    <strong>Reading these times.</strong> The big time is the Ethiopian clock the
                    salon keeps: the day starts at dawn, so Ethiopian 1 o&rsquo;clock is 7 in the
                    morning and Ethiopian 12 o&rsquo;clock is noon. The small grey time underneath
                    is the same moment on the Gregorian clock, with AM or PM.
                  </p>
                  <p>
                    Every time listed is still free; the salon confirms each request by phone before
                    it is fixed.
                  </p>
                </div>
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

            {step === 4 && (
              <Summary
                serviceName={serviceName === "" ? "Appointment" : serviceName}
                date={date}
                time={time}
                details={details}
              />
            )}

            {step === CONFIRMED_STEP && confirmed !== null && (
              <div className="confirmation" role="status">
                <p className="conf-ref">
                  Reference <strong>{confirmed.reference}</strong>
                </p>

                <Summary
                  serviceName={confirmed.serviceName}
                  date={confirmed.date}
                  time={confirmed.time}
                  details={confirmed.details}
                />

                <div className="confirm-banner">
                  <ShieldCheck size={18} />
                  <div>
                    <strong>Request received — we'll call to confirm</strong>
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
                      <a
                        href={icsHref}
                        download={`WIZ-${confirmed.reference}.ics`}
                        className="btn btn-outline"
                      >
                        <CalendarPlus size={14} /> Add to calendar (.ics)
                      </a>
                      <Link to="/booking/recover" className="btn btn-outline">
                        <CalendarPlus size={14} /> Find this booking later
                      </Link>
                    </div>
                    <p className="manage-policy">{policy.summary}</p>
                    <p className="manage-policy-note">
                      <Link
                        to="/booking/information"
                        search={{ lang: "en" }}
                        className="editorial-link"
                        style={{ fontSize: "12.5px" }}
                      >
                        Read the booking terms <ArrowRight size={12} />
                      </Link>
                    </p>
                  </div>
                ) : (
                  <div className="manage-card manage-card--muted">
                    <h3>Manage link unavailable</h3>
                    <p>
                      We couldn't create a manage link for this request. Check your email or call
                      the salon and give your reference.
                    </p>
                  </div>
                )}

                <div className="recover-card">
                  <h4>Lost your link?</h4>
                  <p>
                    Find your booking through your phone number or email — no password needed. Keep
                    your reference <strong>{confirmed.reference}</strong> handy, or find it now.
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

          {step < CONFIRMED_STEP && (
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
                  onClick={submit}
                >
                  {submitting ? "Sending…" : "Request appointment"} <Check size={16} />
                </Button>
              )}
            </div>
          )}

          {/* The terms sit beside the button that accepts them, not in a footer:
              a customer should be able to read what they are agreeing to at the
              moment they agree to it — and read it in their own language. */}
          {step === 4 && (
            <div className="booking-terms-note">
              <p>
                By requesting this appointment you accept the salon's{" "}
                <Link to="/booking/information" search={{ lang: "en" }}>
                  booking terms
                </Link>
                . {policy.summary}
              </p>
              <p className="booking-terms-note__am">
                ቀጠሮ በመጠየቅ የሳሎኑን{" "}
                <Link to="/booking/information" search={{ lang: "am" }}>
                  የቀጠሮ ደንቦች
                </Link>{" "}
                ተቀብለዋል።
              </p>
            </div>
          )}

          {step < CONFIRMED_STEP && (
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
  serviceName,
  date,
  time,
  details,
}: {
  serviceName: string;
  date: string;
  time: string;
  details: Details;
}) {
  return (
    <div className="summary">
      <div>
        <span>Service</span>
        <strong>{serviceName}</strong>
      </div>
      <div>
        <span>Day</span>
        <strong>{date === "" ? "—" : fmt(date)}</strong>
      </div>
      <div>
        <span>Time</span>
        <strong>{time === "" ? "—" : formatEthiopianClock(time)}</strong>
      </div>
      <div>
        <span>Name</span>
        <strong>{details.name === "" ? "—" : details.name}</strong>
      </div>
      <div>
        <span>Phone</span>
        <strong>{details.phone === "" ? "—" : details.phone}</strong>
      </div>
      {details.notes.trim() !== "" && (
        <div>
          <span>Notes</span>
          <strong>{details.notes}</strong>
        </div>
      )}
    </div>
  );
}
