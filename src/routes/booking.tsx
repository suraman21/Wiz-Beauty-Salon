import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { ArrowLeft, ArrowRight, Check } from "lucide-react";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { demoTimes, images, PRICE_PLACEHOLDER } from "@/data/wiz";
import {
  serviceCategories,
  findBookableService,
  isValidServiceSelection,
  bookingDetailsSchema,
  UNDECIDED_SERVICE,
  UNDECIDED_LABEL,
  type BookableService,
} from "@/data/services";

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
type Details = { name: string; phone: string; email: string; notes: string };
type Confirmed = { ref: string; serviceId: string; date: string; time: string; details: Details };

function nextDays(n: number) {
  const out: Date[] = [];
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  for (let i = 1; out.length < n; i++) {
    const x = new Date(d);
    x.setDate(d.getDate() + i);
    out.push(x);
  }
  return out;
}
const fmt = (iso: string) =>
  new Date(iso).toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long" });

function Booking() {
  const search = Route.useSearch();
  const initial = findBookableService(search.service)?.id ?? "";
  const [step, setStep] = useState(initial ? 1 : 0);
  const [serviceId, setServiceId] = useState(initial);
  const [categoryId, setCategoryId] = useState(findBookableService(initial)?.categoryId ?? "");
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [details, setDetails] = useState<Details>({ name: "", phone: "", email: "", notes: "" });
  const [confirmed, setConfirmed] = useState<Confirmed | null>(null);
  const [cancelled, setCancelled] = useState(false);
  const [rescheduling, setRescheduling] = useState(false);
  const days = useMemo(() => nextDays(14), []);
  const service = findBookableService(serviceId);
  const selectedCategory = serviceCategories.find((c) => c.id === categoryId);
  const undecided = serviceId === UNDECIDED_SERVICE;
  const validDetails = bookingDetailsSchema.safeParse(details).success;

  const canNext = [
    isValidServiceSelection(serviceId),
    !!date,
    !!time,
    validDetails,
    isValidServiceSelection(serviceId) && !!date && !!time && validDetails,
  ][step];

  const confirm = () => {
    if (!isValidServiceSelection(serviceId) || !date || !time || !validDetails) return;
    setConfirmed({
      ref: confirmed?.ref ?? `WIZ-${Math.random().toString(36).slice(2, 7).toUpperCase()}`,
      serviceId,
      date,
      time,
      details,
    });
    setCancelled(false);
    setRescheduling(false);
    setStep(5);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const restart = () => {
    setConfirmed(null);
    setCancelled(false);
    setRescheduling(false);
    setServiceId("");
    setCategoryId("");
    setDate("");
    setTime("");
    setDetails({ name: "", phone: "", email: "", notes: "" });
    setStep(0);
  };

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <img src={images.portrait} alt="Demo editorial beauty portrait" />
          <div className="booking-visual-copy">
            <span className="overline">Book your moment</span>
            <p>
              Beauty,
              <br />
              <em>your way.</em>
            </p>
          </div>
        </aside>

        <div className="booking-panel">
          <span className="overline">Booking {rescheduling && "· Rescheduling"}</span>
          <h1>
            {step === 5 ? (
              cancelled ? (
                <>
                  Demo request
                  <br />
                  <em>cancelled.</em>
                </>
              ) : (
                <>
                  Demo request
                  <br />
                  <em>reviewed.</em>
                </>
              )
            ) : (
              <>
                Book an
                <br />
                <em>appointment.</em>
              </>
            )}
          </h1>

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

          <div className="step-body" key={step}>
            {step === 0 && (
              <>
                <label className={`option ${undecided ? "is-selected" : ""}`}>
                  <input
                    type="radio"
                    name="service"
                    checked={undecided}
                    onChange={() => {
                      setServiceId(UNDECIDED_SERVICE);
                      setCategoryId("");
                    }}
                  />
                  <span className="opt-name">{UNDECIDED_LABEL}</span>
                  <span className="opt-meta">Undecided request</span>
                </label>
                <div className="wiz-form my-6">
                  <label className="full">
                    Category
                    <select
                      className="w-full min-w-0 border-b border-input bg-background py-3 text-base font-normal normal-case tracking-normal"
                      aria-label="Service category"
                      value={categoryId}
                      onChange={(e) => {
                        setCategoryId(e.target.value);
                        setServiceId("");
                      }}
                    >
                      <option value="">Choose a category</option>
                      {serviceCategories.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name} — {c.amharic}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
                {selectedCategory && (
                  <fieldset className="svc-group">
                    <legend>
                      {selectedCategory.name}{" "}
                      <span lang="am" className="block font-sans text-lg">
                        {selectedCategory.amharic}
                      </span>
                    </legend>
                    {selectedCategory.items.map((i) => (
                      <label
                        key={i.id}
                        className={`option ${serviceId === i.id ? "is-selected" : ""}`}
                      >
                        <input
                          type="radio"
                          name="service"
                          checked={serviceId === i.id}
                          onChange={() => setServiceId(i.id)}
                        />
                        <span className="opt-name">{i.name}</span>
                        <span className="opt-meta">{PRICE_PLACEHOLDER}</span>
                      </label>
                    ))}
                  </fieldset>
                )}
              </>
            )}

            {step === 1 && (
              <div className="date-grid" role="radiogroup" aria-label="Choose a date">
                {days.map((d) => {
                  const iso = d.toISOString();
                  return (
                    <Button
                      variant="ghost"
                      type="button"
                      role="radio"
                      aria-checked={date === iso}
                      key={iso}
                      className={`date-cell h-auto rounded-none ${date === iso ? "is-selected" : ""}`}
                      onClick={() => {
                        setDate(iso);
                        setTime("");
                      }}
                    >
                      <span>{d.toLocaleDateString("en-GB", { weekday: "short" })}</span>
                      <strong>{d.getDate()}</strong>
                      <span>{d.toLocaleDateString("en-GB", { month: "short" })}</span>
                    </Button>
                  );
                })}
              </div>
            )}

            {step === 2 && (
              <>
                <p className="step-hint">{fmt(date)}</p>
                <div className="time-grid" role="radiogroup" aria-label="Choose a time">
                  {demoTimes.map((t) => (
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
                <span className="demo-note">
                  Demo times — real availability will come from the salon.
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
              </div>
            )}

            {step === 4 && isValidServiceSelection(serviceId) && (
              <Summary service={service} date={date} time={time} details={details} />
            )}

            {step === 5 &&
              confirmed &&
              (() => {
                const s = findBookableService(confirmed.serviceId);
                return (
                  <div className="confirmation" role="status">
                    {cancelled ? (
                      <p>
                        Your demo request <strong>{confirmed.ref}</strong> has been cancelled.
                      </p>
                    ) : (
                      <>
                        <p className="conf-ref">
                          Reference <strong>{confirmed.ref}</strong>
                        </p>
                        <Summary
                          service={s}
                          date={confirmed.date}
                          time={confirmed.time}
                          details={confirmed.details}
                        />
                        <div className="manage">
                          <span className="overline">Manage booking</span>
                          <div className="manage-actions">
                            <Button
                              variant="ghost"
                              type="button"
                              className="btn btn-outline h-auto rounded-none"
                              onClick={() => {
                                setRescheduling(true);
                                setStep(1);
                              }}
                            >
                              Reschedule
                            </Button>
                            <Button
                              variant="ghost"
                              type="button"
                              className="btn btn-outline btn-danger h-auto rounded-none"
                              onClick={() => setCancelled(true)}
                            >
                              Cancel demo request
                            </Button>
                          </div>
                        </div>
                      </>
                    )}
                    <span className="demo-note">
                      Demo only — this request has not been saved or sent to WIZ. No appointment has
                      been made.
                    </span>
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
                );
              })()}
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
                  disabled={!canNext}
                  onClick={() => setStep(step + 1)}
                >
                  Continue <ArrowRight size={16} />
                </Button>
              ) : (
                <Button
                  variant="ghost"
                  type="button"
                  className="btn btn-ink h-auto rounded-none"
                  disabled={!canNext}
                  onClick={confirm}
                >
                  {rescheduling ? "Review new time" : "Review demo request"} <Check size={16} />
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
  service: BookableService | undefined;
  date: string;
  time: string;
  details: Details;
}) {
  return (
    <dl className="summary">
      <div>
        <dt>Service</dt>
        <dd>
          {service?.name ?? UNDECIDED_LABEL}{" "}
          {service ? (
            <small>
              {service.category} — <span lang="am">{service.amharic}</span>
            </small>
          ) : (
            <small>Undecided request</small>
          )}
        </dd>
      </div>
      <div>
        <dt>Date</dt>
        <dd>{fmt(date)}</dd>
      </div>
      <div>
        <dt>Time</dt>
        <dd>{time}</dd>
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
        <dd>{PRICE_PLACEHOLDER}</dd>
      </div>
    </dl>
  );
}
