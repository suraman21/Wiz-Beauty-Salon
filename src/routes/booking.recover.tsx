import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { ArrowLeft, ArrowRight, Check, Copy, ExternalLink, Phone, Search, ShieldCheck } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/lib/api";
import { recoverBookingsByPhone, type RecoverBooking } from "@/lib/booking-api";

export const Route = createFileRoute("/booking/recover")({
  head: () => ({
    meta: [
      { title: "Recover Booking | WIZ Beauty Salon" },
      { name: "description", content: "Find your WIZ booking with your phone number and recover your manage link." },
      { property: "og:title", content: "Recover Booking | WIZ Beauty Salon" },
      { property: "og:type", content: "website" },
    ],
  }),
  component: RecoverBookingPage,
});

function RecoverBookingPage() {
  const [phone, setPhone] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [results, setResults] = useState<RecoverBooking[] | null>(null);
  const [copied, setCopied] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!phone.trim()) return;
    setBusy(true);
    setError("");
    setResults(null);
    try {
      const res = await recoverBookingsByPhone(phone.trim());
      setResults(res.bookings ?? []);
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : "Could not look up bookings. Please try again.";
      setError(msg);
    } finally {
      setBusy(false);
    }
  };

  const copy = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(url);
      setTimeout(() => setCopied(null), 2000);
    } catch {
      window.prompt("Copy this link:", url);
    }
  };

  const fmtDate = (iso: string) => {
    try {
      const d = new Date(`${iso}T00:00:00`);
      return d.toLocaleDateString("en-GB", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
    } catch {
      return iso;
    }
  };

  return (
    <SiteLayout>
      <section className="booking-page">
        <aside className="booking-visual">
          <div className="booking-visual-frame">
            <img
              src="/assets/hero-portrait.jpg"
              alt="WIZ Beauty Salon"
              style={{ objectFit: "cover", width: "100%", height: "100%" }}
            />
          </div>
          <div className="booking-visual-copy">
            <span className="overline">Recover</span>
            <p>
              Your
              <br /> <em>bookings.</em>
            </p>
            <span className="booking-visual-note">Phone recovery · No password needed</span>
          </div>
        </aside>

        <div className="booking-panel">
          <span className="overline">Recover booking</span>
          <h1>
            Lost your
            <br />
            <em>link?</em>
          </h1>

          <p className="step-intro">
            Enter the phone number you booked with. We’ll find your upcoming appointments and
            give you fresh manage links — valid for 24 hours.
          </p>

          <form onSubmit={submit} className="wiz-form" style={{ marginTop: "1.25rem" }}>
            <label className="full">
              Phone number
              <div style={{ display: "flex", gap: "0.5rem" }}>
                <input
                  type="tel"
                  inputMode="tel"
                  autoComplete="tel"
                  placeholder="09… or +251…"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  required
                  maxLength={40}
                  style={{ flex: 1 }}
                />
                <Button
                  type="submit"
                  variant="ghost"
                  className="btn btn-ink h-auto rounded-none"
                  disabled={busy || !phone.trim()}
                >
                  {busy ? "Checking…" : <><Search size={16} /> Find</>}
                </Button>
              </div>
            </label>

            {/* honeypot - hidden from users, bots fill it */}
            <input type="text" name="website" tabIndex={-1} autoComplete="off" style={{ display: "none" }} aria-hidden />

            <span className="field-hint full" style={{ marginTop: "-0.25rem" }}>
              We’ll only look for <em>upcoming</em> bookings — requested or confirmed, next 60
              days.
            </span>

            {error !== "" && (
              <p className="step-note full" role="alert" style={{ color: "#a33" }}>
                {error}
              </p>
            )}
          </form>

          {results !== null && (
            <div style={{ marginTop: "1.5rem" }}>
              {results.length === 0 ? (
                <div className="card" style={{ padding: "1.15rem" }}>
                  <p style={{ fontWeight: 600, marginBottom: "0.35rem" }}>No upcoming bookings found</p>
                  <p className="field-hint" style={{ lineHeight: "1.5" }}>
                    Check the number you typed — use the same one you gave when booking (09… or
                    +251…). If you still don’t see it, it may have been cancelled or already
                    completed. Call the salon with your reference and we’ll help.
                  </p>
                  <div style={{ marginTop: "0.9rem" }}>
                    <Link to="/contact" className="editorial-link">
                      Contact salon <ArrowRight size={14} />
                    </Link>
                  </div>
                </div>
              ) : (
                <>
                  <p className="step-service-hint">
                    Found <strong>{results.length}</strong> upcoming booking
                    {results.length > 1 ? "s" : ""} for this number
                  </p>

                  <div style={{ display: "flex", flexDirection: "column", gap: "0.75rem", marginTop: "0.75rem" }}>
                    {results.map((b) => (
                      <div
                        key={b.reference}
                        className="card"
                        style={{ padding: "1rem 1.1rem", display: "flex", flexDirection: "column", gap: "0.65rem" }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", gap: "0.75rem", alignItems: "flex-start" }}>
                          <div>
                            <p style={{ fontSize: "11px", letterSpacing: "0.08em", textTransform: "uppercase", color: "var(--muted-foreground)", marginBottom: "0.2rem" }}>
                              {b.reference} · <span style={{ textTransform: "none", letterSpacing: 0 }}>{b.status}</span>
                            </p>
                            <p style={{ fontWeight: 700, fontSize: "15px", color: "var(--ink)" }}>{b.service_name}</p>
                            <p style={{ fontSize: "13px", color: "var(--muted-foreground)", marginTop: "0.15rem" }}>
                              {fmtDate(b.booking_date)} · {b.start_time}–{b.end_time}
                            </p>
                          </div>
                          <span
                            className={
                              b.status === "confirmed"
                                ? "badge badge--green"
                                : b.status === "requested"
                                  ? "badge badge--amber"
                                  : "badge badge--grey"
                            }
                            style={{ flex: "none" }}
                          >
                            {b.status}
                          </span>
                        </div>

                        {b.manage_url ? (
                          <div style={{ display: "flex", gap: "0.5rem", flexWrap: "wrap" }}>
                            <a href={b.manage_url} className="btn btn-ink" style={{ flex: "1 1 auto", textAlign: "center" }}>
                              <ExternalLink size={14} /> Manage booking
                            </a>
                            <Button
                              type="button"
                              variant="ghost"
                              className="btn btn-outline h-auto rounded-md"
                              onClick={() => copy(b.manage_url!)}
                              style={{ flex: "0 0 auto" }}
                            >
                              {copied === b.manage_url ? <Check size={14} /> : <Copy size={14} />}{" "}
                              {copied === b.manage_url ? "Copied" : "Copy link"}
                            </Button>
                          </div>
                        ) : (
                          <p className="field-hint">Manage link unavailable — call the salon.</p>
                        )}
                      </div>
                    ))}
                  </div>

                  <div className="confirm-banner" style={{ marginTop: "1.25rem" }}>
                    <ShieldCheck size={18} />
                    <div>
                      <strong>Links are signed & valid 24 hours</strong>
                      <span>Don’t share them — anyone with the link can reschedule or cancel.</span>
                    </div>
                  </div>
                </>
              )}
            </div>
          )}

          <div style={{ marginTop: "1.75rem", display: "flex", gap: "1rem", flexWrap: "wrap", alignItems: "center" }}>
            <Link to="/booking" className="btn btn-outline h-auto rounded-none">
              <ArrowLeft size={16} /> Back to booking
            </Link>
            <Link to="/contact" className="editorial-link">
              Need help? Contact salon <ArrowRight size={14} />
            </Link>
          </div>

          <div className="recover-card" style={{ marginTop: "1.5rem" }}>
            <h4 style={{ display: "flex", alignItems: "center", gap: "0.4rem" }}>
              <Phone size={14} /> Privacy
            </h4>
            <p style={{ fontSize: "13px", lineHeight: "1.5", color: "var(--muted-foreground)" }}>
              Only upcoming, not-yet-finished bookings are shown. We don’t show past or cancelled
              history. If you don’t see yours, check the number format (09… vs +251…) or ask the
              salon to confirm with your reference.
            </p>
          </div>
        </div>
      </section>
    </SiteLayout>
  );
}
