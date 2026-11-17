import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState, type CSSProperties } from "react";
import { ArrowRight, CalendarDays, ChevronDown, Play } from "lucide-react";
import { BeforeAfter, BookingBand, EditorialLink, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
import { useReveal } from "@/lib/use-reveal";
import heroPurple from "@/assets/hero-wiz-purple.jpg";
import { fetchCatalogue } from "@/lib/booking-api";
import { buildServices, localServices, servicesForHome } from "@/lib/service-pages";

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

function Home() {
  const images = useSiteImages();
  const feeling = useReveal<HTMLElement>();
  const services = useReveal<HTMLElement>();
  const gallery = useReveal<HTMLElement>();
  const transformation = useReveal<HTMLElement>();

  // The four cards are the first four categories in the panel's order, so the
  // salon decides what its home page leads with — no deploy needed.
  const [featured, setFeatured] = useState(() => servicesForHome(localServices(), 4));

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServices(catalogue);

        if (live.length > 0) setFeatured(servicesForHome(live, 4));
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

      <section
        id="services"
        ref={services.ref}
        className={`services-section${services.revealed ? " is-revealed" : ""}`}
      >
        <noscript>
          <style>{`.services-eyebrow,.service-card,.services-lede,.service-end,.services-title .feeling-line{opacity:1!important;animation:none!important;transform:none!important}.services-bloom{opacity:1!important}`}</style>
        </noscript>
        <span className="services-bloom" aria-hidden="true" />
        <div className="section-wrap services-inner">
          <div className="services-head">
            <p className="services-eyebrow">
              <span className="feeling-rule" aria-hidden="true" />
              <span>
                <span className="feeling-index">02</span>
                <span className="feeling-sep">/</span> Our services
              </span>
            </p>
            <h2 className="services-title">
              <span className="feeling-line">Every detail,</span>
              <span className="feeling-line feeling-line-em">beautifully you.</span>
            </h2>
            <p className="services-lede">Many ways to make a moment your own.</p>
          </div>
          <div className="service-cards">
            {featured.map((c, i) => (
              <Link
                to="/services"
                hash={c.id}
                className="service-card"
                key={c.id}
                style={{ "--i": i } as CSSProperties}
              >
                <div className="service-card-media">
                  <img src={c.image} alt={c.alt} loading="lazy" />
                  <span className="service-card-sheen" aria-hidden="true" />
                  <span className="service-card-index">{String(i + 1).padStart(2, "0")}</span>
                </div>
                <div className="service-card-body">
                  <h3 className="service-card-name">{c.name}</h3>
                </div>
              </Link>
            ))}
          </div>
          <div className="service-end">
            <EditorialLink to="/services">View all services</EditorialLink>
          </div>
        </div>
      </section>

      <section
        ref={gallery.ref}
        className={`gallery-section${gallery.revealed ? " is-revealed" : ""}`}
      >
        <noscript>
          <style>{`.gallery-eyebrow,.gallery-line,.gallery-lede,.gallery-figure,.gallery-end{opacity:1!important;animation:none!important;transform:none!important}.gallery-bloom{opacity:1!important}.gallery-orb{opacity:.7!important}`}</style>
        </noscript>
        <span className="gallery-bloom" aria-hidden="true" />
        <span className="gallery-orb" aria-hidden="true" />
        <div className="section-wrap gallery-inner">
          <p className="gallery-eyebrow">
            <span className="feeling-rule" aria-hidden="true" />
            <span>
              <span className="feeling-index">03</span>
              <span className="feeling-sep">/</span> A visual mood
            </span>
            <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
          </p>

          <div className="gallery-head">
            <h2 className="gallery-title">
              <span className="feeling-line gallery-line">A little beauty,</span>
              <span className="feeling-line feeling-line-em gallery-line">everywhere.</span>
            </h2>
            <p className="gallery-lede">A glimpse of the work and the mood behind it.</p>
          </div>

          <div className="gallery-composition">
            <figure
              className="gallery-one gallery-figure img-frame"
              style={{ "--i": 0 } as CSSProperties}
            >
              <img src={images.portrait.url} alt={images.portrait.alt} loading="lazy" />
            </figure>
            <figure
              className="gallery-two gallery-figure img-frame"
              style={{ "--i": 1 } as CSSProperties}
            >
              <img src={images.space.url} alt={images.space.alt} loading="lazy" />
            </figure>
            <figure
              className="gallery-three gallery-figure img-frame"
              style={{ "--i": 2 } as CSSProperties}
            >
              <img src={images.nails.url} alt={images.nails.alt} loading="lazy" />
            </figure>
            <span className="gallery-word" aria-hidden="true">
              WIZ
            </span>
          </div>

          <div className="gallery-end">
            <EditorialLink to="/gallery">View gallery</EditorialLink>
          </div>
        </div>
      </section>

      <section
        ref={transformation.ref}
        className={`transformation-section transformation-home${transformation.revealed ? " is-revealed" : ""}`}
      >
        <noscript>
          <style>{`.transformation-eyebrow,.transformation-line,.transformation-lede,.comparison-wrap,.transformation-end,.feeling-rule{opacity:1!important;animation:none!important;transform:none!important}.transformation-bloom{opacity:1!important}`}</style>
        </noscript>
        <span className="transformation-bloom" aria-hidden="true" />
        <div className="section-wrap transformation-inner">
          <p className="transformation-eyebrow">
            <span className="feeling-rule" aria-hidden="true" />
            <span>
              <span className="feeling-index">04</span>
              <span className="feeling-sep">/</span> The transformation
            </span>
            <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
          </p>

          <div className="transformation-head">
            <h2 className="transformation-title">
              <span className="feeling-line transformation-line">See the</span>
              <span className="feeling-line feeling-line-em transformation-line">difference.</span>
            </h2>
            <p className="transformation-lede">
              Sometimes a change in the mirror changes everything. Move the slider to see the
              difference side by side.
            </p>
          </div>

          <div className="comparison-wrap">
            <BeforeAfter before={images.before.url} after={images.after.url} />
          </div>

          <div className="transformation-end">
            <EditorialLink to="/gallery">See more transformations</EditorialLink>
          </div>
        </div>
      </section>

      <BookingBand />
    </SiteLayout>
  );
}
