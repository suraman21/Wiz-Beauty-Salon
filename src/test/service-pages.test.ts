import { describe, expect, it } from "vitest";
import { buildServices, localServices, servicesForHome } from "@/lib/service-pages";
import type { ServiceCatalogue } from "@/lib/booking-api";

const catalogue: ServiceCatalogue = {
  services: [
    {
      id: 4,
      name: "Braids",
      slug: "braids",
      amharic: "ጋሻ",
      description: "Cornrows, knotless, feed-ins",
      image_url: null,
      show_on_home: true,
    },
    {
      id: 2,
      name: "Nails",
      slug: "nail",
      amharic: "ጥፍር",
      description: "",
      image_url: "/uploads/nails.jpg",
      show_on_home: false,
    },
    {
      id: 9,
      name: "Makeup",
      slug: "makeup",
      amharic: "",
      description: "For the day that matters",
      image_url: null,
      show_on_home: true,
    },
  ],
};

describe("the salon's services", () => {
  it("keeps the panel's order, names, Amharic and descriptions", () => {
    const services = buildServices(catalogue);

    expect(services.map((service) => service.id)).toEqual(["braids", "nail", "makeup"]);
    expect(services[0]?.name).toBe("Braids");
    expect(services[0]?.amharic).toBe("ጋሻ");
    expect(services[0]?.description).toBe("Cornrows, knotless, feed-ins");
    expect(services[2]?.amharic).toBe("");
  });

  it("uses the photo staff uploaded, and the bundle's picture otherwise", () => {
    const services = buildServices(catalogue);

    expect(services[1]?.image).toBe("/uploads/nails.jpg");
    expect(services[1]?.fromPanel).toBe(true);
    expect(services[0]?.fromPanel).toBe(false);
    expect(services[0]?.image).not.toBe("");
  });

  it("carries no price and no running time", () => {
    const service = buildServices(catalogue)[0];

    expect(service).not.toHaveProperty("price");
    expect(service).not.toHaveProperty("duration");
  });
});

describe("the home page's four services", () => {
  it("leads with the services staff ticked in the panel", () => {
    const home = servicesForHome(buildServices(catalogue), 4);

    expect(home.slice(0, 2).map((service) => service.id)).toEqual(["braids", "makeup"]);
    expect(home).toHaveLength(3);
  });

  it("fills the section with the next services when fewer are ticked", () => {
    const only = buildServices({
      services: [
        { ...catalogue.services[0]!, show_on_home: false },
        { ...catalogue.services[1]!, show_on_home: true },
        { ...catalogue.services[2]!, show_on_home: false },
      ],
    });

    expect(servicesForHome(only, 4).map((service) => service.id)).toEqual([
      "nail",
      "braids",
      "makeup",
    ]);
  });

  it("falls back to the panel's order when nothing is ticked", () => {
    const none = buildServices({
      services: catalogue.services.map((service) => ({ ...service, show_on_home: false })),
    });

    expect(servicesForHome(none, 4).map((service) => service.id)).toEqual([
      "braids",
      "nail",
      "makeup",
    ]);
  });

  it("never shows more than the section holds", () => {
    expect(servicesForHome(localServices(), 4)).toHaveLength(4);
  });
});

describe("the bundled catalogue", () => {
  it("has names, pictures and the first four on the home page", () => {
    const services = localServices();

    expect(services.length).toBeGreaterThan(3);
    expect(services[0]?.name).toBe("Make Up");
    expect(services[0]?.image).not.toBe("");
    expect(services.slice(0, 4).every((service) => service.showOnHome)).toBe(true);
    expect(services[4]?.showOnHome).toBe(false);
  });
});
