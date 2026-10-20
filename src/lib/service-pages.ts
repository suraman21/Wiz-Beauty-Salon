/**
 * Turns the panel's catalogue into what the Services page and the home page
 * put on screen.
 *
 * The list, the order, the English and Amharic names and the prices all come
 * from `/api/v1/services`, which reads the same tables staff edit in the panel.
 * The local catalogue (`src/data/services.ts`) is still used for two things and
 * nothing else: illustrative images, which the panel does not hold, and a
 * fallback for the first paint and for the worst case where the API is down.
 */

import { images } from "@/data/wiz";
import { serviceCategories } from "@/data/services";
import type { ApiCategory, ServiceCatalogue } from "@/lib/booking-api";

export type ServiceListItem = {
  /** Database id as a string for live services; the local `slug-N` id otherwise. */
  id: string;
  /**
   * What the booking link carries. Slugs are stable, readable, and — unlike a
   * bare number — survive the router's search-string encoding untouched.
   */
  slug: string;
  name: string;
  /** Formatted price, or "" when the salon has not published one. */
  price: string;
  /** The service's own photo when the panel has one. */
  image: string | null;
};

export type ServicePage = {
  id: string;
  name: string;
  amharic: string;
  description: string;
  image: string;
  alt: string;
  /** True when the picture came from the panel rather than the bundle. */
  fromPanel: boolean;
  items: ServiceListItem[];
};

const local = new Map(serviceCategories.map((category) => [category.id as string, category]));

const fallbackImage = {
  image: images.space,
  alt: "Service category",
};

/** Format cents as the salon would say the price out loud. */
export function formatPrice(cents: number | null): string {
  if (cents === null || !Number.isFinite(cents) || cents <= 0) return "";

  const amount = cents / 100;
  const whole = Math.floor(amount);
  const fraction = Math.round((amount - whole) * 100);

  const digits = new Intl.NumberFormat("en-US").format(whole);

  return fraction === 0 ? `ETB ${digits}` : `ETB ${digits}.${String(fraction).padStart(2, "0")}`;
}

function imageFor(slug: string, name: string): { image: string; alt: string } {
  const match =
    local.get(slug) ??
    local.get(
      name
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-"),
    ) ??
    serviceCategories.find((category) => category.name.toLowerCase() === name.trim().toLowerCase());

  return match === undefined ? fallbackImage : { image: match.image, alt: match.alt };
}

function amharicFor(category: ApiCategory | undefined, slug: string, name: string): string {
  const fromPanel = (category?.amharic ?? "").trim();

  if (fromPanel !== "") return fromPanel;

  return local.get(slug)?.amharic ?? local.get(name.trim().toLowerCase())?.amharic ?? "";
}

const key = (value: string) => value.trim().toLowerCase();

/**
 * Categories with at least one visible service, in the panel's order.
 *
 * A category whose services are all hidden is left off the site entirely — an
 * empty heading would only confuse a customer.
 */
export function buildServicePages(catalogue: ServiceCatalogue): ServicePage[] {
  const byName = new Map(catalogue.categories.map((category) => [key(category.name), category]));

  const order = new Map(catalogue.categories.map((category, index) => [category.slug, index]));

  const pages = new Map<string, ServicePage>();

  for (const service of catalogue.services) {
    const category = byName.get(key(service.category));
    // A category the panel does not publish still gets its own heading rather
    // than being pooled into one "Other" bucket with a different category.
    const slug = category?.slug ?? (service.category === "Other" ? "other" : key(service.category));
    const page = pages.get(slug);

    const item: ServiceListItem = {
      id: String(service.id),
      slug: service.slug !== "" ? service.slug : String(service.id),
      name: service.name,
      price: formatPrice(service.price_cents),
      image: service.image_url ?? null,
    };

    if (page !== undefined) {
      page.items.push(item);
      continue;
    }

    const categoryName = category?.name ?? service.category;
    const panelImage = category?.image_url ?? null;
    const own = panelImage !== null && panelImage !== "" ? panelImage : null;
    const illustration = imageFor(slug, categoryName);
    const image = own ?? illustration.image;
    const alt = own === null ? illustration.alt : categoryName;

    pages.set(slug, {
      id: slug,
      name: category?.name ?? service.category,
      amharic: amharicFor(category, slug, service.category),
      description: (category?.description ?? "").trim(),
      image,
      alt,
      fromPanel: own !== null,
      items: [item],
    });
  }

  // A service whose category was deleted in the panel arrives as "Other" and
  // sorts last, so it stays visible instead of dropping off the site.
  return [...pages.entries()]
    .sort(([a], [b]) => {
      const left = order.get(a) ?? Number.MAX_SAFE_INTEGER;
      const right = order.get(b) ?? Number.MAX_SAFE_INTEGER;

      return left === right ? a.localeCompare(b) : left - right;
    })
    .map(([, page]) => page);
}

/** The local catalogue in the shape above, used before the API answers. */
export function localServicePages(): ServicePage[] {
  return serviceCategories.map((category) => ({
    id: category.id,
    name: category.name,
    amharic: category.amharic,
    description: "",
    image: category.image,
    alt: category.alt,
    fromPanel: false,
    items: category.items.map((item) => ({
      id: item.id,
      slug: item.id,
      name: item.name,
      price: "",
      image: null,
    })),
  }));
}

export function hasUnpricedItems(pages: ServicePage[]): boolean {
  return pages.some((page) => page.items.some((item) => item.price === ""));
}

/** The panel's first categories, for the home page teaser. */
export function featuredPages(pages: ServicePage[], limit: number): ServicePage[] {
  return pages.slice(0, limit);
}

export function taglineFor(page: ServicePage): string {
  if (page.description !== "") return page.description;

  const count = page.items.length;

  return `${count} ${count === 1 ? "service" : "services"}`;
}
