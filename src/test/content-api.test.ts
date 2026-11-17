import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api";
import { fetchGallery, fetchReviews, homeMoodPhotos } from "@/lib/content-api";

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("content API client", () => {
  it("reads published photos from /api/v1/gallery", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        photos: [
          { id: 3, title: "Silk press", caption: "Straight, glossy", url: "/uploads/a1b2c3.png" },
        ],
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const photos = await fetchGallery();

    expect(photos).toHaveLength(1);
    expect(photos[0]?.url).toBe("/uploads/a1b2c3.png");
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/v1/gallery");
  });

  it("reads approved reviews from /api/v1/reviews", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({
        reviews: [{ id: 8, name: "Marta", rating: 5, body: "Lovely", service: "Wave", date: null }],
      }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const reviews = await fetchReviews();

    expect(reviews[0]?.rating).toBe(5);
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/v1/reviews");
  });

  it("surfaces a server error so the page can fall back", async () => {
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockImplementation(() =>
          Promise.resolve(jsonResponse({ error: "Server is unwell." }, 500)),
        ),
    );

    await expect(fetchGallery()).rejects.toBeInstanceOf(ApiError);
    await expect(fetchGallery()).rejects.toThrow("Server is unwell.");
  });
});

describe("home mood photos", () => {
  const photo = (id: number, show: boolean, title = "") => ({
    id,
    title,
    caption: "",
    url: `/uploads/p${id}.jpg`,
    category_id: null,
    category: null,
    category_slug: null,
    show_on_home: show,
  });

  const fallback = [
    { url: "/fallback/a.jpg", alt: "A" },
    { url: "/fallback/b.jpg", alt: "B" },
    { url: "/fallback/c.jpg", alt: "C" },
  ];

  it("leads with the photos staff ticked, in gallery order", () => {
    const mood = homeMoodPhotos(
      [photo(1, false), photo(2, true, "Silk"), photo(3, true), photo(4, true)],
      fallback,
    );

    expect(mood.map((m) => m.url)).toEqual([
      "/uploads/p2.jpg",
      "/uploads/p3.jpg",
      "/uploads/p4.jpg",
    ]);
    expect(mood[0]?.alt).toBe("Silk");
  });

  it("fills any shortfall from the website image slots", () => {
    const mood = homeMoodPhotos([photo(7, true, "Bridal")], fallback);

    expect(mood.map((m) => m.url)).toEqual([
      "/uploads/p7.jpg",
      "/fallback/a.jpg",
      "/fallback/b.jpg",
    ]);
  });

  it("uses the fallback set when nothing is ticked", () => {
    expect(homeMoodPhotos([photo(1, false)], fallback)).toEqual(fallback);
  });
});
