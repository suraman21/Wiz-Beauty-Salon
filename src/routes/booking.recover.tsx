import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { ArrowLeft, ArrowRight, Check, Copy, ExternalLink, Mail, Phone, Search, ShieldCheck } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/lib/api";
import { recoverBookings, type RecoverBooking } from "@/lib/booking-api";

export const Route = createFileRoute("/booking/recover")({
  head: () => ({
    meta: [
      { title: "Manage Booking | WIZ Beauty Salon" },
      { name: "description", content: "Find your WIZ Beauty Salon bookings with your phone number or email and manage them." },
      { property: "og:title", content: "Manage Booking | WIZ Beauty Salon" },
      { property: "og:type", content: "website" },
    ],
  }),
  component: ManageBookingPage,
});

function ManageBookingPage() {
  const [identifier, setIdentifier] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [results, setResults] = useState<RecoverBooking[] | null>(null);
  const [copied, setCopied] = useState<string | null>(null);

  const isEmail = identifier.trim().includes("@");
  const hint = isEmail ? "Email address detected" : identifier.trim() ? "Phone number detected" : "";

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!identifier.trim()) return;
    setBusy(true);
    setError("");
    setResults(null);
    try {
      const res = await recoverBookings(identifier.trim());
      setResults(res.bookings ?? []);
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : "We could not look up your bookings. Please check your connection and try again.";
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
      <section className="booking-page manage-page">
        <aside className="booking-visual">
          <div className="booking-visual-frame">
            <img
              src="/assets/hero-portrait.jpg"
              alt="WIZ Beauty Salon"
              style={{ objectFit: "cover", width: "100%", height: "100%" }}
            />
          </div>
          <div className="booking-visual-copy">
            <span className="overline">Manage booking</span>
            <p>
              Manage
              <br /> <em>your visit.</em>
            </p>
            <span className="booking-visual-note">Find with phone or email · No password needed</span>
          </div>
        </aside>

        <div className="booking-panel">
          <span className="overline">Manage booking</span>
          <h1>
            Find your
            <br />
            <em>booking.</em>
          </h1>

          <p className="step-intro">
            Find your booking through your phone number or email. Enter the same contact you used when booking —
            we’ll show your upcoming appointments and give you fresh manage links, valid for 24 hours.
          </p>

          <form onSubmit={submit} className="wiz-form" style={{ marginTop: "1.35rem" }}>
            <label className="full">
              Phone number or email
              <div style={{ display: "flex", gap: "0.5rem", alignItems: "stretch" }}>
                <div style={{ position: "relative", flex: 1, display: "flex", alignItems: "center" }}>
                  <span
                    style={{
                      position: "absolute",
                      left: "0.75rem",
                      color: "var(--muted-foreground)",
                      display: "grid",
                      placeItems: "center",
                      pointerEvents: "none",
                    }}
                  >
                    {isEmail ? <Mail size={16} /> : <Phone size={16} />}
                  </span>
                  <input
                    type="text"
                    inputMode={isEmail ? "email" : "tel"}
                    autoComplete={isEmail ? "email" : "tel"}
                    placeholder="0912345678 or you@email.com"
                    value={identifier}
                    onChange={(e) => setIdentifier(e.target.value)}
                    required
                    maxLength={190}
                    style={{ flex: 1, paddingLeft: "2.4rem" }}
                    aria-label="Phone number or email address"
                  />
                </div>
                <Button
                  type="submit"
                  variant="ghost"
                  className="btn btn-ink h-auto rounded-none"
                  disabled={busy || !identifier.trim()}
                >
                  {busy ? "Searching…" : <><Search size={16} /> Find</>}
                </Button>
              </div>
            </label>

            {/* honeypot - hidden from users, bots fill it */}
            <input type="text" name="website" tabIndex={-1} autoComplete="off" style={{ display: "none" }} aria-hidden="true" />

            <div className="full" style={{ display: "flex", justifyContent: "space-between", gap: "0.75rem", alignItems: "center", marginTop: "-0.4rem", flexWrap: "wrap" }}>
              <span className="field-hint" style={{ display: "inline-flex", alignItems: "center", gap: "0.4rem" }}>
                {hint ? (
                  <>
                    {isEmail ? <Mail size={12} /> : <Phone size={12} />} {hint}
                  </>
                ) : (
                  <>Use 09… / +251… or your email — whichever you booked with</>
                )}
              </span>
              <span className="field-hint" style={{ fontSize: "11px" }}>
                Upcoming only — next 60 days
              </span>
            </div>

            {error !== "" && (
              <p className="step-note full" role="alert" style={{ color: "#a33", background: "#fff1f1", padding: "0.65rem 0.85rem", borderRadius: "8px", border: "1px solid #fee" }}>
                {error}
              </p>
            )}
          </form>

          {results !== null && (
            <div style={{ marginTop: "1.6rem" }} role="status" aria-live="polite">
              {results.length === 0 ? (
                <div className="card" style={{ padding: "1.25rem", border: "1px solid var(--border)", borderRadius: "12px" }}>
                  <p style={{ fontWeight: 700, marginBottom: "0.4rem", fontSize: "15px" }}>No upcoming bookings found</p>
                  <p className="field-hint" style={{ lineHeight: "1.6", fontSize: "13px" }}>
                    We checked upcoming requested and confirmed bookings for{" "}
                    <strong style={{ color: "var(--ink)" }}>{identifier}</strong>. Check you typed it the same way as when booking
                    (09… vs +251… or email spelling). If you still don’t see it, it may be past, cancelled, or booked under a different contact.
                    Call the salon with your reference and we’ll help.
                  </p>
                  <div style={{ marginTop: "1rem", display: "flex", gap: "0.75rem", flexWrap: "wrap" }}>
                    <button
                      type="button"
                      className="btn btn-outline h-auto rounded-none"
                      style={{ fontSize: "12px", padding: "0.7rem 1rem" }}
                      onClick={() => {
                        setResults(null);
                        setIdentifier("");
                        setError("");
                      }}
                    >
                      Try another
                    </button>
                    <Link to="/contact" className="editorial-link">
                      Contact salon <ArrowRight size={14} />
                    </Link>
                  </div>
                </div>
              ) : (
                <>
                  <p className="step-service-hint">
                    Found <strong>{results.length}</strong> upcoming booking
                    {results.length > 1 ? "s" : ""} — tap <em>Manage</em> to reschedule or cancel
                  </p>

                  <div style={{ display: "flex", flexDirection: "column", gap: "0.85rem", marginTop: "0.85rem" }}>
                    {results.map((b) => (
                      <div
                        key={b.reference}
                        className="card"
                        style={{ padding: "1.15rem 1.15rem", display: "flex", flexDirection: "column", gap: "0.75rem", borderRadius: "12px", border: "1px solid var(--border)", background: "white", boxShadow: "0 4px 16px oklch(0.19 0.012 330 / 0.06)" }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", gap: "0.85rem", alignItems: "flex-start" }}>
                          <div style={{ minWidth: 0 }}>
                            <p style={{ fontSize: "11px", letterSpacing: "0.07em", textTransform: "uppercase", color: "var(--muted-foreground)", marginBottom: "0.25rem", fontWeight: 600 }}>
                              {b.reference} · <span style={{ textTransform: "none", letterSpacing: 0 }}>{b.status}</span>
                            </p>
                            <p style={{ fontWeight: 700, fontSize: "15.5px", color: "var(--ink)", lineHeight: "1.3" }}>{b.service_name}</p>
                            <p style={{ fontSize: "13px", color: "var(--muted-foreground)", marginTop: "0.3rem", display: "flex", gap: "0.4rem", alignItems: "center", flexWrap: "wrap" }}>
                              <span>{fmtDate(b.booking_date)}</span> <span aria-hidden>·</span> <span>{b.start_time}–{b.end_time}</span>
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
                            style={{ flex: "none", borderRadius: "999px", padding: "0.25rem 0.65rem", fontSize: "11px", fontWeight: 700 }}
                          >
                            {b.status}
                          </span>
                        </div>

                        {b.manage_url ? (
                          <div style={{ display: "flex", gap: "0.5rem", flexWrap: "wrap" }}>
                            <a href={b.manage_url} className="btn btn-ink" style={{ flex: "1 1 160px", textAlign: "center", justifyContent: "center" }}>
                              <ExternalLink size={14} /> Manage booking
                            </a>
                            <Button
                              type="button"
                              variant="ghost"
                              className="btn btn-outline h-auto rounded-md"
                              onClick={() => copy(b.manage_url!)}
                              style={{ flex: "0 0 auto", minWidth: "110px" }}
                            >
                              {copied === b.manage_url ? <Check size={14} /> : <Copy size={14} />}{" "}
                              {copied === b.manage_url ? "Copied" : "Copy link"}
                            </Button>
                          </div>
                        ) : (
                          <p className="field-hint">Manage link unavailable — please call the salon.</p>
                        )}
                      </div>
                    ))}
                  </div>

                  <div className="confirm-banner" style={{ marginTop: "1.35rem" }}>
                    <ShieldCheck size={18} />
                    <div>
                      <strong>Links are signed & valid 24 hours</strong>
                      <span>Don’t share them — anyone with the link can reschedule or cancel. After 24h, find your booking again here.</span>
                    </div>
                  </div>
                </>
              )}
            </div>
          )}

          <div style={{ marginTop: "1.85rem", display: "flex", gap: "1rem", flexWrap: "wrap", alignItems: "center" }}>
            <Link to="/booking" className="btn btn-outline h-auto rounded-none">
              <ArrowLeft size={16} /> Book an appointment
            </Link>
            <Link to="/contact" className="editorial-link">
              Need help? Contact salon <ArrowRight size={14} />
            </Link>
          </div>

          <div className="recover-card" style={{ marginTop: "1.35rem" }}>
            <h4 style={{ display: "flex", alignItems: "center", gap: "0.45rem" }}>
              <ShieldCheck size={14} /> Privacy
            </h4>
            <p style={{ fontSize: "13px", lineHeight: "1.6", color: "var(--muted-foreground)" }}>
              Only upcoming, not-yet-finished bookings are shown. We don’t show past or cancelled history. If you don’t see yours, check the number/email or ask the salon to confirm with your reference.
            </p>
          </div>
        </div>
      </section>
    </SiteLayout>
  );
}
