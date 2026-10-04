import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { ArrowDown, ArrowRight, Menu, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import logo from "@/assets/wiz-logo.png.asset.json";
import hero from "@/assets/home-hero.jpg";
import hair from "@/assets/hair-detail.jpg";
import nails from "@/assets/nails-editorial.jpg";
import makeup from "@/assets/makeup-editorial.jpg";
import skin from "@/assets/skin-editorial.jpg";
import portrait from "@/assets/hero-portrait.jpg";
import space from "@/assets/salon-space.jpg";
import before from "@/assets/transformation-before.jpg";
import after from "@/assets/transformation-after.jpg";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "WIZ Beauty Salon | Beauty, Your Way" },
      { name: "description", content: "Explore the visual world of WIZ Beauty Salon: hair, nails, makeup and skin in a beauty experience made for you. Home page preview with demo imagery." },
      { property: "og:title", content: "WIZ Beauty Salon | Beauty, Your Way" },
      { property: "og:description", content: "Discover the beauty experience imagined for WIZ. Explore hair, nails, makeup and skin in our Home page preview." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Home,
});

const nav = [
  { label: "Home", href: "#home" },
  { label: "Services", href: "#services" },
  { label: "About", href: "#about" },
  { label: "Gallery", href: "#gallery" },
  { label: "Contact", href: "#contact" },
];

const services = [
  { name: "Hair", copy: "A little movement. A lot of feeling.", image: hair, alt: "Demo image of hair being styled", width: 1024, height: 1280, className: "service-hair" },
  { name: "Nails", copy: "The detail that changes everything.", image: nails, alt: "Demo image of a manicure", width: 768, height: 1024, className: "service-nails" },
  { name: "Makeup", copy: "Your features, beautifully expressed.", image: makeup, alt: "Demo beauty makeup portrait", width: 768, height: 1024, className: "service-makeup" },
  { name: "Skin", copy: "A fresh way to feel like yourself.", image: skin, alt: "Demo skincare portrait", width: 768, height: 1024, className: "service-skin" },
];

function TextLink({ href, children, light = false }: { href: string; children: React.ReactNode; light?: boolean }) {
  return <a href={href} className={`editorial-link ${light ? "editorial-link-light" : ""}`}>{children}<ArrowRight size={18} strokeWidth={1.5} aria-hidden="true" /></a>;
}

function Home() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [comparison, setComparison] = useState(50);

  return (
    <div className="home-page" id="home">
      <header className="site-header">
        <a href="#home" aria-label="WIZ Beauty Salon home" className="brand-mark"><img src={logo.url} alt="" /><span>WIZ <small>BEAUTY SALON</small></span></a>
        <nav className="desktop-nav" aria-label="Main navigation">{nav.map((item) => <a href={item.href} key={item.label}>{item.label}</a>)}</nav>
        <div className="header-actions">
          <Button asChild className="nav-book rounded-none bg-primary px-7 py-6 text-xs font-semibold uppercase tracking-widest text-primary-foreground shadow-none hover:bg-primary-hover"><a href="#booking">Book Now <ArrowRight size={15} /></a></Button>
          <Button variant="ghost" size="icon" className="menu-button" aria-label={menuOpen ? "Close menu" : "Open menu"} aria-expanded={menuOpen} onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X /> : <Menu />}</Button>
        </div>
        {menuOpen && <nav className="mobile-nav" aria-label="Mobile navigation">{nav.map((item) => <a onClick={() => setMenuOpen(false)} href={item.href} key={item.label}>{item.label}<ArrowRight size={16}/></a>)}</nav>}
      </header>

      <main>
        <section className="home-hero" aria-labelledby="hero-title">
          <img className="home-hero-image" src={hero} alt="Editorial beauty portrait, temporary demo image" width={1536} height={1024} fetchPriority="high" />
          <div className="home-hero-shade" />
          <div className="hero-content">
            <p className="overline hero-overline">A new expression of beauty</p>
            <h1 id="hero-title"><span className="hero-name">WIZ Beauty Salon</span><span>Beauty,<br /><em>your way.</em></span></h1>
            <p className="hero-copy">A moment to express yourself, feel beautiful, and make it entirely yours.</p>
            <div className="hero-actions">
              <Button asChild className="rounded-none bg-accent px-8 py-6 text-xs font-semibold uppercase tracking-widest text-accent-foreground shadow-none hover:bg-blush"><a href="#booking">Book an appointment <ArrowRight size={17}/></a></Button>
              <a className="hero-secondary" href="#services">Explore services <ArrowRight size={17}/></a>
            </div>
          </div>
          <a className="hero-scroll" href="#about" aria-label="Scroll to introduction"><ArrowDown size={18}/><span>SCROLL TO DISCOVER</span></a>
          <span className="hero-image-note">Editorial demo imagery</span>
        </section>

        <section id="about" className="intro-section section-wrap">
          <div className="intro-aside"><span className="overline">01 / The WIZ feeling</span><div className="intro-line" /></div>
          <div className="intro-main"><h2>Beauty is personal.<br /><em>Make it yours.</em></h2><div className="intro-bottom"><p>Imagine a space for your own expression of beauty — from the smallest detail to a whole new look. Come as you are; leave feeling more like you.</p><TextLink href="#about">Discover our story</TextLink></div></div>
        </section>

        <section id="services" className="services-section">
          <div className="section-wrap services-inner">
            <div className="section-heading"><div><span className="overline">02 / What we imagine for WIZ</span><h2>Every detail,<br /><em>beautifully you.</em></h2></div><p>Four ways to make a moment your own.</p></div>
            <div className="services-grid">{services.map((service, i) => <article className={`service-item ${service.className}`} key={service.name}><div className="service-image img-frame"><img src={service.image} alt={service.alt} width={service.width} height={service.height} loading="lazy" /></div><div className="service-caption"><span className="service-index">0{i + 1}</span><div><h3>{service.name}</h3><p>{service.copy}</p></div><ArrowRight size={22} strokeWidth={1.3}/></div></article>)}</div>
            <div className="service-end"><span className="demo-note">Imagery and categories are for visual preview only.</span><TextLink href="#booking">View all services</TextLink></div>
          </div>
        </section>

        <section id="gallery" className="gallery-section section-wrap">
          <div className="section-heading gallery-heading"><div><span className="overline">03 / A visual mood</span><h2>A little beauty,<br /><em>everywhere.</em></h2></div><p>Images here show the creative direction, not WIZ's actual work.</p></div>
          <div className="gallery-composition">
            <figure className="gallery-one img-frame"><img src={portrait} alt="Demo editorial beauty portrait" width={1024} height={1280} loading="lazy" /></figure>
            <figure className="gallery-two img-frame"><img src={space} alt="Demo salon-inspired interior, not the WIZ location" width={1280} height={896} loading="lazy" /></figure>
            <figure className="gallery-three img-frame"><img src={nails} alt="Demo manicure detail" width={768} height={1024} loading="lazy" /></figure>
            <span className="gallery-word" aria-hidden="true">WIZ</span>
          </div>
          <div className="gallery-end"><span className="demo-note">Temporary editorial images · Replace with WIZ photography</span><TextLink href="#transformation">View gallery</TextLink></div>
        </section>

        <section id="transformation" className="transformation-section">
          <div className="section-wrap transformation-inner">
            <div className="transformation-copy"><span className="overline">04 / The transformation</span><h2>See the<br /><em>difference.</em></h2><p>Sometimes a change in the mirror changes everything. Move the slider to explore a sample transformation.</p><span className="demo-note">Illustrative demo only — not a WIZ client result.</span><TextLink href="#booking" light>See more transformations</TextLink></div>
            <div className="comparison-wrap"><div className="comparison" aria-label="Illustrative before and after hair comparison"><img src={after} alt="Demo styled hair after" width={1024} height={768} loading="lazy" /><div className="comparison-before" style={{ width: `${comparison}%` }}><img src={before} alt="Demo hair before styling" width={1024} height={768} loading="lazy" /></div><span className="comparison-label comparison-label-before">BEFORE</span><span className="comparison-label comparison-label-after">AFTER</span><div className="comparison-handle" style={{ left: `${comparison}%` }} aria-hidden="true"><span>‹ &nbsp; ›</span></div><input type="range" min="0" max="100" value={comparison} onChange={(event) => setComparison(Number(event.target.value))} aria-label="Reveal before and after demo transformation" /></div></div>
          </div>
        </section>

        <section id="booking" className="booking-section"><div className="section-wrap booking-inner"><span className="overline">Your moment awaits</span><h2>Ready for your<br /><em>WIZ moment?</em></h2><p>Something beautiful starts with a little time for yourself.</p><Button asChild className="rounded-none bg-primary px-10 py-7 text-xs font-semibold uppercase tracking-widest text-primary-foreground shadow-none hover:bg-primary-hover"><a href="#contact">Book an appointment <ArrowRight size={18}/></a></Button><span className="demo-note">Booking is a preview. Contact and appointment details will be added after WIZ confirms them.</span></div></section>
      </main>

      <footer id="contact" className="site-footer"><div className="section-wrap"><div className="footer-top"><div><span className="overline">WIZ Beauty Salon</span><p>Beauty, <em>your way.</em></p></div><a href="#home" className="footer-back">Back to top ↑</a></div><div className="footer-bottom"><nav aria-label="Footer navigation">{nav.map((item) => <a href={item.href} key={item.label}>{item.label}</a>)}</nav><div className="footer-contact"><span>Contact details · Coming from WIZ</span><span>Location & hours · Coming from WIZ</span><span>Social links · Coming from WIZ</span></div></div><div className="footer-last"><span>© WIZ Beauty Salon · Home page concept</span><span>Demo imagery and copy are replaceable</span></div></div></footer>
    </div>
  );
}
