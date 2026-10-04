import { createFileRoute } from "@tanstack/react-router";
import { BookingBand, EditorialLink, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { images } from "@/data/wiz";

export const Route = createFileRoute("/about")({
  head: () => ({
    meta: [
      { title: "About | WIZ Beauty Salon" },
      { name: "description", content: "The story behind WIZ Beauty Salon: beauty that is personal, expressive and entirely yours." },
      { property: "og:title", content: "About | WIZ Beauty Salon" },
      { property: "og:description", content: "Beauty that is personal, expressive and entirely yours — the WIZ story." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: About,
});

const values = [
  { n: "01", t: "Personal", c: "Every look starts with listening — your features, your routine, your mood." },
  { n: "02", t: "Expressive", c: "Beauty as a way to say something about yourself, softly or boldly." },
  { n: "03", t: "Considered", c: "Calm, unhurried care in a space made to feel like a pause." },
];

function About() {
  return (
    <SiteLayout>
      <PageHero tone="mist" overline="About WIZ" title={<>Beauty that<br /><em>feels like you.</em></>} copy="WIZ is a space for your own expression of beauty — from the smallest detail to a whole new look." image={images.portrait} alt="Demo editorial portrait" />

      <section className="story section-wrap">
        <span className="overline">Our story</span>
        <p className="story-lead">We believe beauty is personal. <em>It should be expressed, not imposed.</em></p>
        <div className="story-cols">
          <p>[The WIZ founding story — to be written with the salon. This paragraph is placeholder text describing where the idea came from and why it matters.]</p>
          <p>[A second paragraph about the salon's approach and the people behind it. No names, dates or credentials are shown until WIZ confirms them.]</p>
        </div>
      </section>

      <section className="about-split">
        <figure className="img-frame"><img src={images.space} alt="Demo salon-inspired interior, not the WIZ location" loading="lazy" /></figure>
        <div className="about-split-copy">
          <span className="overline">What we stand for</span>
          <ol>{values.map((v) => <li key={v.n}><span>{v.n}</span><div><h3>{v.t}</h3><p>{v.c}</p></div></li>)}</ol>
          <EditorialLink to="/services" light>Explore our services</EditorialLink>
        </div>
      </section>

      <BookingBand />
    </SiteLayout>
  );
}
