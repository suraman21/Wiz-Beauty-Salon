import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ArrowDown, ArrowRight } from "lucide-react";
import { BeforeAfter, BookingBand, EditorialLink, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
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
      <section className="home-hero" aria-labelledby="hero-title">
        <picture className="home-hero-media">
          {/* The hero photograph is shot twice: wide for desktop, and a portrait
              crop for phones, where a slice of the wide one would cut her face. */}
          {images.hero.portrait ? (
            <source media="(max-width: 47.5rem)" srcSet={images.hero.portrait} />
          ) : null}
          <img
            className="home-hero-image"
            src={images.hero.url}
            alt={images.hero.alt}
            width={2400}
            height={1350}
            fetchPriority="high"
          />
        </picture>
        <div className="home-hero-shade" />
        <div className="hero-content reveal-up">
          <p className="overline hero-overline">A new expression of beauty</p>
          <h1 id="hero-title">
            <span className="hero-name">WIZ Beauty Salon</span>
            <span>
              Beauty,
              <br />
              <em>your way.</em>
            </span>
          </h1>
          <p className="hero-copy">
            A moment to express yourself, feel beautiful, and make it entirely yours.
          </p>
          <div className="hero-actions">
            <Link to="/booking" className="btn btn-blush">
              Book an appointment <ArrowRight size={17} />
            </Link>
            <Link className="hero-secondary" to="/services">
              Explore services <ArrowRight size={17} />
            </Link>
          </div>
        </div>
        <a className="hero-scroll" href="#about" aria-label="Scroll to introduction">
          <ArrowDown size={18} />
          <span>SCROLL TO DISCOVER</span>
        </a>
      </section>

      <section id="about" className="intro-section section-wrap">
        <div className="intro-aside">
          <span className="overline">01 / The WIZ feeling</span>
          <div className="intro-line" />
        </div>
        <div className="intro-main">
          <h2>
            Beauty is personal.
            <br />
            <em>Make it yours.</em>
          </h2>
          <div className="intro-bottom">
            <p>
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
