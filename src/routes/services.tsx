import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ArrowRight, Plus } from "lucide-react";
import { ClosingCta, PageHead, SiteLayout } from "@/components/site/SiteChrome";
import { fetchCatalogue } from "@/lib/booking-api";
import {
  PRICE_UNDISCLOSED,
  buildServicePages,
  localServicePages,
  type ServicePage,
} from "@/lib/service-pages";
import { groupIntoFamilies, optionCount } from "@/lib/service-families";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services and prices | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "The full WIZ Beauty Salon catalogue: braids, sewing, colour, treatments, nails, makeup and waxing, with every option in English and Amharic.",
      },
      { property: "og:title", content: "Services and prices | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "14 categories and 78 services. Open a category and book the option you want.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const [pages, setPages] = useState<ServicePage[]>(() => localServicePages());
  const [open, setOpen] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServicePages(catalogue);
        if (live.length > 0) setPages(live);
      })
      .catch(() => {
        // Bundled catalogue stays.
      });

    return () => controller.abort();
  }, []);

  // Links from the home page arrive as /services#braids — open that category.
  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (hash !== "" && pages.some((page) => page.id === hash)) setOpen(hash);
  }, [pages]);

  const families = groupIntoFamilies(pages);
  const totalOptions = pages.reduce((sum, page) => sum + page.items.length, 0);

  return (
    <SiteLayout>
      <PageHead
        title={
          <>
            The full
            <br />
            catalogue.
          </>
        }
        amharic="የአገልግሎት ዝርዝር"
        intro={`${pages.length} categories, ${totalOptions} services. Open any category to see the options inside, then book the one you want. Prices are confirmed when you ask, because a full head of braids is not the same job as three.`}
      />

      <section className="wz-wrap wz-index">
        {families.map((family) => (
          <div className="wz-family" key={family.id} id={family.id}>
            <div className="wz-family-head">
              <p className="wz-family-eth" lang="am">
                {family.amharic}
              </p>
              <p className="wz-family-name">{family.name}</p>
              <p className="wz-family-note">{family.note}</p>
              <div className="wz-family-thumb">
                <img src={family.image} alt="" loading="lazy" />
              </div>
            </div>

            <div className="wz-svc-list">
              {family.pages.map((page) => {
                const isOpen = open === page.id;

                return (
                  <div key={page.id} id={page.id} className="wz-svc-group">
                    <button
                      type="button"
                      className="wz-svc-row wz-svc-toggle"
                      aria-expanded={isOpen}
                      onClick={() => setOpen(isOpen ? null : page.id)}
                    >
                      <span className="wz-svc-name">
                        {page.name}
                        {page.amharic !== "" && (
                          <span className="wz-svc-eth" lang="am">
                            {page.amharic}
                          </span>
                        )}
                      </span>
                      <span className="wz-svc-count">{optionCount(page)}</span>
                      <Plus
                        className={`wz-svc-plus ${isOpen ? "is-open" : ""}`}
                        size={16}
                        aria-hidden="true"
                      />
                    </button>

                    {isOpen && (
                      <div className="wz-options">
                        {page.items.map((item) => (
                          <div className="wz-option" key={item.id}>
                            <span className="wz-option-name">{item.name}</span>
                            <span className="wz-option-price">
                              {item.price === "" ? PRICE_UNDISCLOSED : item.price}
                            </span>
                            <Link
                              to="/booking"
                              search={{ service: item.slug }}
                              className="wz-option-book"
                            >
                              Book <ArrowRight size={14} aria-hidden="true" />
                            </Link>
                          </div>
                        ))}
                        {page.description !== "" && (
                          <p className="wz-meta wz-mt-sm">{page.description}</p>
                        )}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        ))}

        <div className="wz-index-foot">
          <p className="wz-meta">
            Not sure which one you need? Book a consultation and describe it in your own words.
          </p>
          <Link to="/booking" className="wz-btn">
            Book a consultation <ArrowRight size={15} aria-hidden="true" />
          </Link>
        </div>
      </section>

      <ClosingCta
        title={
          <>
            Still deciding?
            <br />
            That is what the phone is for.
          </>
        }
        copy="If you cannot find what you want in the list, or the price is not clear, message us before you book. We would rather answer first than have you sit down for something you did not ask for."
      />
    </SiteLayout>
  );
}
