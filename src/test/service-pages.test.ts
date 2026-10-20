import { describe, expect, it } from "vitest";
import {
  PRICE_UNDISCLOSED,
  buildServicePages,
  formatPrice,
  hasUnpricedItems,
  localServicePages,
  taglineFor,
} from "@/lib/service-pages";
import type { ServiceCatalogue } from "@/lib/booking-api";

const catalogue: ServiceCatalogue = {
  categories: [
    {
      id: 4,
      name: "Braids",
      slug: "braids",
      amharic: "ጋሻ",
      description: "",
      sort_order: 0,
      image_url: null,
    },
    {
      id: 2,
      name: "Nail",
      slug: "nail",
      amharic: "ጥፍር",
      description: "Hands and feet",
      sort_order: 10,
      image_url: null,
    },
  ],
  services: [
    {
      id: 21,
      name: "Box braids",
      category: "Braids",
      slug: "braids-box-braids",
      description: "",
      duration_minutes: 120,
      price_cents: 250000,
      featured: false,
      image_url: null,
    },
    {
      id: 7,
      name: "Shellac",
      category: "Nail",
      slug: "nail-shellac",
      description: "",
      duration_minutes: 45,
      price_cents: null,
      featured: true,
      image_url: null,
    },
    {
      id: 8,
      name: "Manicure",
      category: "Nail",
      slug: "nail-manicure",
      description: "",
      duration_minutes: 45,
      price_cents: 120050,
      featured: false,
      image_url: null,
    },
  ],
};

describe("service pages", () => {
  it("keeps the panel's category order, names and Amharic", () => {
    const pages = buildServicePages(catalogue);

    expect(pages.map((page) => page.id)).toEqual(["braids", "nail"]);
    expect(pages[0]?.name).toBe("Braids");
    expect(pages[0]?.amharic).toBe("ጋሻ");
    expect(pages[1]?.description).toBe("Hands and feet");
  });

  it("links each service to its database id and shows its price", () => {
    const pages = buildServicePages(catalogue);
    const nail = pages.find((page) => page.id === "nail");

    expect(nail?.items).toEqual([
      { id: "7", slug: "nail-shellac", name: "Shellac", price: "", image: null },
      { id: "8", slug: "nail-manicure", name: "Manicure", price: "ETB 1,200.50", image: null },
    ]);
    expect(hasUnpricedItems(pages)).toBe(true);
  });

  it("formats whole prices without decimals and hides absent ones", () => {
    expect(formatPrice(250000)).toBe("ETB 2,500");
    expect(formatPrice(120050)).toBe("ETB 1,200.50");
    expect(formatPrice(null)).toBe("");
    expect(formatPrice(0)).toBe("");
  });

  it("keeps an orphaned service visible under its own heading", () => {
    const pages = buildServicePages({
      categories: [],
      services: [
        {
          id: 3,
          name: "Wash and blow-dry",
          category: "Other",
          slug: "wash-blow-dry",
          description: "",
          duration_minutes: 60,
          price_cents: null,
          featured: false,
          image_url: null,
        },
      ],
    });

    expect(pages).toHaveLength(1);
    expect(pages[0]?.name).toBe("Other");
    expect(pages[0]?.items[0]?.id).toBe("3");
  });

  it("returns nothing when the salon has hidden everything", () => {
    expect(buildServicePages({ categories: [], services: [] })).toEqual([]);
  });

  it("falls back to the local catalogue when the API has nothing", () => {
    const pages = localServicePages();

    expect(pages.length).toBeGreaterThan(0);
    expect(pages[0]?.items.every((item) => item.price === "")).toBe(true);
    expect(pages[0]?.items[0]?.id).toMatch(/^[a-z-]+-\d+$/);
    expect(pages[0]?.items[0]?.slug).toBe(pages[0]?.items[0]?.id);
    expect(taglineFor(pages[0]!)).toMatch(/^\d+ services?$/);
  });

  it("uses the placeholder wording the page prints", () => {
    expect(PRICE_UNDISCLOSED).toBe("Price · to be confirmed");
    expect(taglineFor(buildServicePages(catalogue)[1]!)).toBe("Hands and feet");
  });
});

describe("category and service pictures", () => {
  it("prefers the picture staff chose in the panel", () => {
    const pages = buildServicePages({
      categories: [
        {
          id: 1,
          name: "Nail",
          slug: "nail",
          amharic: "ጥፍር",
          description: "",
          sort_order: 0,
          image_url: "/uploads/category.jpg",
        },
      ],
      services: [
        {
          id: 5,
          name: "Shellac",
          slug: "nail-shellac",
          category: "Nail",
          description: "",
          duration_minutes: 45,
          price_cents: null,
          featured: false,
          image_url: "/uploads/service.jpg",
        },
      ],
    });

    expect(pages[0]?.image).toBe("/uploads/category.jpg");
    expect(pages[0]?.fromPanel).toBe(true);
    expect(pages[0]?.items[0]?.image).toBe("/uploads/service.jpg");
  });

  it("keeps the bundled illustration when the panel has no picture", () => {
    const pages = buildServicePages({
      categories: [],
      services: [
        {
          id: 9,
          name: "Manicure",
          slug: "nail-manicure",
          category: "Nail",
          description: "",
          duration_minutes: 45,
          price_cents: null,
          featured: false,
          image_url: null,
        },
      ],
    });

    expect(pages[0]?.fromPanel).toBe(false);
    expect(pages[0]?.image).not.toBe("");
    expect(pages[0]?.items[0]?.image).toBeNull();
  });
});
