import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ArrowRight } from "lucide-react";
import { BeforeAfter, ClosingCta, SiteHero, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
import { fetchCatalogue } from "@/lib/booking-api";
import { buildServicePages, localServicePages, type ServicePage } from "@/lib/service-pages";
import { groupIntoFamilies, optionCount } from "@/lib/service-families";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "WIZ Beauty Salon | Braids, colour and nails in Addis Ababa" },
      {
        name: "description",
        content:
          "WIZ Beauty Salon in Addis Ababa: braids, sewing, colour, nails, makeup and waxing, in English and Amharic. Book the time your appointment actually needs.",
      },
      { property: "og:title", content: "WIZ Beauty Salon | Beauty, your way" },
      {
        property: "og:description",
        content:
          "Braids, colour, nails and makeup in Addis Ababa. 14 categories, 78 services, booked in a few taps.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Home,
});

function Home() {
  const images = useSiteImages();
  const [pages, setPages] = useState<ServicePage[]>(() => localServicePages());

  useEffect(() => {
    const controller = new AbortController();

    fetchCatalogue(controller.signal)
      .then((catalogue) => {
        const live = buildServicePages(catalogue);
        if (live.length > 0) setPages(live);
      })
      .catch(() => {
        // The bundled catalogue stays in place if the panel is unreachable.
      });

    return () => controller.abort();
  }, []);

  const families = groupIntoFamilies(pages);
  const totalOptions = pages.reduce((sum, page) => sum + page.items.length, 0);

  return (
    <SiteLayout>
      <SiteHero
        title={
          <>
            The braid,
            <br />
            and everything after.
          </>
        }
        amharic="ሹሩባ፣ ቀለም፣ ጥፍር"
        lede="Braiding, colour, nails, makeup and waxing in Addis Ababa. Tell us what you want and we will book the hours it actually takes."
        image={images.braid.url}
        alt={images.braid.alt}
      />

      <section className="wz-wrap wz-intro">
        <p className="wz-intro-mark" lang="am" aria-hidden="true">
          ውበት
        </p>
        <h2>A salon for hair that is looked after, not just styled.</h2>
        <div className="wz-intro-side">
          <p className="wz-prose">
            WIZ is a salon in Addis Ababa. We take braids, colour and relaxers seriously — the
            sectioning, the tension, the care afterwards — and we give nails, makeup and waxing the
            same attention. Come as you are.
          </p>
          <p className="wz-meta wz-mt-md">
            {pages.length} categories, {totalOptions} services, in English and አማርኛ.
          </p>
          <p className="wz-mt-sm">
            <Link to="/about" className="wz-link">
              Read about the salon
            </Link>
          </p>
        </div>
      </section>

      <section className="wz-wrap wz-index">
        <div className="wz-index-head">
          <h2>What would you like done?</h2>
          <p className="wz-prose">
            Everything we offer, grouped the way we actually work. Open a category to see the
            options and pick the one you want.
          </p>
        </div>

        {families.map((family) => (
          <div className="wz-family" key={family.id}>
            <div className="wz-family-head">
              <p className="wz-family-eth" lang="am">
                {family.amharic}
              </p>
              <p className="wz-family-name">{family.name}</p>
              <p className="wz-family-note">{family.note}</p>
            </div>
            <div className="wz-svc-list">
              {family.pages.map((page) => (
                <Link key={page.id} to="/services" hash={page.id} className="wz-svc-row">
                  <span className="wz-svc-name">
                    {page.name}
                    {page.amharic !== "" && (
                      <span className="wz-svc-eth" lang="am">
                        {page.amharic}
                      </span>
                    )}
                  </span>
                  <span className="wz-svc-count">{optionCount(page)}</span>
                  <ArrowRight className="wz-svc-arrow" size={16} aria-hidden="true" />
                </Link>
              ))}
            </div>
          </div>
        ))}

        <div className="wz-index-foot">
          <p className="wz-meta">
            Prices are given on request — a full head of braids and three braids are not the same
            job.
          </p>
          <Link to="/services" className="wz-btn wz-btn-ghost">
            Open the full catalogue <ArrowRight size={15} aria-hidden="true" />
          </Link>
        </div>
      </section>

      <section className="wz-dark">
        <div className="wz-wrap wz-dark-inner">
          <div>
            <h2>A braid can take four hours. We book the four hours.</h2>
          </div>
          <div>
            <p>
              Booking a single short slot is how salons end up rushing the part that matters. When
              you book with us, the time is held for the work you asked for — and if you are having
              more than one service, we add the times together instead of guessing.
            </p>
            <Link to="/booking" className="wz-link wz-link-light">
              See how booking works
            </Link>
          </div>
        </div>
      </section>

      <section className="wz-wrap wz-story">
        <div className="wz-story-media">
          <BeforeAfter
            before={images.before.url}
            after={images.after.url}
            label="Slide to compare a finished blow-dry with how it started"
          />
        </div>
        <div className="wz-story-body">
          <h2>What a difference four hours makes.</h2>
          <p className="wz-prose">
            Same person, same shirt, same afternoon light. One is a wash and go; the other is a full
            blow-dry. Drag the handle to see the change.
          </p>
          <p className="wz-meta wz-mt-md">
            Illustrative work, shown with permission of the model. Individual results depend on hair
            type and length.
          </p>
          <p className="wz-mt-md">
            <Link to="/gallery" className="wz-link">
              More of our work
            </Link>
          </p>
        </div>
      </section>

      <ClosingCta />
    </SiteLayout>
  );
}
