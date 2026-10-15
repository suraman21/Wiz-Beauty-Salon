import { Link } from "@tanstack/react-router";
import { useEffect, useState, type ReactNode } from "react";
import { ArrowRight, Camera, Menu, X } from "lucide-react";
import logo from "@/assets/wiz-logo.png.asset.json";
import { contact, nav } from "@/data/wiz";

export function SiteHeader() {
  const [open, setOpen] = useState(false);
  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [open]);
  return (
    <header className="site-header">
      <Link to="/" aria-label="WIZ Beauty Salon home" className="brand-mark" onClick={() => setOpen(false)}>
        <img src={logo.url} alt="" />
        <span>WIZ <small>BEAUTY SALON</small></span>
      </Link>
      <nav className="desktop-nav" aria-label="Main navigation">
        {nav.map((item) => (
          <Link key={item.to} to={item.to} activeOptions={{ exact: true }} activeProps={{ className: "is-active" }}>{item.label}</Link>
        ))}
      </nav>
      <div className="header-actions">
        <Link to="/booking" className="btn btn-ink nav-book">Book Now <ArrowRight size={15} /></Link>
        <button type="button" className="menu-button" aria-label={open ? "Close menu" : "Open menu"} aria-expanded={open} onClick={() => setOpen(!open)}>
          {open ? <X /> : <Menu />}
        </button>
      </div>
      {open && (
        <nav className="mobile-nav" aria-label="Mobile navigation">
          {nav.map((item) => (
            <Link key={item.to} to={item.to} onClick={() => setOpen(false)} activeOptions={{ exact: true }} activeProps={{ className: "is-active" }}>{item.label}<ArrowRight size={16} /></Link>
          ))}
          <Link to="/booking" onClick={() => setOpen(false)} className="btn btn-ink mobile-book">Book an appointment <ArrowRight size={16} /></Link>
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
          <div><span className="overline">WIZ Beauty Salon</span><p>Beauty, <em>your way.</em></p></div>
          <Link to="/booking" className="btn btn-blush">Book an appointment <ArrowRight size={16} /></Link>
        </div>
        <div className="footer-bottom">
          <nav aria-label="Footer navigation">
            {nav.map((item) => <Link key={item.to} to={item.to}>{item.label}</Link>)}
            <Link to="/booking">Booking</Link>
          </nav>
          <div className="footer-contact">
            <span>{contact.phone}</span>
            <span>{contact.address}</span>
            <span>Instagram {contact.instagram} · TikTok {contact.tiktok}</span>
          </div>
        </div>
        <div className="footer-last"><span>© WIZ Beauty Salon</span><span>Demo imagery and details are placeholders until confirmed by WIZ</span></div>
      </div>
    </footer>
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

export function PageHero({ overline, title, copy, image, alt, tone = "plum" }: { overline: string; title: ReactNode; copy: string; image: string; alt: string; tone?: "plum" | "mist" }) {
  return (
    <section className={`page-hero page-hero-${tone}`}>
      <div className="section-wrap page-hero-inner">
        <div className="page-hero-copy reveal-up">
          <span className="overline">{overline}</span>
          <h1>{title}</h1>
          <p>{copy}</p>
        </div>
        <figure className="page-hero-image img-frame reveal"><img src={image} alt={alt} fetchPriority="high" /></figure>
      </div>
    </section>
  );
}

export function EditorialLink({ to, children, light = false }: { to: "/" | "/services" | "/about" | "/gallery" | "/booking" | "/contact"; children: ReactNode; light?: boolean }) {
  return <Link to={to} className={`editorial-link ${light ? "editorial-link-light" : ""}`}>{children}<ArrowRight size={18} strokeWidth={1.5} aria-hidden="true" /></Link>;
}

export function BookingBand({ title = <>Ready for your<br /><em>WIZ moment?</em></>, copy = "Something beautiful starts with a little time for yourself." }: { title?: ReactNode; copy?: string }) {
  return (
    <section className="booking-section">
      <div className="section-wrap booking-inner">
        <span className="overline">Your moment awaits</span>
        <h2>{title}</h2>
        <p>{copy}</p>
        <Link to="/booking" className="btn btn-ink btn-lg">Book an appointment <ArrowRight size={18} /></Link>
      </div>
    </section>
  );
}

export function BeforeAfter({ before, after, label = "Illustrative before and after comparison" }: { before: string; after: string; label?: string }) {
  const [v, setV] = useState(50);
  return (
    <div className="comparison" aria-label={label}>
      <img src={after} alt="Demo result after" loading="lazy" />
      <div className="comparison-before" style={{ width: `${v}%` }}><img src={before} alt="Demo before" loading="lazy" /></div>
      <span className="comparison-label comparison-label-before">BEFORE</span>
      <span className="comparison-label comparison-label-after">AFTER</span>
      <div className="comparison-handle" style={{ left: `${v}%` }} aria-hidden="true"><span>‹ &nbsp; ›</span></div>
      <input type="range" min="0" max="100" value={v} onChange={(e) => setV(Number(e.target.value))} aria-label="Reveal before and after demo transformation" />
    </div>
  );
}

export function PhotoSlot({ label }: { label: string }) {
  return (
    <div className="photo-slot" role="img" aria-label={`${label} — coming soon`}>
      <Camera size={20} strokeWidth={1.3} aria-hidden="true" />
      <span>{label}</span>
      <small>Coming soon</small>
    </div>
  );
}
