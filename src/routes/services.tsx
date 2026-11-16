import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { ArrowRight, Heart, Sparkles } from "lucide-react";
import { BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
import { fetchCatalogue } from "@/lib/booking-api";
import {
  PRICE_UNDISCLOSED,
  buildServicePages,
  localServicePages,
  type ServicePage,
} from "@/lib/service-pages";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "Explore WIZ Beauty Salon's 14 service categories in English and Amharic, including hair, nails, makeup and kids' braids.",
      },
      { property: "og:title", content: "Services | WIZ Beauty Salon" },
      {
        property: "og:description",
        content:
          "Explore WIZ's bilingual service menu and choose your appointment or an undecided request.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const images = useSiteImages();
  const [pages, setPages] = useState<ServicePage[]>(() => localServicePages());
  const [selected, setSelected] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServicePages(catalogue);
        if (live.length > 0) setPages(live);
      })
      .catch(() => {});
    return () => controller.abort();
  }, []);

  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (hash && pages.some((p) => p.id === hash)) setSelected(hash);
  }, [pages]);

  const activePage = useMemo(
    () => (selected ? (pages.find((p) => p.id === selected) ?? null) : null),
    [pages, selected],
  );

  const handleSelect = (id: string) => {
    setSelected(id);
    window.location.hash = id;
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handleBack = () => {
    setSelected(null);
    history.replaceState(null, "", window.location.pathname);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <SiteLayout>
      <PageHero
        overline="Services"
        title={
          <>
            Made for
            <br />
            <em>every moment.</em>
          </>
        }
        copy="Fourteen ways to feel more like you — pick a category, then choose the detail that’s yours."
        image={images.makeup.url}
        alt={images.makeup.alt}
      />

      {activePage === null ? (
        <section className="svc-hub section-wrap">
          <div className="svc-hub-head">
            <div>
              <span className="overline">Browse by category</span>
              <h2>
                What would you <em>like today?</em>
              </h2>
              <p>Tap a card to see the services inside — each one books in a tap.</p>
            </div>
            <div className="svc-hub-meta">
              <span className="svc-count-badge">
                <Heart size={12} /> {pages.length} categories
              </span>
              <span className="svc-count-badge svc-count-badge--muted">
                {pages.reduce((n, p) => n + p.items.length, 0)} services
              </span>
            </div>
          </div>

          <div className="svc-category-grid">
            {pages.map((cat, idx) => (
              <button
                key={cat.id}
                onClick={() => handleSelect(cat.id)}
                className="svc-category-card"
                aria-label={`Open ${cat.name}`}
              >
                <div className="svc-category-image">
                  <img src={cat.image} alt={cat.alt} loading="lazy" />
                  <span className="svc-category-index">0{idx + 1}</span>
                </div>
                <div className="svc-category-body">
                  <h3>{cat.name}</h3>
                  {cat.amharic !== "" && (
                    <span lang="am" className="svc-category-am">
                      {cat.amharic}
                    </span>
                  )}
                  <span className="svc-category-count">
                    <Sparkles size={11} /> {cat.items.length}{" "}
                    {cat.items.length === 1 ? "service" : "services"}
                  </span>
                  <span className="svc-category-cta">
                    Explore <ArrowRight size={14} />
                  </span>
                </div>
              </button>
            ))}
          </div>

          <div className="svc-hub-help">
            <p>
              Not sure?{" "}
              <Link to="/booking" className="editorial-link">
                Book a time and tell us what you have in mind <ArrowRight size={14} />
              </Link>
            </p>
          </div>
        </section>
      ) : (
        <section className="svc-detail">
          <div className="section-wrap svc-detail-head">
            <button onClick={handleBack} className="svc-back">
              ← Back to categories
            </button>
            <div className="svc-detail-hero">
              <figure className="svc-detail-image img-frame">
                <img src={activePage.image} alt={activePage.alt} />
              </figure>
              <div className="svc-detail-copy">
                <span className="overline">
                  {pages.findIndex((p) => p.id === activePage.id) + 1 < 10
                    ? `0${pages.findIndex((p) => p.id === activePage.id) + 1}`
                    : pages.findIndex((p) => p.id === activePage.id) + 1}{" "}
                  / {activePage.name}
                </span>
                <h2>
                  {activePage.name}
                  {activePage.amharic && (
                    <>
                      <br />
                      <em lang="am">{activePage.amharic}</em>
                    </>
                  )}
                </h2>
                {activePage.description ? (
                  <p>{activePage.description}</p>
                ) : (
                  <p>
                    {activePage.items.length} services — pick the one that feels right and book in
                    one tap.
                  </p>
                )}
                <div className="svc-detail-meta">
                  <span className="svc-chip-count">{activePage.items.length} options</span>
                </div>
              </div>
            </div>
          </div>

          <div className="section-wrap svc-sub-list-wrap">
            <div className="svc-sub-head">
              <h3>All services in {activePage.name}</h3>
              <span className="svc-sub-hint">
                Tap Book to continue • Prices are confirmed at the salon
              </span>
            </div>
            <ul className="svc-sub-list">
              {activePage.items.map((item) => (
                <li key={item.id} className="svc-sub-item">
                  <div className="svc-sub-main">
                    {item.image ? (
                      <img className="svc-sub-thumb" src={item.image} alt="" loading="lazy" />
                    ) : (
                      <span className="svc-sub-dot" aria-hidden="true" />
                    )}
                    <div>
                      <h4>{item.name}</h4>
                      <span className="svc-sub-price">
                        {item.price === "" ? PRICE_UNDISCLOSED : item.price}
                      </span>
                    </div>
                  </div>
                  <Link to="/booking" search={{ service: item.slug }} className="svc-sub-book">
                    Book <ArrowRight size={14} />
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </section>
      )}

      <BookingBand
        title={
          <>
            Not sure what
            <br />
            <em>to choose?</em>
          </>
        }
        copy="Book a time and tell us what you have in mind — we'll guide you from there."
      />
    </SiteLayout>
  );
}
