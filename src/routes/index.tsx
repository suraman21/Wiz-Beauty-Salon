import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useRef, useState, type CSSProperties } from "react";
import { ArrowRight, CalendarDays, ChevronDown, Play } from "lucide-react";
import { BeforeAfter, BookingBand, EditorialLink, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
import heroPurple from "@/assets/hero-wiz-purple.jpg";
import { fetchCatalogue } from "@/lib/booking-api";
import {
  buildServicePages,
  featuredPages,
  localServicePages,
  taglineFor,
} from "@/lib/service-pages";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "WIZ Beauty Salon | Beauty, Your Way" },
      {
        name: "description",
        content:
          "WIZ Beauty Salon: hair, nails, makeup and skin in a beauty experience made for you. Explore services and book your appointment.",
      },
      { property: "og:title", content: "WIZ Beauty Salon | Beauty, Your Way" },
      {
        property: "og:description",
        content: "Hair, nails, makeup and skin — a beauty experience made entirely yours.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Home,
});

const layout = ["service-hair", "service-nails", "service-makeup", "service-skin"];

function Home() {
  const images = useSiteImages();
  const feeling = useReveal<HTMLElement>();

  // The four cards are the first four categories in the panel's order, so the
  // salon decides what its home page leads with — no deploy needed.
  const [featured, setFeatured] = useState(() => featuredPages(localServicePages(), 4));

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServicePages(catalogue);

        if (live.length > 0) setFeatured(featuredPages(live, 4));
      })
      .catch(() => {
        // Keep the local four.
      });

    return () => controller.abort();
  }, []);

  return (
    <SiteLayout>
      <section
        className="home-hero home-hero-wiz"
        aria-labelledby="hero-title"
        style={{ "--hero-art": `url(${heroPurple})` } as CSSProperties}
      >
        <img
          className="home-hero-image"
          src={heroPurple}
          alt="Model in a white off-shoulder gown and crystal tiara, in a softly lit purple salon"
          width={1672}
          height={941}
          fetchPriority="high"
        />
        <div className="home-hero-shade" />

        <div className="hero-content">
          <p className="wiz-eyebrow">
            <span aria-hidden="true" />
            Look good · Feel beautiful · Be confident
            <span aria-hidden="true" />
          </p>

          <h1 id="hero-title" className="wiz-title">
            <span className="wiz-title-line wiz-title-wiz">WIZ</span>
            <span className="wiz-title-line wiz-title-salon">Beauty Salon</span>
          </h1>

          <div className="hero-copy wiz-lede">
            <p className="wiz-lede-line">
              Beauty,
              <br />
              <em>your way.</em>
            </p>
            <p className="wiz-lede-body">
              A moment to express yourself, feel beautiful, and make it entirely yours.
            </p>
          </div>

          <div className="wiz-actions">
            <Link to="/booking" className="wiz-btn-primary">
              <CalendarDays size={17} aria-hidden="true" />
              Book Appointment
              <ArrowRight size={17} aria-hidden="true" />
            </Link>
            <a className="wiz-btn-video" href="#about">
              <span className="wiz-play" aria-hidden="true">
                <Play size={15} fill="currentColor" strokeWidth={0} />
              </span>
              <span className="wiz-video-label">Watch Video</span>
            </a>
          </div>
        </div>

        <a className="wiz-scroll" href="#about" aria-label="Scroll to introduction">
          <span className="wiz-scroll-pill" aria-hidden="true" />
          <span>Scroll Down</span>
          <ChevronDown size={16} aria-hidden="true" />
        </a>
      </section>

      <section
        id="about"
        ref={feeling.ref}
        className={`feeling-section${feeling.revealed ? " is-revealed" : ""}`}
      >
        <noscript>
          <style>{`.feeling-eyebrow,.feeling-line,.feeling-foot,.feeling-rule{opacity:1!important;animation:none!important;transform:none!important}.feeling-bloom{opacity:1!important}.feeling-orb{opacity:.8!important}`}</style>
        </noscript>
        <span className="feeling-bloom" aria-hidden="true" />
        <span className="feeling-orb" aria-hidden="true" />
        <div className="section-wrap feeling-inner">
          <p className="feeling-eyebrow">
            <span className="feeling-rule" aria-hidden="true" />
            <span>
              <span className="feeling-index">01</span>
              <span className="feeling-sep">/</span> The WIZ feeling
            </span>
            <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
          </p>
          <h2 className="feeling-title">
            <span className="feeling-line">Beauty is personal.</span>
            <span className="feeling-line feeling-line-em">Make it yours.</span>
          </h2>
          <div className="feeling-foot">
            <p className="feeling-body">
              A space for your own expression of beauty — from the smallest detail to a whole new
              look. Come as you are; leave feeling more like you.
            </p>
            <EditorialLink to="/about">Discover our story</EditorialLink>
          </div>
        </div>
      </section>

      <section className="services-section">
        <div className="section-wrap services-inner">
          <div className="section-heading">
            <div>
              <span className="overline">02 / Our services</span>
              <h2>
                Every detail,
                <br />
                <em>beautifully you.</em>
              </h2>
            </div>
            <p>Four ways to make a moment your own.</p>
          </div>
          <div className="services-grid">
            {featured.map((c, i) => (
              <Link
                to="/services"
                hash={c.id}
                className={`service-item ${layout[i] ?? ""}`}
                key={c.id}
              >
                <div className="service-image img-frame">
                  <img src={c.image} alt={c.alt} loading="lazy" />
                </div>
                <div className="service-caption">
                  <span className="service-index">0{i + 1}</span>
                  <div>
                    <h3>{c.name}</h3>
                    <p>{taglineFor(c)}</p>
                  </div>
                  <ArrowRight size={22} strokeWidth={1.3} />
                </div>
              </Link>
            ))}
          </div>
          <div className="service-end">
            <EditorialLink to="/services">View all services</EditorialLink>
          </div>
        </div>
      </section>

      <section className="gallery-section section-wrap">
        <div className="section-heading gallery-heading">
          <div>
            <span className="overline">03 / A visual mood</span>
            <h2>
              A little beauty,
              <br />
              <em>everywhere.</em>
            </h2>
          </div>
          <p>A glimpse of the work and the mood behind it.</p>
        </div>
        <div className="gallery-composition">
          <figure className="gallery-one img-frame">
            <img src={images.portrait.url} alt={images.portrait.alt} loading="lazy" />
          </figure>
          <figure className="gallery-two img-frame">
            <img src={images.space.url} alt={images.space.alt} loading="lazy" />
          </figure>
          <figure className="gallery-three img-frame">
            <img src={images.nails.url} alt={images.nails.alt} loading="lazy" />
          </figure>
          <span className="gallery-word" aria-hidden="true">
            WIZ
          </span>
        </div>
        <div className="gallery-end">
          <EditorialLink to="/gallery">View gallery</EditorialLink>
        </div>
      </section>

      <section className="transformation-section">
        <div className="section-wrap transformation-inner">
          <div className="transformation-copy">
            <span className="overline">04 / The transformation</span>
            <h2>
              See the
              <br />
              <em>difference.</em>
            </h2>
            <p>
              Sometimes a change in the mirror changes everything. Move the slider to see the
              difference side by side.
            </p>
            <EditorialLink to="/gallery" light>
              See more transformations
            </EditorialLink>
          </div>
          <div className="comparison-wrap">
            <BeforeAfter before={images.before.url} after={images.after.url} />
          </div>
        </div>
      </section>

      <BookingBand />
    </SiteLayout>
  );
}

/**
 * Flips on the first time the wrapped section scrolls into view, so the
 * section can play the hero's entrance far below the fold.
 */
function useReveal<T extends HTMLElement>() {
  const ref = useRef<T | null>(null);
  const [revealed, setRevealed] = useState(false);

  useEffect(() => {
    const node = ref.current;

    if (!node || typeof IntersectionObserver === "undefined") {
      setRevealed(true);
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          setRevealed(true);
          observer.disconnect();
        }
      },
      { threshold: 0.18, rootMargin: "0px 0px -10% 0px" },
    );

    observer.observe(node);

    return () => observer.disconnect();
  }, []);

  return { ref, revealed };
}
