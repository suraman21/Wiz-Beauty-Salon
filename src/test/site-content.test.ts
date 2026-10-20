import { describe, expect, it } from "vitest";
import { bundledImages, mergeImages } from "@/lib/site-content";

describe("site images from the panel", () => {
  it("keeps the bundled picture for every slot the panel has not filled", () => {
    const merged = mergeImages({ images: { hero: { url: "/uploads/abc.jpg", alt: "New hero" } } });

    expect(merged.hero).toEqual({ url: "/uploads/abc.jpg", alt: "New hero" });
    expect(merged.before).toEqual(bundledImages.before);
    expect(merged.after).toEqual(bundledImages.after);
  });

  it("falls back to the bundled description when the panel left it blank", () => {
    const merged = mergeImages({ images: { space: { url: "/uploads/room.jpg", alt: "" } } });

    expect(merged.space.url).toBe("/uploads/room.jpg");
    expect(merged.space.alt).toBe(bundledImages.space.alt);
  });

  it("ignores an empty slot rather than blanking the page", () => {
    const merged = mergeImages({ images: { hero: { url: "", alt: "Gone" } } });

    expect(merged.hero).toEqual(bundledImages.hero);
  });

  it("survives an API that answered with nothing", () => {
    expect(mergeImages(null)).toEqual(bundledImages);
    expect(mergeImages({})).toEqual(bundledImages);
  });
});
