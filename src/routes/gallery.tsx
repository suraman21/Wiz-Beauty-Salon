import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { ArrowRight, ChevronLeft, ChevronRight, X } from "lucide-react";
import { BeforeAfter, ClosingCta, PageHead, SiteLayout } from "@/components/site/SiteChrome";
import { categories, galleryWork } from "@/data/wiz";
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
      { title: "Our work | WIZ Beauty Salon" },
      {
        name: "description",
        content: "Braids, colour, nails and makeup from the WIZ Beauty Salon in Addis Ababa.",
      },
      { property: "og:title", content: "Our work | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Recent work and before-and-after appointments from WIZ Beauty Salon.",
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
  /** Filter chip slug, when the photo knows which category it belongs to. */
  category: string | undefined;
};

function Gallery() {
  const images = useSiteImages();
  const [filter, setFilter] = useState("all");
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

  const items = useMemo<Item[]>(() => {
    if (live) {
      return photos.map((photo) => ({
        key: `photo-${photo.id}`,
        image: photo.url,
        alt: photo.title !== "" ? photo.title : "Work by WIZ Beauty Salon",
        title: photo.title !== "" ? photo.title : "WIZ Beauty Salon",
        category: photo.category_slug ?? undefined,
      }));
    }

    return galleryWork.map((work) => ({
      key: work.id,
      image: work.image,
      alt: work.alt,
      title: work.title,
      category: work.category,
    }));
  }, [live, photos]);

  // The panel decides which filters exist — only categories with a published
  // photo — so a chip can never open an empty wall.
  const filterList: { id: string; name: string }[] = live
    ? [{ id: "all", name: "Everything" }, ...filters.map((c) => ({ id: c.slug, name: c.name }))]
    : [
        { id: "all", name: "Everything" },
        ...categories.map((c) => ({ id: c.id as string, name: c.name })),
      ];

  const active = filterList.some((c) => c.id === filter) ? filter : "all";
  const shown = active === "all" ? items : items.filter((i) => i.category === active);

  const featured = useMemo<Item[]>(
    () => [
      {
        key: "f-a",
        image: images.braid.url,
        alt: images.braid.alt,
        title: "Cornrows, finished",
        category: undefined,
      },
      {
        key: "f-b",
        image: images.braiding.url,
        alt: images.braiding.alt,
        title: "Sectioning",
        category: undefined,
      },
      {
        key: "f-c",
        image: images.kids.url,
        alt: images.kids.alt,
        title: "Beads and braids",
        category: undefined,
      },
    ],
    [images],
  );

  useEffect(() => {
    if (open === null) return;

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(null);
      if (event.key === "ArrowRight")
        setOpen((current) => (current === null ? current : (current + 1) % shown.length));
      if (event.key === "ArrowLeft")
        setOpen((current) =>
          current === null ? current : (current - 1 + shown.length) % shown.length,
        );
    };

    window.addEventListener("keydown", onKey);

    return () => window.removeEventListener("keydown", onKey);
  }, [open, shown.length]);

  return (
    <SiteLayout>
      <PageHead
        title={
          <>
            Work done
            <br />
            in this room.
          </>
        }
        amharic="ስራዎቻችን"
        intro="Braids, colour, nails and makeup, photographed at the salon and shared with each client's permission. No filters on the hair."
      />

      <section className="wz-wrap" style={{ paddingBottom: "clamp(2rem, 4vw, 3.5rem)" }}>
        <div className="wz-featured">
          {featured.map((tile) => (
            <figure className="wz-gallery-item" key={tile.key}>
              <img src={tile.image} alt={tile.alt} loading="lazy" />
              <figcaption className="wz-gallery-cap">
                <span>{tile.title}</span>
              </figcaption>
            </figure>
          ))}
        </div>
      </section>

      <section className="wz-dark">
        <div className="wz-wrap wz-dark-inner">
          <div>
            <h2>Four hours between these two photographs.</h2>
          </div>
          <div>
            <p>
              Drag the handle. Same person, same shirt, same afternoon light — a wash and go on one
              side, a full blow-dry on the other.
            </p>
            <p className="wz-meta wz-mt-sm" style={{ color: "var(--wz-mauve)" }}>
              Illustrative work. Results depend on hair type and length.
            </p>
          </div>
        </div>
      </section>

      <section className="wz-wrap" style={{ paddingBlock: "clamp(2rem, 4vw, 3.5rem)" }}>
        <BeforeAfter
          before={images.before.url}
          after={images.after.url}
          label="Slide to compare the finished blow-dry with how it started"
        />
      </section>

      <section className="wz-wrap wz-pad-md">
        <div className="wz-index-head">
          <h2>Browse by what you want done.</h2>
          <p className="wz-prose">{items.length} photographs. Tap one to see it full size.</p>
        </div>

        {filterList.length > 1 && (
          <div className="wz-filters" role="group" aria-label="Filter work by category">
            {filterList.map((chip) => (
              <button
                key={chip.id}
                type="button"
                className="wz-filter"
                aria-pressed={active === chip.id}
                onClick={() => setFilter(chip.id)}
              >
                {chip.name}
              </button>
            ))}
          </div>
        )}

        <div className="wz-gallery-grid">
          {shown.map((item, index) => (
            <figure className="wz-gallery-item" key={item.key}>
              <button
                type="button"
                onClick={() => setOpen(index)}
                aria-label={`Open ${item.title} full size`}
                style={{
                  display: "block",
                  border: 0,
                  padding: 0,
                  background: "none",
                  width: "100%",
                }}
              >
                <img src={item.image} alt={item.alt} loading="lazy" />
              </button>
              <figcaption className="wz-gallery-cap">
                <span>{item.title}</span>
              </figcaption>
            </figure>
          ))}
        </div>

        {shown.length === 0 && <p className="wz-prose">No photographs in this category yet.</p>}
      </section>

      {reviews.length > 0 && (
        <section className="wz-wrap wz-pad-md">
          <div className="wz-beliefs" style={{ borderTop: 0 }}>
            {reviews.slice(0, 3).map((review) => (
              <blockquote key={review.id} className="wz-belief">
                <p
                  style={{
                    fontFamily: "var(--wz-serif)",
                    fontSize: "1.15rem",
                    color: "var(--wz-ink)",
                  }}
                >
                  “{review.body}”
                </p>
                <p className="wz-meta">
                  {review.name}
                  {review.service ? `, ${review.service}` : ""}
                </p>
              </blockquote>
            ))}
          </div>
        </section>
      )}

      <ClosingCta
        title={
          <>
            Bring a photograph.
            <br />
            We will tell you if it will work.
          </>
        }
        copy="Hair type, length and the condition it is in decide what is possible in one appointment. Send us a picture before you book and we will say honestly whether it will take one visit or two."
      />

      {open !== null && shown[open] && (
        <div
          className="wz-lightbox"
          role="dialog"
          aria-modal="true"
          aria-label={shown[open].title}
          onClick={() => setOpen(null)}
        >
          <button
            type="button"
            className="wz-lb-close"
            aria-label="Close"
            onClick={() => setOpen(null)}
          >
            <X size={20} />
          </button>
          <button
            type="button"
            className="wz-lb-nav wz-lb-prev"
            aria-label="Previous photograph"
            onClick={(event) => {
              event.stopPropagation();
              setOpen((open - 1 + shown.length) % shown.length);
            }}
          >
            <ChevronLeft size={22} />
          </button>
          <figure onClick={(event) => event.stopPropagation()}>
            <img src={shown[open].image} alt={shown[open].alt} />
            <figcaption>
              {shown[open].title}
              <span className="wz-meta">
                {" "}
                · {open + 1} of {shown.length}
              </span>
            </figcaption>
          </figure>
          <button
            type="button"
            className="wz-lb-nav wz-lb-next"
            aria-label="Next photograph"
            onClick={(event) => {
              event.stopPropagation();
              setOpen((open + 1) % shown.length);
            }}
          >
            <ChevronRight size={22} />
          </button>
        </div>
      )}
    </SiteLayout>
  );
}
