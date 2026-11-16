import { Link, useRouterState } from "@tanstack/react-router";
import { useEffect, useState, type ReactNode } from "react";
import { ArrowRight, CalendarDays, Menu, X } from "lucide-react";
import logo from "@/assets/wiz-logo.png";
import { nav } from "@/data/wiz";
import { useSalonInfo } from "@/lib/site-content";
import { useReveal } from "@/lib/use-reveal";
import { hasDetails, whatsappHref } from "@/lib/salon-api";

export function SiteHeader() {
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const overHero = pathname === "/";

  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "";

    return () => {
      document.body.style.overflow = "";
    };
  }, [open]);

  // Over the hero the bar floats on the artwork; once the page moves it fades
  // to a solid plum so the links never sit on stray photography.
  useEffect(() => {
    if (!overHero) return;
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    return () => window.removeEventListener("scroll", onScroll);
  }, [overHero]);

  return (
    <header
      className={[
        "site-header",
        overHero ? "site-header-over-hero" : "",
        overHero && scrolled ? "is-scrolled" : "",
      ]
        .filter(Boolean)
        .join(" ")}
    >
      <Link
        to="/"
        aria-label="WIZ Beauty Salon home"
        className="brand-mark"
        onClick={() => setOpen(false)}
      >
        <img className="brand-logo" src={logo} alt="WIZ Beauty Salon" width={56} height={56} />
        <span className="brand-wordmark">WIZ Beauty Salon</span>
      </Link>
      <nav className="desktop-nav" aria-label="Main navigation">
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
      <div className="header-actions">
        <Link to="/booking/recover" className="nav-manage">
          Manage booking
        </Link>
        <Link to="/booking" className="btn btn-pink nav-book">
          <CalendarDays size={15} />
          Book Now
        </Link>
        <button
          type="button"
          className="menu-button"
          aria-label={open ? "Close menu" : "Open menu"}
          aria-expanded={open}
          onClick={() => setOpen(!open)}
        >
          {open ? <X /> : <Menu />}
        </button>
      </div>
      {open && (
        <div className="mobile-nav-scrim" aria-hidden="true" onClick={() => setOpen(false)} />
      )}
      {open && (
        <nav className="mobile-nav" aria-label="Mobile navigation">
          {nav.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              onClick={() => setOpen(false)}
              activeOptions={{ exact: true }}
              activeProps={{ className: "is-active" }}
            >
              {item.label}
              <ArrowRight size={16} />
            </Link>
          ))}
          <Link to="/booking/recover" onClick={() => setOpen(false)} className="mobile-manage-link">
            Manage booking <ArrowRight size={16} />
          </Link>
          <Link to="/booking" onClick={() => setOpen(false)} className="btn btn-ink mobile-book">
            Book an appointment <ArrowRight size={16} />
          </Link>
        </nav>
      )}
    </header>
  );
}

export function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="section-wrap">
        <div className="footer-top">
          <div>
            <span className="overline">WIZ Beauty Salon</span>
            <p>
              Beauty, <em>your way.</em>
            </p>
          </div>
          <Link to="/booking" className="btn btn-blush">
            Book an appointment <ArrowRight size={16} />
          </Link>
        </div>
        <div className="footer-bottom">
          <nav aria-label="Footer navigation">
            {nav.map((item) => (
              <Link key={item.to} to={item.to}>
                {item.label}
              </Link>
            ))}
            <Link to="/booking">Booking</Link>
            <Link to="/booking/recover">Manage booking</Link>
          </nav>
          <FooterContact />
        </div>
        <div className="footer-last">
          <span>© WIZ Beauty Salon</span>
        </div>
      </div>
    </footer>
  );
}

/**
 * The footer prints what the salon published in Settings — and nothing it has
 * not. An empty line of half-filled links looks broken, so it is left out.
 */
function FooterContact() {
  const salon = useSalonInfo();
  const details = salon?.contact;

  if (details === undefined || !hasDetails(details)) {
    return null;
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
    <div className="footer-contact">
      {details.phone !== "" && (
        <a href={`tel:${details.phone.replace(/[^\d+]/g, "")}`}>{details.phone}</a>
      )}
      {details.whatsapp !== "" && <a href={whatsappHref(details.whatsapp)}>WhatsApp</a>}
      {details.address !== "" && <span>{details.address}</span>}
      {details.email !== "" && <a href={`mailto:${details.email}`}>{details.email}</a>}
      {socials.length > 0 && (
        <span>
          {socials.map((item) => (
            <a key={item.label} href={item.href}>
              {item.label}
            </a>
          ))}
        </span>
      )}
    </div>
  );
}

export function SiteLayout({ children }: { children: ReactNode }) {
  return (
    <div className="home-page">
      <SiteHeader />
      <main>{children}</main>
      <SiteFooter />
    </div>
  );
}

export function PageHero({
  overline,
  title,
  copy,
  image,
  alt,
  tone = "plum",
}: {
  overline: string;
  title: ReactNode;
  copy: string;
  image: string;
  alt: string;
  tone?: "plum" | "mist";
}) {
  return (
    <section className={`page-hero page-hero-${tone}`}>
      <div className="section-wrap page-hero-inner">
        <div className="page-hero-copy reveal-up">
          <span className="overline">{overline}</span>
          <h1>{title}</h1>
          <p>{copy}</p>
        </div>
        <figure className="page-hero-image img-frame reveal">
          <img src={image} alt={alt} fetchPriority="high" />
        </figure>
      </div>
    </section>
  );
}

export function EditorialLink({
  to,
  children,
  light = false,
}: {
  to: "/" | "/services" | "/about" | "/gallery" | "/booking" | "/contact";
  children: ReactNode;
  light?: boolean;
}) {
  return (
    <Link to={to} className={`editorial-link ${light ? "editorial-link-light" : ""}`}>
      {children}
      <ArrowRight size={18} strokeWidth={1.5} aria-hidden="true" />
    </Link>
  );
}

export function BookingBand({
  title = (
    <>
      Ready for your
      <br />
      <em>WIZ moment?</em>
    </>
  ),
  copy = "Something beautiful starts with a little time for yourself.",
}: {
  title?: ReactNode;
  copy?: string;
}) {
  const reveal = useReveal<HTMLElement>();

  return (
    <section ref={reveal.ref} className={`booking-section${reveal.revealed ? " is-revealed" : ""}`}>
      <noscript>
        <style>{`.booking-eyebrow,.booking-title,.booking-copy,.booking-cta,.feeling-rule{opacity:1!important;animation:none!important;transform:none!important}.booking-bloom{opacity:1!important}`}</style>
      </noscript>
      <span className="booking-bloom" aria-hidden="true" />
      <div className="section-wrap booking-inner">
        <p className="booking-eyebrow">
          <span className="feeling-rule" aria-hidden="true" />
          <span className="booking-eyebrow-text">Your moment awaits</span>
          <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
        </p>
        <h2 className="booking-title">{title}</h2>
        <p className="booking-copy">{copy}</p>
        <Link to="/booking" className="btn btn-ink btn-lg booking-cta">
          Book an appointment <ArrowRight size={18} />
        </Link>
      </div>
    </section>
  );
}

export function BeforeAfter({
  before,
  after,
  label = "Before and after comparison",
}: {
  before: string;
  after: string;
  label?: string;
}) {
  const [v, setV] = useState(50);
  return (
    <div className="comparison" aria-label={label}>
      <img src={after} alt="After" loading="lazy" />
      <div className="comparison-before" style={{ width: `${v}%` }}>
        <img src={before} alt="Before" loading="lazy" />
      </div>
      <span className="comparison-label comparison-label-before">BEFORE</span>
      <span className="comparison-label comparison-label-after">AFTER</span>
      <div className="comparison-handle" style={{ left: `${v}%` }} aria-hidden="true">
        <span>‹ &nbsp; ›</span>
      </div>
      <input
        type="range"
        min="0"
        max="100"
        value={v}
        onChange={(e) => setV(Number(e.target.value))}
        aria-label="Reveal the before and after"
      />
    </div>
  );
}
