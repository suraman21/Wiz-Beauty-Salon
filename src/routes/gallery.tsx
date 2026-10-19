import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { BeforeAfter, BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { categories, galleryWork, images, stories, type CategoryId } from "@/data/wiz";

export const Route = createFileRoute("/gallery")({
  head: () => ({
    meta: [
      { title: "Gallery | WIZ Beauty Salon" },
      {
        name: "description",
        content: "Featured work, transformations and client stories from WIZ Beauty Salon.",
      },
      { property: "og:title", content: "Gallery | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Featured work, before-and-after transformations and client stories.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Gallery,
});

function Gallery() {
  const [filter, setFilter] = useState<CategoryId | "all">("all");
  const [open, setOpen] = useState<number | null>(null);
  const shown = filter === "all" ? galleryWork : galleryWork.filter((g) => g.category === filter);

  useEffect(() => {
    if (open === null) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(null);
      if (e.key === "ArrowRight") setOpen((o) => (o === null ? o : (o + 1) % shown.length));
      if (e.key === "ArrowLeft")
        setOpen((o) => (o === null ? o : (o - 1 + shown.length) % shown.length));
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, shown.length]);

  return (
    <SiteLayout>
      <PageHero
        overline="Gallery"
        title={
          <>
            A little beauty,
            <br />
            <em>everywhere.</em>
          </>
        }
        copy="Featured looks, transformations and the stories behind them. All images are temporary demo material."
        image={images.hair}
        alt="Demo hair styling detail"
      />

      <section className="featured section-wrap">
        <div className="section-heading">
          <div>
            <span className="overline">01 / Featured work</span>
            <h2>
              The looks
              <br />
              <em>we love.</em>
            </h2>
          </div>
          <p>Demo imagery — not WIZ client work.</p>
        </div>
        <div className="featured-grid">
          <figure className="img-frame f-a">
            <img src={images.makeup} alt="Demo featured makeup look" loading="lazy" />
            <figcaption>Evening glow · Makeup</figcaption>
          </figure>
          <figure className="img-frame f-b">
            <img src={images.after} alt="Demo featured hair look" loading="lazy" />
            <figcaption>Long waves · Hair</figcaption>
          </figure>
          <figure className="img-frame f-c">
            <img src={images.skin} alt="Demo featured skin look" loading="lazy" />
            <figcaption>Fresh skin · Skin</figcaption>
          </figure>
        </div>
      </section>

      <section className="transformation-section">
        <div className="section-wrap transformation-inner">
          <div className="transformation-copy">
            <span className="overline">02 / Transformations</span>
            <h2>
              Before
              <br />
              <em>&amp; after.</em>
            </h2>
            <p>
              Drag the slider to compare. Real WIZ transformations will be added with client
              permission.
            </p>
            <span className="demo-note">Illustrative demo only — not a WIZ client result.</span>
          </div>
          <BeforeAfter before={images.before} after={images.after} />
        </div>
      </section>

      <section className="more-work section-wrap">
        <div className="section-heading">
          <div>
            <span className="overline">03 / More work</span>
            <h2>
              Browse
              <br />
              <em>by mood.</em>
            </h2>
          </div>
        </div>
        <div className="filter-bar" role="tablist" aria-label="Filter gallery">
          {[{ id: "all", name: "All" }, ...categories].map((c) => (
            <button
              key={c.id}
              role="tab"
              aria-selected={filter === c.id}
              className={`chip ${filter === c.id ? "is-active" : ""}`}
              onClick={() => setFilter(c.id as CategoryId | "all")}
            >
              {c.name}
            </button>
          ))}
        </div>
        <div className="masonry">
          {shown.map((g, i) => (
            <button
              key={g.id}
              className={`masonry-item img-frame ${g.tall ? "tall" : ""}`}
              onClick={() => setOpen(i)}
              aria-label={`Open ${g.title}`}
            >
              <img src={g.image} alt={g.alt} loading="lazy" />
              <span>{g.title}</span>
            </button>
          ))}
        </div>
      </section>

      <section className="stories">
        <div className="section-wrap">
          <span className="overline">04 / Client stories</span>
          <div className="stories-grid">
            {stories.map((s) => (
              <blockquote key={s.id}>
                <p>“{s.quote}”</p>
                <footer>
                  {s.who} · {s.service}
                </footer>
              </blockquote>
            ))}
          </div>
          <span className="demo-note">
            Placeholder stories — real client words will be added only with permission.
          </span>
        </div>
      </section>

      <BookingBand />

      {open !== null && shown[open] && (
        <div
          className="lightbox"
          role="dialog"
          aria-modal="true"
          aria-label={shown[open].title}
          onClick={() => setOpen(null)}
        >
          <button className="lb-btn lb-close" aria-label="Close" onClick={() => setOpen(null)}>
            <X />
          </button>
          <button
            className="lb-btn lb-prev"
            aria-label="Previous"
            onClick={(e) => {
              e.stopPropagation();
              setOpen((open - 1 + shown.length) % shown.length);
            }}
          >
            <ChevronLeft />
          </button>
          <figure onClick={(e) => e.stopPropagation()}>
            <img src={shown[open].image} alt={shown[open].alt} />
            <figcaption>{shown[open].title} · Demo image</figcaption>
          </figure>
          <button
            className="lb-btn lb-next"
            aria-label="Next"
            onClick={(e) => {
              e.stopPropagation();
              setOpen((open + 1) % shown.length);
            }}
          >
            <ChevronRight />
          </button>
        </div>
      )}
    </SiteLayout>
  );
}
