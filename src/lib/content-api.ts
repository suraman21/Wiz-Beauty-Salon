import { apiGet } from "@/lib/api";

export type GalleryPhoto = {
  id: number;
  title: string;
  caption: string;
  url: string;
};

export type PublicReview = {
  id: number;
  name: string;
  rating: number;
  body: string;
  service: string | null;
  date: string | null;
};

/** Photos the salon has published in the panel, oldest first. */
export function fetchGallery(signal?: AbortSignal): Promise<GalleryPhoto[]> {
  return apiGet<{ photos: GalleryPhoto[] }>("/api/v1/gallery", signal).then((body) => body.photos);
}

/** Approved reviews only, newest first. */
export function fetchReviews(signal?: AbortSignal): Promise<PublicReview[]> {
  return apiGet<{ reviews: PublicReview[] }>("/api/v1/reviews", signal).then(
    (body) => body.reviews,
  );
}
