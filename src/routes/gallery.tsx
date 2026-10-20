import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { BeforeAfter, BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { categories, galleryWork, stories, type CategoryId } from "@/data/wiz";
import {
  fetchGalleryPage,
  fetchReviews,
  type GalleryCategory,
  type GalleryPhoto,
  type PublicReview,
} from "@/lib/content-api";
import { useSiteImages } from "@/lib/site-content";

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

type Item = {
  key: string;
  image: string;
  alt: string;
  title: string;
  caption: string | undefined;
  tall: boolean;
  /** The filter chip this photo belongs to, by slug — gallery photos and demo items alike. */
  category: string | undefined;
};

function Gallery() {
  const images = useSiteImages();
  const [filter, setFilter] = useState<string>("all");
  const [open, setOpen] = useState<number | null>(null);

  const [photos, setPhotos] = useState<GalleryPhoto[] | null>(null);
  const [filters, setFilters] = useState<GalleryCategory[]>([]);
  const [reviews, setReviews] = useState<PublicReview[]>([]);

  useEffect(() => {
    const controller = new AbortController();

    fetchGalleryPage(controller.signal)
      .then((page) => {
        setPhotos(page.photos);
        setFilters(page.categories);
      })
      .catch(() => setPhotos([]));

    fetchReviews(controller.signal)
      .then(setReviews)
      .catch(() => setReviews([]));

    return () => controller.abort();
  }, []);

  const live = photos !== null && photos.length > 0;

  // The salon's own photos when there are any, the demo set until then.
  const items = useMemo<Item[]>(() => {
    if (live) {
      return photos.map((photo, index) => ({
        key: `photo-${photo.id}`,
        image: photo.url,
        alt: photo.title !== "" ? photo.title : "Work by WIZ Beauty Salon",
        title: photo.title !== "" ? photo.title : "WIZ Beauty Salon",
        caption: photo.category ?? undefined,
        tall: index % 3 === 1,
        category: photo.category_slug ?? undefined,
      }));
    }

    return galleryWork.map((work) => ({
      key: work.id,
      image: work.image,
      alt: work.alt,
      title: work.title,
      caption: undefined,
      tall: work.tall ?? false,
      category: work.category,
    }));
  }, [live, photos]);

  // The panel decides which filters exist: only categories with a published
  // photo, so a chip can never open an empty wall.
  const filterList: { id: string; name: string }[] = live
    ? [{ id: "all", name: "All" }, ...filters.map((c) => ({ id: c.slug, name: c.name }))]
    : [
        { id: "all", name: "All" },
        ...categories.map((c) => ({ id: c.id as string, name: c.name })),
      ];

  const active = filterList.some((c) => c.id === filter) ? filter : "all";
  const shown = active === "all" ? items : items.filter((i) => i.category === active);

  // The three large tiles at the top: the salon's newest published photos, or
  // the design's own three when the panel has none yet.
  const featured = useMemo<Item[]>(() => {
    if (live) return items.slice(0, 3);

    return [
      {
        key: "f-a",
        image: images.makeup.url,
        alt: images.makeup.alt,
        title: "Evening glow",
        caption: "Makeup",
        tall: false,
        category: undefined,
      },
      {
        key: "f-b",
        image: images.after.url,
        alt: images.after.alt,
        title: "Long waves",
        caption: "Hair",
        tall: false,
        category: undefined,
      },
      {
        key: "f-c",
        image: images.skin.url,
        alt: images.skin.alt,
        title: "Fresh skin",
        caption: "Skin",
        tall: false,
        category: undefined,
      },
    ];
  }, [live, items, images]);

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
        copy={
          live
            ? "Recent work from the salon, shared with our clients' permission."
            : "Featured looks, transformations and the stories behind them. All images are temporary demo material."
        }
        image={images.hair.url}
        alt={images.hair.alt}
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
          <p>{live ? "From the salon's own album." : "Demo imagery — not WIZ client work."}</p>
        </div>
        <div className="featured-grid">
          {featured.map((tile, index) => (
            <figure className={`img-frame ${["f-a", "f-b", "f-c"][index] ?? "f-a"}`} key={tile.key}>
              <img src={tile.image} alt={tile.alt} loading="lazy" />
              <figcaption>
                {tile.title}
                {tile.caption !== undefined ? ` · ${tile.caption}` : ""}
              </figcaption>
            </figure>
          ))}
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
          <BeforeAfter before={images.before.url} after={images.after.url} />
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

        {filterList.length > 1 && (
          <div className="filter-bar" role="tablist" aria-label="Filter gallery">
            {filterList.map((c) => (
              <button
                key={c.id}
                role="tab"
                aria-selected={active === c.id}
                className={`chip ${active === c.id ? "is-active" : ""}`}
                onClick={() => setFilter(c.id)}
              >
                {c.name}
              </button>
            ))}
          </div>
        )}

        <div className="masonry">
          {shown.map((g, i) => (
            <button
              key={g.key}
              className={`masonry-item img-frame ${g.tall ? "tall" : ""}`}
              onClick={() => setOpen(i)}
              aria-label={`Open ${g.title}`}
            >
              <img src={g.image} alt={g.alt} loading="lazy" />
              <span>{g.title}</span>
            </button>
          ))}
        </div>

        {live && (
          <span className="demo-note">
            Photos published from the salon's panel. New work appears here automatically.
          </span>
        )}
      </section>

      <section className="stories">
        <div className="section-wrap">
          <span className="overline">04 / Client stories</span>
          <div className="stories-grid">
            {reviews.length > 0
              ? reviews.slice(0, 3).map((review) => (
                  <blockquote key={review.id}>
                    <p>“{review.body}”</p>
                    <footer>
                      {review.name}
                      {review.service ? ` · ${review.service}` : ""}
                    </footer>
                  </blockquote>
                ))
              : stories.map((s) => (
                  <blockquote key={s.id}>
                    <p>“{s.quote}”</p>
                    <footer>
                      {s.who} · {s.service}
                    </footer>
                  </blockquote>
                ))}
          </div>
          {reviews.length === 0 && (
            <span className="demo-note">
              Placeholder stories — real client words will be added only with permission.
            </span>
          )}
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
            <figcaption>{shown[open].title}</figcaption>
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
