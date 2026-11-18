import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

/**
 * The terms page reads the panel, and survives it being away.
 *
 * The page itself is a long document; what matters here is the single decision
 * it makes at the top — use the published wording, or keep the copy that
 * shipped with the site. Both halves are tested, because a regression in either
 * one is invisible until a customer sees a blank legal page or a stale one.
 */

const published = {
  published: true,
  updated_on: "2026-11-17",
  sections: [
    {
      id: "window",
      title: { en: "Changing your booking", am: "ቀጠሮ መቀየር" },
      clauses: [{ en: "Free until 24 hours before.", am: "እስከ 24 ሰዓት በፊት ነፃ።" }],
    },
  ],
};

function fakeResponse(body: unknown, ok = true) {
  return {
    ok,
    status: ok ? 200 : 500,
    json: async () => body,
  } as unknown as Response;
}

/** A page-shaped stand-in: it renders whichever document the store gives it. */
function Harness({ useTermsDocument }: { useTermsDocument: () => unknown }) {
  const document = useTermsDocument() as { sections: { title: { en: string } }[] } | null;

  return (
    <p data-testid="heading">
      {document === null ? "bundled copy" : (document.sections[0]?.title.en ?? "bundled copy")}
    </p>
  );
}

afterEach(() => {
  vi.unstubAllGlobals();
  vi.resetModules();
});

describe("the booking terms page", () => {
  it("renders the wording the salon published in the panel", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => fakeResponse(published)),
    );

    const { useTermsDocument } = await import("@/lib/terms-api");

    render(<Harness useTermsDocument={useTermsDocument} />);

    expect(screen.getByTestId("heading").textContent).toBe("bundled copy");

    await waitFor(() => {
      expect(screen.getByTestId("heading").textContent).toBe("Changing your booking");
    });
  });

  it("keeps its own copy when the API is unreachable", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => {
        throw new TypeError("Failed to fetch");
      }),
    );

    const { useTermsDocument } = await import("@/lib/terms-api");

    render(<Harness useTermsDocument={useTermsDocument} />);

    // Give the rejected request a chance to land; the page must not change.
    await new Promise((resolve) => setTimeout(resolve, 20));

    expect(screen.getByTestId("heading").textContent).toBe("bundled copy");
  });

  it("keeps its own copy when the panel has published nothing", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => fakeResponse({ published: false, updated_on: null, sections: [] })),
    );

    const { useTermsDocument } = await import("@/lib/terms-api");

    render(<Harness useTermsDocument={useTermsDocument} />);

    await new Promise((resolve) => setTimeout(resolve, 20));

    expect(screen.getByTestId("heading").textContent).toBe("bundled copy");
  });
});
