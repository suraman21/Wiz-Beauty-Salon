import { act, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { SmartImage } from "@/components/site/SmartImage";

/**
 * The contract this component exists for: on a bad connection a picture must
 * never become a broken-file icon, and it must keep trying by itself.
 */
describe("SmartImage", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("asks for the picture first, and marks it loading", () => {
    render(<SmartImage src="/photo.jpg" alt="Hair portrait" />);

    const img = screen.getByAltText("Hair portrait");

    expect(img.getAttribute("src")).toBe("/photo.jpg");
    expect(img.className).toContain("is-loading");
  });

  it("fades to loaded when the picture arrives", () => {
    render(<SmartImage src="/photo.jpg" alt="Hair portrait" />);

    const img = screen.getByAltText("Hair portrait");

    act(() => {
      img.dispatchEvent(new Event("load"));
    });

    expect(img.className).toContain("is-loaded");
    // the picture stays the picture: a load must not swap in the stand-in
    expect(img.getAttribute("src")).toBe("/photo.jpg");
  });

  it("draws a stand-in the moment the picture fails, never a broken icon", () => {
    render(<SmartImage src="/photo.jpg" alt="Hair portrait" />);

    const img = screen.getByAltText("Hair portrait");

    act(() => {
      img.dispatchEvent(new Event("error"));
    });

    expect(img.className).toContain("is-retrying");
    // a real image, drawn inline, so nothing can render as a broken file
    expect(img.getAttribute("src")).toMatch(/^data:image\/svg\+xml/);
    expect(decodeURIComponent(img.getAttribute("src") ?? "")).toContain("HP");
  });

  /**
   * A probe that records what it was asked for and answers however the test
   * wants. jsdom never loads an image, so the retries need a stand-in of their
   * own — which is also the only way to make the third attempt succeed.
   */
  function stubProbe(answer: (attempt: string, index: number) => "load" | "error") {
    const asked: string[] = [];

    class FakeImage {
      onload: (() => void) | null = null;
      onerror: (() => void) | null = null;

      set src(value: string) {
        asked.push(value);
        const verdict = answer(value, asked.length - 1);

        setTimeout(() => {
          if (verdict === "load") this.onload?.();
          else this.onerror?.();
        }, 0);
      }

      get src() {
        return asked.at(-1) ?? "";
      }
    }

    vi.stubGlobal("Image", FakeImage as unknown as typeof Image);

    return asked;
  }

  it("retries by itself, against a cache-busted URL", async () => {
    const asked = stubProbe(() => "error");

    render(<SmartImage src="/photo.jpg" alt="Hair portrait" />);

    act(() => {
      screen.getByAltText("Hair portrait").dispatchEvent(new Event("error"));
    });

    await act(async () => {
      vi.advanceTimersByTime(1000);
      await Promise.resolve();
    });

    expect(asked).toContain("/photo.jpg?retry=1");
  });

  it("puts the picture back when a later attempt succeeds", async () => {
    // the first probe fails, the second one brings the photograph
    stubProbe((_url, index) => (index === 0 ? "error" : "load"));

    render(<SmartImage src="/photo.jpg" alt="Hair portrait" />);

    const img = screen.getByAltText("Hair portrait");

    act(() => {
      img.dispatchEvent(new Event("error"));
    });

    for (let i = 0; i < 4; i += 1) {
      await act(async () => {
        vi.advanceTimersByTime(5000);
        await Promise.resolve();
      });
    }

    expect(img.className).toContain("is-loaded");
    expect(img.getAttribute("src")).toBe("/photo.jpg?retry=2");
  });

  it("settles on the stand-in after the last attempt, and stops asking", async () => {
    const asked = stubProbe(() => "error");

    render(<SmartImage src="/photo.jpg" alt="Hair portrait" retries={2} retryDelay={10} />);

    const img = screen.getByAltText("Hair portrait");

    act(() => {
      img.dispatchEvent(new Event("error"));
    });

    for (let i = 0; i < 6; i += 1) {
      await act(async () => {
        vi.advanceTimersByTime(1000);
        await Promise.resolve();
      });
    }

    expect(img.className).toContain("is-missing");
    expect(asked).toHaveLength(2);
    expect(img.getAttribute("src")).toMatch(/^data:image\/svg\+xml/);
  });

  it("shows the stand-in straight away when there is no source at all", () => {
    render(<SmartImage src={null} alt="Nails detail" />);

    const img = screen.getByAltText("Nails detail");

    expect(img.className).toContain("is-missing");
    expect(img.getAttribute("src")).toMatch(/^data:image\/svg\+xml/);
  });
});
