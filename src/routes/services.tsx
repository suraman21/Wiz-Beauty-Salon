import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ArrowRight } from "lucide-react";
import { BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { useSiteImages } from "@/lib/site-content";
import { fetchCatalogue } from "@/lib/booking-api";
import {
  PRICE_UNDISCLOSED,
  buildServicePages,
  hasUnpricedItems,
  localServicePages,
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
  const [filter, setFilter] = useState<string>("all");

  // The panel's catalogue arrives after the first paint; until then (and if the
  // request fails) the page shows the local copy rather than an empty list.
  const [pages, setPages] = useState(localServicePages);

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServicePages(catalogue);

        if (live.length > 0) setPages(live);
      })
      .catch(() => {
        // Keep what is already on screen — a slow or down API is not an error
        // the customer needs to see.
      });

    return () => controller.abort();
  }, []);

  const active = filter !== "all" && pages.some((page) => page.id === filter) ? filter : "all";
  const shown = active === "all" ? pages : pages.filter((page) => page.id === active);
  const unpriced = hasUnpricedItems(pages);
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
        copy="Hair, nails, makeup and more — choose a category, then select your appointment."
        image={images.makeup.url}
        alt={images.makeup.alt}
      />

      <div className="filter-bar section-wrap" role="tablist" aria-label="Service categories">
        <Button
          variant="ghost"
          role="tab"
          aria-selected={active === "all"}
          className={`chip h-auto rounded-none ${active === "all" ? "is-active" : ""}`}
          onClick={() => setFilter("all")}
        >
          All
        </Button>
        {pages.map((c) => (
          <Button
            variant="ghost"
            key={c.id}
            role="tab"
            aria-selected={active === c.id}
            className={`chip h-auto rounded-none flex-wrap whitespace-normal ${active === c.id ? "is-active" : ""}`}
            onClick={() => setFilter(c.id)}
          >
            {c.name} {c.amharic !== "" && <span lang="am">{c.amharic}</span>}
          </Button>
        ))}
      </div>

      <div className="svc-list">
        {shown.map((c, i) => (
          <section id={c.id} key={c.id} className={`svc-cat ${i % 2 ? "svc-flip" : ""}`}>
            <div className="section-wrap svc-cat-inner">
              <figure className="svc-cat-image img-frame">
                <img src={c.image} alt={c.alt} loading="lazy" />
              </figure>
              <div className="svc-cat-body">
                <span className="overline">
                  {String(pages.indexOf(c) + 1).padStart(2, "0")} / {c.name}
                </span>
                <h2 className="break-words">
                  {c.name}
                  <br />
                  {c.amharic !== "" && (
                    <em lang="am" className="font-sans text-2xl leading-normal not-italic">
                      {c.amharic}
                    </em>
                  )}
                </h2>
                <ul className="svc-items">
                  {c.items.map((item) => (
                    <li key={item.id}>
                      <div className="svc-item-text">
                        {item.image !== null && (
                          <img className="svc-thumb" src={item.image} alt="" loading="lazy" />
                        )}
                        <div className="min-w-0 break-words">
                          <h3>{item.name}</h3>
                          <span>{item.price === "" ? PRICE_UNDISCLOSED : item.price}</span>
                        </div>
                      </div>
                      <Link
                        to="/booking"
                        search={{ service: item.slug }}
                        className="svc-book"
                        aria-label={`Book ${item.name}`}
                      >
                        Book <ArrowRight size={15} />
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </section>
        ))}
      </div>

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
