/**
 * The salon's services, as the website shows them.
 *
 * A service is one row staff manage in the panel — Hair, Nails, Makeup, Skin —
 * with a name, an optional Amharic name, a description and a photo. What
 * happens inside a service, how long it takes and what it costs are settled
 * with the customer at the salon, so the site never prints a price list or a
 * running time: it lists the services and takes the booking.
 *
 * `/api/v1/services` is the only source once it answers. The bundled catalogue
 * (`src/data/services.ts`) is the fallback for the first paint and for the case
 * where the API is down.
 */

import { serviceCategories } from "@/data/services";
import type { ApiService, ServiceCatalogue } from "@/lib/booking-api";

export type SalonService = {
  /** Slug: the anchor on the services page and what the booking link carries. */
  id: string;
  name: string;
  amharic: string;
  description: string;
  image: string;
  alt: string;
  /** True when the picture came from the panel rather than the bundle. */
  fromPanel: boolean;
  /** True when staff put this service on the home page in the panel. */
  showOnHome: boolean;
};

const fallbackImage = {
  image: "",
  alt: "Service at WIZ Beauty Salon",
};

function imageFor(slug: string, name: string): { image: string; alt: string } {
  const key = name.trim().toLowerCase();
  const match =
    serviceCategories.find((category) => category.id === slug) ??
    serviceCategories.find((category) => category.name.toLowerCase() === key);

  return match === undefined ? fallbackImage : { image: match.image, alt: match.alt };
}

/** The panel's services, in the order staff arranged them. */
export function buildServices(catalogue: ServiceCatalogue): SalonService[] {
  return catalogue.services.map((service: ApiService) => {
    const slug = service.slug !== "" ? service.slug : String(service.id);
    const illustration = imageFor(slug, service.name);
    const own = service.image_url ?? null;

    return {
      id: slug,
      name: service.name,
      amharic: (service.amharic ?? "").trim(),
      description: (service.description ?? "").trim(),
      image: own ?? illustration.image,
      alt: own === null ? illustration.alt : service.name,
      fromPanel: own !== null,
      showOnHome: service.show_on_home === true,
    };
  });
}

/** The bundled catalogue, in the shape above, used before the API answers. */
export function localServices(): SalonService[] {
  return serviceCategories.map((category, index) => ({
    id: category.id,
    name: category.name,
    amharic: category.amharic,
    description: "",
    image: category.image,
    alt: category.alt,
    fromPanel: false,
    // Until the panel answers, the bundle leads with its first four — the same
    // four the home page always showed.
    showOnHome: index < 4,
  }));
}

/**
 * What the home page's services section shows.
 *
 * Staff tick "Show in 'Our services' on the home page" per service in the
 * panel, and those come first, in the order the arrows give them. Any shortfall
 * is filled with the next services so the section is never half-empty — which
 * also covers a salon that has ticked nothing yet.
 */
export function servicesForHome(services: SalonService[], limit: number): SalonService[] {
  const chosen = services.filter((service) => service.showOnHome);
  const rest = services.filter((service) => !service.showOnHome);

  return [...chosen, ...rest].slice(0, limit);
}
