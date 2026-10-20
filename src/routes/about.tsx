import { createFileRoute } from "@tanstack/react-router";
import { BookingBand, EditorialLink, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";

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

      <section className="story section-wrap">
        <span className="overline">Our story</span>
        <p className="story-lead">
          We believe beauty is personal. <em>It should be expressed, not imposed.</em>
        </p>
        <div className="story-cols">
          <p>
            WIZ began with a simple idea: that a visit to the salon should feel like a pause, not a
            production. A calm room, an honest conversation, and work shaped around the person in
            the chair rather than a picture on a wall.
          </p>
          <p>
            Our approach is detail-led — the finish of a cut, the line of a braid, the way a colour
            catches the light. Every appointment starts by listening to what you want, and ends with
            something that feels entirely yours.
          </p>
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
