import { createFileRoute } from "@tanstack/react-router";
import { BookingBand, EditorialLink, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";
import { useReveal } from "@/lib/use-reveal";

export const Route = createFileRoute("/about")({
  head: () => ({
    meta: [
      { title: "About | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "The story behind WIZ Beauty Salon: beauty that is personal, expressive and entirely yours.",
      },
      { property: "og:title", content: "About | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Beauty that is personal, expressive and entirely yours — the WIZ story.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: About,
});

const values = [
  {
    n: "01",
    t: "Personal",
    c: "Every look starts with listening — your features, your routine, your mood.",
  },
  {
    n: "02",
    t: "Expressive",
    c: "Beauty as a way to say something about yourself, softly or boldly.",
  },
  { n: "03", t: "Considered", c: "Calm, unhurried care in a space made to feel like a pause." },
];

function About() {
  const images = useSiteImages();
  const reveal = useReveal<HTMLElement>();

  return (
    <SiteLayout>
      <PageHero
        tone="mist"
        overline="About WIZ"
        title={
          <>
            Beauty that
            <br />
            <em>feels like you.</em>
          </>
        }
        copy="WIZ is a space for your own expression of beauty — from the smallest detail to a whole new look."
        image={images.portrait.url}
        alt={images.portrait.alt}
      />

      <section ref={reveal.ref} className={`story${reveal.revealed ? " is-revealed" : ""}`}>
        <noscript>
          <style>{`.story-eyebrow,.story-line,.story-cols,.feeling-rule{opacity:1!important;animation:none!important;transform:none!important}.story-bloom{opacity:1!important}`}</style>
        </noscript>
        <span className="story-bloom" aria-hidden="true" />
        <div className="section-wrap story-inner">
          <p className="story-eyebrow">
            <span className="feeling-rule" aria-hidden="true" />
            <span className="story-eyebrow-text">Our story</span>
            <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
          </p>
          <p className="story-lead">
            <span className="feeling-line story-line">We believe beauty is personal.</span>
            <span className="feeling-line feeling-line-em story-line">
              It should be expressed, not imposed.
            </span>
          </p>
          <div className="story-cols">
            <p>
              WIZ began with a simple idea: that beauty should feel personal. A place where you are
              listened to before a single brush, comb or file is picked up.
            </p>
            <p>
              Every visit is shaped around you — your hair, your skin, your occasion, your mood. Our
              team works with care and detail, and leaves you room to say what you want, and what
              you don't.
            </p>
          </div>
        </div>
      </section>

      <section className="about-split">
        <figure className="img-frame">
          <img src={images.space.url} alt={images.space.alt} loading="lazy" />
        </figure>
        <div className="about-split-copy">
          <span className="overline">What we stand for</span>
          <ol>
            {values.map((v) => (
              <li key={v.n}>
                <span>{v.n}</span>
                <div>
                  <h3>{v.t}</h3>
                  <p>{v.c}</p>
                </div>
              </li>
            ))}
          </ol>
          <EditorialLink to="/services" light>
            Explore our services
          </EditorialLink>
        </div>
      </section>

      <BookingBand />
    </SiteLayout>
  );
}
