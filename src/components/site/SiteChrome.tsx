import { Link } from "@tanstack/react-router";
import { useEffect, useState, type ReactNode } from "react";
import { ArrowRight, Menu, X } from "lucide-react";
import logo from "@/assets/wiz-logo.png";
import { nav } from "@/data/wiz";
import { useSalonInfo } from "@/lib/site-content";
import { hasDetails, whatsappHref } from "@/lib/salon-api";

/**
 * Public chrome shared by every marketing page.
 *
 * Design notes live in DESIGN-PLAN.md. Two rules worth repeating here because
 * they are easy to undo by accident:
 *   1. No all-caps tracked label above a heading. Labels are sentence case and
 *      say something true ("12 options", "From ETB 450").
 *   2. No italic or colour switch inside a headline to fake emphasis. Scale and
 *      position carry emphasis instead.
 */

export function SiteHeader() {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "";

    return () => {
      document.body.style.overflow = "";
    };
  }, [open]);

  return (
    <header className="wz-header">
      <div className="wz-wrap wz-header-inner">
        <Link to="/" aria-label="WIZ Beauty Salon, home" className="wz-brand">
          <img className="wz-brand-logo" src={logo} alt="" width={38} height={38} />
          <span>
            <span className="wz-brand-word">WIZ</span>
            <span className="wz-brand-eth" lang="am">
              ዊዝ ቢዩቲ ሳሎን
            </span>
          </span>
        </Link>

        <nav className="wz-nav" aria-label="Main">
          {nav.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              activeOptions={{ exact: true }}
              activeProps={{ className: "is-active" }}
            >
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="wz-header-actions">
          <Link to="/booking/recover" className="wz-manage">
            Manage a booking
          </Link>
          <Link to="/booking" className="wz-btn" onClick={() => setOpen(false)}>
            Book an appointment
          </Link>
          <button
            type="button"
            className="wz-menu-btn"
            aria-label={open ? "Close menu" : "Open menu"}
            aria-expanded={open}
            onClick={() => setOpen(!open)}
          >
            {open ? <X size={19} /> : <Menu size={19} />}
          </button>
        </div>
      </div>

      {open && (
        <nav className="wz-drawer" aria-label="Mobile">
          {nav.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              onClick={() => setOpen(false)}
              activeOptions={{ exact: true }}
              activeProps={{ className: "is-active" }}
            >
              {item.label}
            </Link>
          ))}
          <Link to="/booking/recover" onClick={() => setOpen(false)}>
            Manage a booking
          </Link>
          <Link to="/booking" onClick={() => setOpen(false)} className="wz-btn">
            Book an appointment
          </Link>
        </nav>
      )}
    </header>
  );
}

export function SiteFooter() {
  return (
    <footer className="wz-footer">
      <div className="wz-wrap">
        <div className="wz-footer-top">
          <div>
            <span className="wz-brand-word" style={{ color: "var(--wz-chalk)" }}>
              WIZ
            </span>
            <p className="wz-footer-line">Beauty, your way.</p>
            <p className="wz-footer-eth" lang="am">
              ውበት፣ በእርስዎ መንገድ።
            </p>
          </div>
          <div className="wz-footer-cols">
            <div className="wz-footer-col">
              <h3>Pages</h3>
              {nav.map((item) => (
                <Link key={item.to} to={item.to}>
                  {item.label}
                </Link>
              ))}
              <Link to="/booking">Booking</Link>
              <Link to="/booking/recover">Manage a booking</Link>
            </div>
            <FooterContact />
          </div>
        </div>
        <div className="wz-footer-bottom">
          <span>© {new Date().getFullYear()} WIZ Beauty Salon, Addis Ababa</span>
          <span lang="am" className="wz-eth">
            አዲስ አበባ
          </span>
        </div>
      </div>
    </footer>
  );
}

/**
 * Prints what the salon actually published in Settings, and nothing it has not.
 * An empty column of half-filled links reads as broken, so it is left out.
 */
function FooterContact() {
  const salon = useSalonInfo();
  const details = salon?.contact;

  if (details === undefined || !hasDetails(details)) {
    return (
      <div className="wz-footer-col">
        <h3>Visit</h3>
        <span>Addis Ababa</span>
        <Link to="/contact">Contact and directions</Link>
      </div>
    );
  }

  const socials = [
    details.instagram === ""
      ? null
      : { label: "Instagram", href: `https://instagram.com/${details.instagram}` },
    details.tiktok === ""
      ? null
      : { label: "TikTok", href: `https://tiktok.com/@${details.tiktok}` },
  ].filter((item): item is { label: string; href: string } => item !== null);

  return (
    <div className="wz-footer-col">
      <h3>Visit</h3>
      {details.address !== "" && <span>{details.address}</span>}
      {details.phone !== "" && (
        <a href={`tel:${details.phone.replace(/[^\d+]/g, "")}`}>{details.phone}</a>
      )}
      {details.whatsapp !== "" && <a href={whatsappHref(details.whatsapp)}>WhatsApp</a>}
      {details.email !== "" && <a href={`mailto:${details.email}`}>{details.email}</a>}
      {socials.map((item) => (
        <a key={item.label} href={item.href}>
          {item.label}
        </a>
      ))}
    </div>
  );
}

export function SiteLayout({ children }: { children: ReactNode }) {
  return (
    <div className="wz-site">
      <SiteHeader />
      <main>{children}</main>
      <SiteFooter />
    </div>
  );
}

/** The home hero: type on plum at the left, one photograph full-bleed at the right. */
export function SiteHero({
  title,
  amharic,
  lede,
  image,
  alt,
}: {
  title: ReactNode;
  amharic: string;
  lede: string;
  image: string;
  alt: string;
}) {
  return (
    <section className="wz-hero">
      <div className="wz-hero-copy">
        <h1 className="wz-hero-title wz-rise">{title}</h1>
        <p className="wz-hero-eth wz-rise-2" lang="am">
          {amharic}
        </p>
        <p className="wz-hero-lede wz-rise-2">{lede}</p>
        <div className="wz-hero-actions wz-rise-3">
          <Link to="/booking" className="wz-btn wz-btn-onDark">
            Book an appointment <ArrowRight size={16} aria-hidden="true" />
          </Link>
          <Link to="/services" className="wz-link wz-link-light">
            See the full catalogue
          </Link>
        </div>
      </div>
      <div className="wz-hero-media">
        <img src={image} alt={alt} fetchPriority="high" width={1536} height={1024} />
      </div>
    </section>
  );
}

/**
 * Interior page head. Deliberately a different composition from the home hero:
 * type left, standfirst right, then a full-bleed photograph underneath.
 */
export function PageHead({
  title,
  amharic,
  intro,
  children,
}: {
  title: ReactNode;
  amharic: string;
  intro: string;
  children?: ReactNode;
}) {
  return (
    <div className="wz-wrap wz-page-head">
      <div className="wz-page-head-top">
        <div>
          <p className="wz-eth-mark" lang="am">
            {amharic}
          </p>
          <h1 className="wz-rise">{title}</h1>
        </div>
        <div>
          <p className="wz-lede">{intro}</p>
          {children}
        </div>
      </div>
    </div>
  );
}

/** A full-bleed photograph. Never tinted, never overlaid — the image does the work. */
export function ImageBand({ image, alt }: { image: string; alt: string }) {
  return (
    <figure className="wz-band">
      <img src={image} alt={alt} loading="lazy" />
    </figure>
  );
}

export function ClosingCta({
  title = (
    <>
      Tell us what you have in mind.
      <br />
      We will find the time.
    </>
  ),
  copy = "Most appointments are booked a week ahead. If you are not sure what to ask for, book a consultation and we will talk it through first.",
}: {
  title?: ReactNode;
  copy?: string;
}) {
  return (
    <section className="wz-cta">
      <div className="wz-wrap wz-cta-inner">
        <div>
          <h2>{title}</h2>
          <Link to="/booking" className="wz-btn">
            Book an appointment <ArrowRight size={16} aria-hidden="true" />
          </Link>
        </div>
        <div>
          <p className="wz-prose">{copy}</p>
          <p className="wz-meta wz-mt-sm">
            Booking from a phone works best — the whole flow is one thumb.
          </p>
        </div>
      </div>
    </section>
  );
}

/**
 * Before / after slider. Both photographs genuinely differ, so the control is
 * honest. The "before" frame is clipped with clip-path rather than resized, so
 * the two images stay in exact register at any container width.
 */
export function BeforeAfter({
  before,
  after,
  beforeLabel = "Before",
  afterLabel = "After",
  label = "Before and after",
}: {
  before: string;
  after: string;
  beforeLabel?: string;
  afterLabel?: string;
  label?: string;
}) {
  const [value, setValue] = useState(52);

  return (
    <div className="wz-compare">
      <img src={after} alt={afterLabel} loading="lazy" />
      <img
        src={before}
        alt={beforeLabel}
        loading="lazy"
        style={{ clipPath: `inset(0 ${100 - value}% 0 0)` }}
      />
      <span className="wz-compare-tag" style={{ left: "0.85rem" }}>
        {beforeLabel}
      </span>
      <span className="wz-compare-tag" style={{ right: "0.85rem" }}>
        {afterLabel}
      </span>
      <div className="wz-compare-handle" style={{ left: `${value}%` }} aria-hidden="true" />
      <input
        type="range"
        min="0"
        max="100"
        value={value}
        onChange={(event) => setValue(Number(event.target.value))}
        aria-label={label}
      />
    </div>
  );
}
