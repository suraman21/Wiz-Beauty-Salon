import { createFileRoute, Link } from "@tanstack/react-router";
import { ClosingCta, ImageBand, PageHead, SiteLayout } from "@/components/site/SiteChrome";
import { useSiteImages } from "@/lib/site-content";

export const Route = createFileRoute("/about")({
  head: () => ({
    meta: [
      { title: "About the salon | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "WIZ Beauty Salon in Addis Ababa: a salon for hair that is looked after, not just styled. Braids, colour, nails, makeup and waxing.",
      },
      { property: "og:title", content: "About the salon | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Who we are, how we work, and what we will not rush.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: About,
});

/**
 * No numbers here. These are three commitments, not three steps — numbering
 * them would imply an order that does not exist.
 */
const beliefs = [
  {
    am: "ማዳመጥ",
    title: "We listen first",
    copy: "No comb comes out until we know what you want, what your hair has been through, and how much time you have.",
  },
  {
    am: "ጥንቃቄ",
    title: "We do not rush a braid",
    copy: "The sectioning and the tension are the whole job. We book the hours the work needs rather than squeezing it into one.",
  },
  {
    am: "እውነት",
    title: "We tell you the truth",
    copy: "If a photograph will not work on your hair in one visit, or a colour needs two, we say so before you sit down.",
  },
];

function About() {
  const images = useSiteImages();

  return (
    <SiteLayout>
      <PageHead
        title={
          <>
            A salon for hair,
            <br />
            not a production line.
          </>
        }
        amharic="ስለ እኛ"
        intro="WIZ is a small salon in Addis Ababa. We would rather do fewer appointments properly than turn the chair over quickly."
      />

      <ImageBand image={images.braiding.url} alt={images.braiding.alt} />

      <section className="wz-wrap wz-story">
        <div className="wz-story-body">
          <h2>It started with people asking for the same thing twice.</h2>
          <p className="wz-prose">
            Clients kept coming back with the same complaint about other salons: the braids were too
            tight, the colour was not what was discussed, and nobody had asked before starting.
          </p>
          <p className="wz-prose">
            So WIZ was built around the opposite — a conversation before the work, a price agreed
            beforehand, and enough time booked that nothing has to be hurried at the end.
          </p>
        </div>
        <div className="wz-story-media">
          <img src={images.space.url} alt={images.space.alt} loading="lazy" />
        </div>
      </section>

      <section className="wz-wrap">
        <div className="wz-beliefs">
          {beliefs.map((belief) => (
            <div className="wz-belief" key={belief.title}>
              <p className="wz-eth" lang="am" aria-hidden="true">
                {belief.am}
              </p>
              <h3>{belief.title}</h3>
              <p>{belief.copy}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="wz-dark">
        <div className="wz-wrap wz-dark-inner">
          <div>
            <h2>Fourteen categories, in English and አማርኛ.</h2>
          </div>
          <div>
            <p>
              Braids, sewing, ponytail, mesh, cuts, colour, treatments, manicure, pedicure, makeup,
              brows, lashes, waxing and children's braids. Every option is listed with its Amharic
              name, so nothing gets lost in translation at the chair.
            </p>
            <Link to="/services" className="wz-link wz-link-light">
              See the full catalogue
            </Link>
          </div>
        </div>
      </section>

      <ClosingCta
        title={
          <>
            Come and see
            <br />
            the room first.
          </>
        }
        copy="You are welcome to come in, look around and ask questions before you book anything. Bring a photograph if you have one."
      />
    </SiteLayout>
  );
}
