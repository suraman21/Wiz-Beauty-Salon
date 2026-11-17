import { apiGet } from "@/lib/api";

export type GalleryPhoto = {
  id: number;
  title: string;
  caption: string;
  url: string;
  /** Which of the salon's categories the photo belongs to, if any. */
  category_id: number | null;
  category: string | null;
  category_slug: string | null;
  /** True when staff ticked the photo to lead the home page's mood section. */
  show_on_home: boolean;
};

/** A category that has at least one published photo — the gallery filter. */
export type GalleryCategory = {
  id: number;
  name: string;
  slug: string;
  amharic: string;
  photo_count: number;
};

export type GalleryPage = {
  photos: GalleryPhoto[];
  categories: GalleryCategory[];
};

export type PublicReview = {
  id: number;
  name: string;
  rating: number;
  body: string;
  service: string | null;
  date: string | null;
};

/** Photos and filter categories, in one request. */
export function fetchGalleryPage(signal?: AbortSignal): Promise<GalleryPage> {
  return apiGet<Partial<GalleryPage>>("/api/v1/gallery", signal).then((body) => ({
    photos: body.photos ?? [],
    categories: body.categories ?? [],
  }));
}

/** Photos the salon has published in the panel, oldest first. */
export function fetchGallery(signal?: AbortSignal): Promise<GalleryPhoto[]> {
  return fetchGalleryPage(signal).then((page) => page.photos);
}

export type MoodImage = { url: string; alt: string };

/**
 * What the home page's "A visual mood" section shows.
 *
 * Staff tick photos in the Gallery to lead the home page, and those come
 * first, in the order the arrows give them. Any shortfall is filled with the
 * fallback slots (the Website Images the section always showed) so the
 * composition is never half-empty — which also covers a salon that has
 * ticked nothing yet.
 */
export function homeMoodPhotos(
  photos: GalleryPhoto[],
  fallback: MoodImage[],
  limit = 3,
): MoodImage[] {
  const chosen = photos
    .filter((photo) => photo.show_on_home)
    .map((photo) => ({
      url: photo.url,
      alt: photo.title !== "" ? photo.title : "Work by WIZ Beauty Salon",
    }));

  return [...chosen, ...fallback].slice(0, limit);
}

/** Approved reviews only, newest first. */
export function fetchReviews(signal?: AbortSignal): Promise<PublicReview[]> {
  return apiGet<{ reviews: PublicReview[] }>("/api/v1/reviews", signal).then(
    (body) => body.reviews,
  );
}
