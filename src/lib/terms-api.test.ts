import { describe, expect, it } from "vitest";
import { formatUpdatedOn, normaliseTerms } from "@/lib/terms-api";

/**
 * The terms page must never show a half-published document.
 *
 * The panel can, for a moment, hold a clause that is complete in English and
 * unfinished in Amharic — the server refuses to save one, but the API is still
 * the boundary this page trusts. These tests pin the behaviour that matters: a
 * gap is dropped rather than printed, and an empty answer means "use the
 * bundled copy", never "show no terms".
 */
describe("normaliseTerms", () => {
  it("treats an unpublished document as no document", () => {
    expect(normaliseTerms(null)).toEqual({ updatedOn: null, sections: [] });
    expect(normaliseTerms({ published: false, sections: [] })).toEqual({
      updatedOn: null,
      sections: [],
    });
  });

  it("keeps the order the salon arranged", () => {
    const document = normaliseTerms({
      published: true,
      updated_on: "2026-11-17",
      sections: [
        {
          id: "window",
          title: { en: "Changing your booking", am: "ቀጠሮ መቀየር" },
          clauses: [{ en: "Free inside the window.", am: "በወቅቱ ውስጥ ነፃ ነው።" }],
        },
        {
          id: "law",
          title: { en: "Governing law", am: "የሚመራው ሕግ" },
          clauses: [{ en: "Ethiopian law applies.", am: "የኢትዮጵያ ሕግ ይመራል።" }],
        },
      ],
    });

    expect(document.updatedOn).toBe("2026-11-17");
    expect(document.sections.map((section) => section.id)).toEqual(["window", "law"]);
    expect(document.sections[0]?.title.en).toBe("Changing your booking");
  });

  it("drops a clause that is only written in one language", () => {
    const document = normaliseTerms({
      published: true,
      sections: [
        {
          id: "late",
          title: { en: "Late arrivals", am: "ዘግይቶ መምጣት" },
          clauses: [
            { en: "Tell us you are late.", am: "ዘግይተዋል ብለው ያሳውቁን።" },
            { en: "Half-written English only.", am: "   " },
          ],
        },
      ],
    });

    expect(document.sections[0]?.clauses).toHaveLength(1);
    expect(document.sections[0]?.clauses[0]?.am).toBe("ዘግይተዋል ብለው ያሳውቁን።");
  });

  it("drops a heading with no anchor, and a section with no clauses", () => {
    const document = normaliseTerms({
      published: true,
      sections: [
        { id: "", title: { en: "No anchor", am: "መግቢያ የለውም" }, clauses: [{ en: "a", am: "ሀ" }] },
        { id: "empty", title: { en: "Nothing under it", am: "ምንም የለውም" }, clauses: [] },
      ],
    });

    expect(document.sections).toEqual([]);
  });

  it("treats an answer with no usable section as no answer", () => {
    expect(normaliseTerms({ published: true, sections: [] })).toEqual({
      updatedOn: null,
      sections: [],
    });
  });
});

describe("formatUpdatedOn", () => {
  it("writes the date out in the reader's language", () => {
    expect(formatUpdatedOn("2026-11-17", "en")).toBe("17 November 2026");
    expect(formatUpdatedOn("2026-11-17", "am")).not.toBe("");
    expect(formatUpdatedOn("2026-11-17", "am")).not.toBe(formatUpdatedOn("2026-11-17", "en"));
  });

  it("has nothing to say about a missing or broken date", () => {
    expect(formatUpdatedOn(null, "en")).toBe("");
    expect(formatUpdatedOn("", "en")).toBe("");
    expect(formatUpdatedOn("not-a-date", "en")).toBe("");
  });
});
