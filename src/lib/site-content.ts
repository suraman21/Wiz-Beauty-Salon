/**
 * The picture and detail sets the whole site shares.
 *
 * One small store rather than a React context: the page is split into chunks
 * per route, and a context created in one chunk is not the same object in
 * another. A module-level store keeps every consumer in step — each copy of the
 * module fetches once and notifies its own readers.
 *
 * Nothing here is a hard dependency: the bundled photographs and the
 * placeholder copy stay in place until (or unless) the panel answers.
 */

import { useSyncExternalStore } from "react";
import { images as bundled } from "@/data/wiz";
import { apiGet } from "@/lib/api";
import { fetchSalon, type SalonInfo } from "@/lib/salon-api";

export type SiteImage = { url: string; alt: string };

export type SiteImageSet = Record<keyof typeof bundled, SiteImage>;

/** What the design shipped with, used until — or instead of — the API answer. */
export const bundledImages: SiteImageSet = {
  hero: { url: bundled.hero, alt: "Editorial beauty portrait" },
  portrait: { url: bundled.portrait, alt: "Editorial beauty portrait" },
  space: { url: bundled.space, alt: "Salon-inspired interior" },
  nails: { url: bundled.nails, alt: "Manicure detail" },
  hair: { url: bundled.hair, alt: "Hair being styled" },
  makeup: { url: bundled.makeup, alt: "Makeup portrait" },
  skin: { url: bundled.skin, alt: "Skincare portrait" },
  before: { url: bundled.before, alt: "Before a transformation" },
  after: { url: bundled.after, alt: "After a transformation" },
};

export type ImagePayload = { images?: Record<string, { url?: string; alt?: string }> };

/** Merge whatever the panel returned over the bundled set. */
export function mergeImages(payload: ImagePayload | null): SiteImageSet {
  const merged: SiteImageSet = { ...bundledImages };

  for (const slot of Object.keys(bundledImages) as (keyof SiteImageSet)[]) {
    const published = payload?.images?.[slot];

    if (published !== undefined && typeof published.url === "string" && published.url !== "") {
      merged[slot] = {
        url: published.url,
        alt: published.alt && published.alt !== "" ? published.alt : bundledImages[slot].alt,
      };
    }
  }

  return merged;
}

export type SiteContent = { images: SiteImageSet; salon: SalonInfo | null };

const initial: SiteContent = { images: bundledImages, salon: null };

let state: SiteContent = initial;
let started = false;

const listeners = new Set<() => void>();

function publish(next: Partial<SiteContent>): void {
  state = { ...state, ...next };

  for (const listener of listeners) listener();
}

/** Fetch once per module instance; a failed request leaves the fallbacks in place. */
export function loadSiteContent(): void {
  if (started || typeof window === "undefined") return;

  started = true;

  apiGet<ImagePayload>("/api/v1/images")
    .then((payload) => publish({ images: mergeImages(payload) }))
    .catch(() => {
      // Keep the bundled pictures — a slow API should never leave a hole.
    });

  fetchSalon()
    .then((salon) => publish({ salon }))
    .catch(() => {
      // The pages keep their placeholder copy.
    });
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

function snapshot(): SiteContent {
  return state;
}

export function useSiteContent(): SiteContent {
  loadSiteContent();

  return useSyncExternalStore(subscribe, snapshot, snapshot);
}

export function useSiteImages(): SiteImageSet {
  return useSiteContent().images;
}

export function useSalonInfo(): SalonInfo | null {
  return useSiteContent().salon;
}
