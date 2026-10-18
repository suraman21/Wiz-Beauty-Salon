import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { ArrowLeft, ArrowRight, Check } from "lucide-react";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteChrome";
import { allServices, categories, demoTimes, images, PRICE_PLACEHOLDER } from "@/data/wiz";

export const Route = createFileRoute("/booking")({
  validateSearch: (s) => z.object({ service: z.string().optional() }).parse(s),
  head: () => ({
    meta: [
      { title: "Book an Appointment | WIZ Beauty Salon" },
      { name: "description", content: "Book your WIZ Beauty Salon appointment: choose a service, date and time in a few simple steps." },
      { property: "og:title", content: "Book an Appointment | WIZ Beauty Salon" },
      { property: "og:description", content: "Choose your service, date and time and book your WIZ moment." },
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
const fmt = (iso: string) => new Date(iso).toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long" });

function Booking() {
  const search = Route.useSearch();
  const initial = allServices.some((s) => s.id === search.service) ? search.service! : "";
  const [step, setStep] = useState(initial ? 1 : 0);
  const [serviceId, setServiceId] = useState(initial);
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [details, setDetails] = useState<Details>({ name: "", phone: "", email: "", notes: "" });
  const [confirmed, setConfirmed] = useState<Confirmed | null>(null);
  const [cancelled, setCancelled] = useState(false);
  const [rescheduling, setRescheduling] = useState(false);
  const days = useMemo(() => nextDays(14), []);
  const service = allServices.find((s) => s.id === serviceId);

  const canNext = [!!serviceId, !!date, !!time, details.name.trim() && details.phone.trim(), true][step];

  const confirm = () => {
    setConfirmed({ ref: confirmed?.ref ?? `WIZ-${Math.random().toString(36).slice(2, 7).toUpperCase()}`, serviceId, date, time, details });
    setCancelled(false);
    setRescheduling(false);
    setStep(5);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const restart = () => {
    setConfirmed(null); setCancelled(false); setRescheduling(false);
    setServiceId(""); setDate(""); setTime(""); setDetails({ name: "", phone: "", email: "", notes: "" }); setStep(0);
  };

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <img src={images.portrait} alt="Demo editorial beauty portrait" />
          <div className="booking-visual-copy"><span className="overline">Book your moment</span><p>Beauty,<br /><em>your way.</em></p></div>
        </aside>

        <div className="booking-panel">
          <span className="overline">Booking {rescheduling && "· Rescheduling"}</span>
          <h1>{step === 5 ? (cancelled ? <>Appointment<br /><em>cancelled.</em></> : <>You're<br /><em>booked.</em></>) : <>Book an<br /><em>appointment.</em></>}</h1>

          {step < 5 && (
            <ol className="stepper" aria-label="Booking progress">
              {steps.map((s, i) => (
                <li key={s} className={i === step ? "is-current" : i < step ? "is-done" : ""}>
                  <button type="button" disabled={i > step} onClick={() => setStep(i)}><span>{i < step ? <Check size={12} /> : i + 1}</span>{s}</button>
                </li>
              ))}
            </ol>
          )}

          <div className="step-body" key={step}>
            {step === 0 && categories.map((c) => (
              <fieldset key={c.id} className="svc-group">
                <legend>{c.name}</legend>
                {c.items.map((i) => (
                  <label key={i.id} className={`option ${serviceId === i.id ? "is-selected" : ""}`}>
                    <input type="radio" name="service" checked={serviceId === i.id} onChange={() => setServiceId(i.id)} />
                    <span className="opt-name">{i.name}</span><span className="opt-meta">{i.duration} · {PRICE_PLACEHOLDER}</span>
                  </label>
                ))}
              </fieldset>
            ))}

            {step === 1 && (
              <div className="date-grid" role="radiogroup" aria-label="Choose a date">
                {days.map((d) => {
                  const iso = d.toISOString();
                  return (
                    <button type="button" role="radio" aria-checked={date === iso} key={iso} className={`date-cell ${date === iso ? "is-selected" : ""}`} onClick={() => { setDate(iso); setTime(""); }}>
                      <span>{d.toLocaleDateString("en-GB", { weekday: "short" })}</span>
                      <strong>{d.getDate()}</strong>
                      <span>{d.toLocaleDateString("en-GB", { month: "short" })}</span>
                    </button>
                  );
                })}
              </div>
            )}

            {step === 2 && (
              <>
                <p className="step-hint">{fmt(date)}</p>
                <div className="time-grid" role="radiogroup" aria-label="Choose a time">
                  {demoTimes.map((t) => <button type="button" role="radio" aria-checked={time === t} key={t} className={`chip ${time === t ? "is-active" : ""}`} onClick={() => setTime(t)}>{t}</button>)}
                </div>
                <span className="demo-note">Demo times — real availability will come from the salon.</span>
              </>
            )}

            {step === 3 && (
              <div className="wiz-form">
                <label>Full name *<input value={details.name} onChange={(e) => setDetails({ ...details, name: e.target.value })} autoComplete="name" /></label>
                <label>Phone *<input type="tel" value={details.phone} onChange={(e) => setDetails({ ...details, phone: e.target.value })} autoComplete="tel" /></label>
                <label className="full">Email<input type="email" value={details.email} onChange={(e) => setDetails({ ...details, email: e.target.value })} autoComplete="email" /></label>
                <label className="full">Notes<textarea rows={3} value={details.notes} onChange={(e) => setDetails({ ...details, notes: e.target.value })} placeholder="Anything we should know?" /></label>
              </div>
            )}

            {step === 4 && service && <Summary service={service} date={date} time={time} details={details} />}

            {step === 5 && confirmed && (() => {
              const s = allServices.find((x) => x.id === confirmed.serviceId)!;
              return (
                <div className="confirmation" role="status">
                  {cancelled ? (
                    <p>Your demo appointment <strong>{confirmed.ref}</strong> has been cancelled.</p>
                  ) : (
                    <>
                      <p className="conf-ref">Reference <strong>{confirmed.ref}</strong></p>
                      <Summary service={s} date={confirmed.date} time={confirmed.time} details={confirmed.details} />
                      <div className="manage">
                        <span className="overline">Manage booking</span>
                        <div className="manage-actions">
                          <button type="button" className="btn btn-outline" onClick={() => { setRescheduling(true); setStep(1); }}>Reschedule</button>
                          <button type="button" className="btn btn-outline btn-danger" onClick={() => setCancelled(true)}>Cancel appointment</button>
                        </div>
                      </div>
                    </>
                  )}
                  <span className="demo-note">Demo booking — no appointment has actually been made. Real bookings will be connected once WIZ confirms its system.</span>
                  <div className="manage-actions">
                    <button type="button" className="btn btn-ink" onClick={restart}>Book another</button>
                    <Link to="/" className="editorial-link">Back home <ArrowRight size={16} /></Link>
                  </div>
                </div>
              );
            })()}
          </div>

          {step < 5 && (
            <div className="step-nav">
              {step > 0 ? <button type="button" className="btn btn-outline" onClick={() => setStep(step - 1)}><ArrowLeft size={16} /> Back</button> : <span />}
              {step < 4
                ? <button type="button" className="btn btn-ink" disabled={!canNext} onClick={() => setStep(step + 1)}>Continue <ArrowRight size={16} /></button>
                : <button type="button" className="btn btn-ink" onClick={confirm}>{rescheduling ? "Confirm new time" : "Confirm booking"} <Check size={16} /></button>}
            </div>
          )}
        </div>
      </section>
    </SiteLayout>
  );
}

function Summary({ service, date, time, details }: { service: (typeof allServices)[number]; date: string; time: string; details: Details }) {
  return (
    <dl className="summary">
      <div><dt>Service</dt><dd>{service.name} <small>{service.category}</small></dd></div>
      <div><dt>Date</dt><dd>{fmt(date)}</dd></div>
      <div><dt>Time</dt><dd>{time}</dd></div>
      <div><dt>Name</dt><dd>{details.name}</dd></div>
      <div><dt>Phone</dt><dd>{details.phone}</dd></div>
      {details.email && <div><dt>Email</dt><dd>{details.email}</dd></div>}
      <div><dt>Price</dt><dd>{PRICE_PLACEHOLDER}</dd></div>
    </dl>
  );
}
