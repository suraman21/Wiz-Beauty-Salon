import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, ArrowRight, Check, HelpCircle, Sparkles } from "lucide-react";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteChrome";
import { categories, demoTimes, findService, images, type CategoryId } from "@/data/wiz";

export const Route = createFileRoute("/booking")({
  validateSearch: (s) => z.object({ service: z.string().optional(), option: z.string().optional() }).parse(s),
  head: () => ({
    meta: [
      { title: "Book an Appointment | WIZ Beauty Salon" },
      { name: "description", content: "Request a WIZ Beauty Salon appointment — choose your service, or let the team help you decide." },
      { property: "og:title", content: "Book an Appointment | WIZ Beauty Salon" },
      { property: "og:description", content: "Pick a service and preferred time, or ask WIZ to help you choose." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Booking,
});

const STORE = "wiz-booking-selection";
const steps = ["Service", "Date", "Time", "Details", "Summary"];
type Mode = "known" | "unsure" | null;
type Details = { name: string; phone: string; email: string; notes: string; forChild: boolean };
/** Shape of a booking request. An undecided request has serviceId null + needsConsultation true. */
type BookingRequest = {
  ref: string;
  serviceId: string | null;
  optionId: string | null;
  needsConsultation: boolean;
  preferredDate: string;
  preferredTime: string;
  status: "pending";
  details: Details;
};

function nextDays(n: number) {
  const out: Date[] = [];
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  for (let i = 1; out.length < n; i++) { const x = new Date(d); x.setDate(d.getDate() + i); out.push(x); }
  return out;
}
const fmt = (iso: string) => new Date(iso).toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long" });
const emptyDetails: Details = { name: "", phone: "", email: "", notes: "", forChild: false };

function Booking() {
  const search = Route.useSearch();
  const [mode, setMode] = useState<Mode>(null);
  const [catId, setCatId] = useState<CategoryId | "">("");
  const [serviceId, setServiceId] = useState("");
  const [optionId, setOptionId] = useState("");
  const [step, setStep] = useState(0);
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [details, setDetails] = useState<Details>(emptyDetails);
  const [request, setRequest] = useState<BookingRequest | null>(null);
  const days = useMemo(() => nextDays(14), []);

  const applySelection = (sid?: string, oid?: string) => {
    const s = findService(sid);
    if (!s) return false;
    setMode("known"); setCatId(s.categoryId); setServiceId(s.id);
    setOptionId(s.options?.some((o) => o.id === oid) ? oid! : "");
    return true;
  };

  // Preselect from the link (Services page) or from the last selection this session.
  useEffect(() => {
    if (applySelection(search.service, search.option)) return;
    try {
      const saved = JSON.parse(sessionStorage.getItem(STORE) ?? "null");
      if (saved?.mode === "unsure") setMode("unsure");
      else if (saved) applySelection(saved.serviceId, saved.optionId);
    } catch { /* ignore */ }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search.service, search.option]);

  useEffect(() => {
    if (!mode) return;
    sessionStorage.setItem(STORE, JSON.stringify({ mode, serviceId, optionId }));
  }, [mode, serviceId, optionId]);

  const service = findService(serviceId);
  const option = service?.options?.find((o) => o.id === optionId);
  const category = categories.find((c) => c.id === catId);
  const unsure = mode === "unsure";
  const showChild = unsure || service?.categoryId === "kids-braids";

  const serviceReady = unsure || (mode === "known" && !!service && (!service.options || !!option));
  const canNext = [serviceReady, !!date, !!time, !!(details.name.trim() && details.phone.trim()), true][step];

  const submit = () => {
    setRequest({
      ref: `WIZ-${Math.random().toString(36).slice(2, 7).toUpperCase()}`,
      serviceId: unsure ? null : serviceId,
      optionId: unsure ? null : optionId || null,
      needsConsultation: unsure,
      preferredDate: date,
      preferredTime: time,
      status: "pending",
      details: { ...details, forChild: showChild && details.forChild },
    });
    sessionStorage.removeItem(STORE);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const restart = () => {
    setRequest(null); setMode(null); setCatId(""); setServiceId(""); setOptionId("");
    setDate(""); setTime(""); setDetails(emptyDetails); setStep(0);
  };

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <img src={images.portrait} alt="Demo editorial beauty portrait" />
          <div className="booking-visual-copy"><span className="overline">Book your moment</span><p>Beauty,<br /><em>your way.</em></p></div>
        </aside>

        <div className="booking-panel">
          <span className="overline">Booking request</span>
          <h1>{request ? <>Request<br /><em>received.</em></> : <>Book an<br /><em>appointment.</em></>}</h1>

          {!request && (
            <ol className="stepper" aria-label="Booking progress">
              {steps.map((s, i) => (
                <li key={s} className={i === step ? "is-current" : i < step ? "is-done" : ""}>
                  <button type="button" disabled={i > step} onClick={() => setStep(i)}><span>{i < step ? <Check size={12} /> : i + 1}</span>{s}</button>
                </li>
              ))}
            </ol>
          )}

          <div className="step-body" key={request ? "done" : step}>
            {!request && step === 0 && (
              <>
                <p className="decide-q">Have you decided which service you'd like?</p>
                <div className="decide" role="radiogroup" aria-label="Have you decided which service you'd like?">
                  <button type="button" role="radio" aria-checked={mode === "known"} className={`decide-card ${mode === "known" ? "is-selected" : ""}`} onClick={() => setMode("known")}>
                    <Sparkles size={20} strokeWidth={1.4} /><strong>I know what I want</strong><span>Choose a category and service.</span>
                  </button>
                  <button type="button" role="radio" aria-checked={unsure} className={`decide-card ${unsure ? "is-selected" : ""}`} onClick={() => setMode("unsure")}>
                    <HelpCircle size={20} strokeWidth={1.4} /><strong>I'm not sure yet</strong><span>Help me choose — WIZ will contact you.</span>
                  </button>
                </div>

                {mode === "known" && (
                  <div className="pick">
                    <label className="pick-label">Category
                      <div className="pick-chips">
                        {categories.map((c) => (
                          <button type="button" key={c.id} className={`chip ${catId === c.id ? "is-active" : ""}`} aria-pressed={catId === c.id} onClick={() => { setCatId(c.id); setServiceId(""); setOptionId(""); }}>{c.short}</button>
                        ))}
                      </div>
                    </label>
                    {category && (
                      <fieldset className="svc-group">
                        <legend>{category.name}</legend>
                        {category.items.map((i) => (
                          <label key={i.id} className={`option ${serviceId === i.id ? "is-selected" : ""}`}>
                            <input type="radio" name="service" checked={serviceId === i.id} onChange={() => { setServiceId(i.id); setOptionId(""); }} />
                            <span className="opt-name">{i.name}</span>{i.group && <span className="opt-meta">{i.group}</span>}
                          </label>
                        ))}
                      </fieldset>
                    )}
                    {service?.options && (
                      <fieldset className="svc-group">
                        <legend>{service.name} — option</legend>
                        <div className="pick-chips">
                          {service.options.map((o) => (
                            <button type="button" key={o.id} className={`chip ${optionId === o.id ? "is-active" : ""}`} aria-pressed={optionId === o.id} onClick={() => setOptionId(o.id)}>{o.name}</button>
                          ))}
                        </div>
                      </fieldset>
                    )}
                    {service?.confirm && <span className="demo-note">{service.confirm}.</span>}
                  </div>
                )}
                {unsure && <p className="step-hint unsure-hint">No problem. Pick a preferred date and time, and the WIZ team will contact you to find the right service and confirm how long your appointment needs.</p>}
              </>
            )}

            {!request && step === 1 && (
              <>
                <p className="step-hint">Choose a preferred date.</p>
                <div className="date-grid" role="radiogroup" aria-label="Choose a preferred date">
                  {days.map((d) => {
                    const iso = d.toISOString();
                    return (
                      <button type="button" role="radio" aria-checked={date === iso} key={iso} className={`date-cell ${date === iso ? "is-selected" : ""}`} onClick={() => { setDate(iso); setTime(""); }}>
                        <span>{d.toLocaleDateString("en-GB", { weekday: "short" })}</span><strong>{d.getDate()}</strong><span>{d.toLocaleDateString("en-GB", { month: "short" })}</span>
                      </button>
                    );
                  })}
                </div>
              </>
            )}

            {!request && step === 2 && (
              <>
                <p className="step-hint">{fmt(date)} — choose a preferred time.</p>
                <div className="time-grid" role="radiogroup" aria-label="Choose a preferred time">
                  {demoTimes.map((t) => <button type="button" role="radio" aria-checked={time === t} key={t} className={`chip ${time === t ? "is-active" : ""}`} onClick={() => setTime(t)}>{t}</button>)}
                </div>
                <span className="demo-note">Preferred time only — the salon confirms the final time and duration with you.</span>
              </>
            )}

            {!request && step === 3 && (
              <div className="wiz-form">
                <label>Full name *<input value={details.name} onChange={(e) => setDetails({ ...details, name: e.target.value })} autoComplete="name" /></label>
                <label>Phone *<input type="tel" value={details.phone} onChange={(e) => setDetails({ ...details, phone: e.target.value })} autoComplete="tel" /></label>
                <label className="full">Email (optional)<input type="email" value={details.email} onChange={(e) => setDetails({ ...details, email: e.target.value })} autoComplete="email" /></label>
                {showChild && (
                  <label className="full check"><input type="checkbox" checked={details.forChild} onChange={(e) => setDetails({ ...details, forChild: e.target.checked })} /> This appointment is for a child</label>
                )}
                <label className="full">Notes<textarea rows={3} value={details.notes} onChange={(e) => setDetails({ ...details, notes: e.target.value })} placeholder={unsure ? "Tell us what you have in mind" : "Anything we should know?"} /></label>
              </div>
            )}

            {!request && step === 4 && <Summary unsure={unsure} serviceId={serviceId} optionId={optionId} date={date} time={time} details={{ ...details, forChild: showChild && details.forChild }} />}

            {request && (
              <div className="confirmation" role="status">
                {request.needsConsultation
                  ? <p className="conf-lead">Your request has been received! The WIZ Beauty Salon team will contact you to help choose the right service and arrange your appointment.</p>
                  : <p className="conf-lead">Your request has been received. The WIZ team will call you on your phone number to confirm your appointment.</p>}
                <p className="conf-ref">Reference <strong>{request.ref}</strong> · Status <strong>Awaiting confirmation</strong></p>
                <Summary unsure={request.needsConsultation} serviceId={request.serviceId ?? ""} optionId={request.optionId ?? ""} date={request.preferredDate} time={request.preferredTime} details={request.details} />
                <span className="demo-note">Online booking isn't connected to the salon yet — this request was not sent. It will be once WIZ's booking system is set up.</span>
                <div className="manage-actions">
                  <button type="button" className="btn btn-ink" onClick={restart}>Make another request</button>
                  <Link to="/" className="editorial-link">Back home <ArrowRight size={16} /></Link>
                </div>
              </div>
            )}
          </div>

          {!request && (
            <div className="step-nav">
              {step > 0 ? <button type="button" className="btn btn-outline" onClick={() => setStep(step - 1)}><ArrowLeft size={16} /> Back</button> : <span />}
              {step < 4
                ? <button type="button" className="btn btn-ink" disabled={!canNext} onClick={() => setStep(step + 1)}>Continue <ArrowRight size={16} /></button>
                : <button type="button" className="btn btn-ink" onClick={submit}>Send request <Check size={16} /></button>}
            </div>
          )}
        </div>
      </section>
    </SiteLayout>
  );
}

function Summary({ unsure, serviceId, optionId, date, time, details }: { unsure: boolean; serviceId: string; optionId: string; date: string; time: string; details: Details }) {
  const s = findService(serviceId);
  const o = s?.options?.find((x) => x.id === optionId);
  return (
    <dl className="summary">
      <div><dt>Service</dt><dd>{unsure || !s ? <>Help me choose <small>Consultation needed</small></> : <>{s.name}{o && ` — ${o.name}`} <small>{s.category}</small></>}</dd></div>
      <div><dt>Preferred date</dt><dd>{fmt(date)}</dd></div>
      <div><dt>Preferred time</dt><dd>{time}</dd></div>
      <div><dt>Name</dt><dd>{details.name}</dd></div>
      <div><dt>Phone</dt><dd>{details.phone}</dd></div>
      {details.email && <div><dt>Email</dt><dd>{details.email}</dd></div>}
      {details.forChild && <div><dt>For</dt><dd>A child</dd></div>}
      {details.notes && <div><dt>Notes</dt><dd>{details.notes}</dd></div>}
    </dl>
  );
}
