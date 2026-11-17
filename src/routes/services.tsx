import { createFileRoute, Link } from "@tanstack/react-router";
import { SmartImage } from "@/components/site/SmartImage";
import { useEffect, useState } from "react";
import { ArrowRight, Heart } from "lucide-react";
import { BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
import { fetchCatalogue } from "@/lib/booking-api";
import { buildServices, localServices, type SalonService } from "@/lib/service-pages";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "The services WIZ Beauty Salon offers — hair, nails, makeup, skin and more, in English and Amharic. Pick one and book your time.",
      },
      { property: "og:title", content: "Services | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "The services WIZ offers, and a booking that takes a minute.",
      },
      { property: "og:type", content: "website" },
      { property: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const images = useSiteImages();
  const [services, setServices] = useState<SalonService[]>(() => localServices());

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServices(catalogue);

        if (live.length > 0) setServices(live);
      })
      .catch(() => {
        // Keep the bundled list.
      });

    return () => controller.abort();
  }, []);

  return (
    <SiteLayout>
      <PageHero
        variant="services"
        overline="Our services"
        title={
          <>
            Made for <em>every</em> moment.
          </>
        }
        copy="From everyday beauty to special occasions, we offer premium services tailored for you."
        cta={
          <a className="wiz-btn-primary" href="#service-list">
            Explore Services
            <ArrowRight size={16} aria-hidden="true" />
          </a>
        }
      />

      <section id="service-list" className="svc-hub section-wrap">
        <div className="svc-hub-head">
          <div>
            <span className="overline">What we offer</span>
            <h2>
              Choose your <em>service</em>
            </h2>
            <p>One tap books it. What happens inside is decided together at the salon.</p>
          </div>
          <div className="svc-hub-meta">
            <span className="svc-count-badge">
              <Heart size={12} /> {services.length} {services.length === 1 ? "service" : "services"}
            </span>
          </div>
        </div>

        <div className="svc-category-grid">
          {services.map((service, index) => (
            <Link
              key={service.id}
              id={service.id}
              to="/booking"
              search={{ service: service.id }}
              className="svc-category-card"
              aria-label={`Book ${service.name}`}
            >
              <div className="svc-category-image">
                <SmartImage src={service.image} alt={service.alt} loading="lazy" />
                <span className="svc-category-index">{String(index + 1).padStart(2, "0")}</span>
              </div>
              <div className="svc-category-body">
                <h3>{service.name}</h3>
                {service.amharic !== "" && (
                  <span lang="am" className="svc-category-am">
                    {service.amharic}
                  </span>
                )}
                {service.description !== "" && (
                  <p className="svc-category-desc">{service.description}</p>
                )}
                <span className="svc-category-cta">
                  Book <ArrowRight size={14} />
                </span>
              </div>
            </Link>
          ))}
        </div>

        <div className="svc-hub-help">
          <p>
            Not sure which one?{" "}
            <Link to="/contact" className="editorial-link">
              Call the salon and we will help <ArrowRight size={14} />
            </Link>
          </p>
        </div>
      </section>

      <BookingBand
        title={
          <>
            Ready when
            <br />
            <em>you are.</em>
          </>
        }
        copy="Choose a service and a time — we'll confirm the rest with you."
      />
    </SiteLayout>
  );
}
