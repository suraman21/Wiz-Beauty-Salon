/**
 * The booking terms, fetched from the panel.
 *
 * The terms page used to be shipped inside the site bundle, which meant a
 * wording change needed a deploy. Now the document lives in the admin panel and
 * arrives from `GET /api/v1/terms`; this module is the only place the site
 * reads it.
 *
 * Two rules matter here, and both come from the page this feeds:
 *
 * 1. The reader must never see a gap. If the request fails, is slow, or the
 *    panel has published nothing yet, the page keeps the copy that shipped with
 *    it — the bundled document is the fallback, not a placeholder.
 * 2. The live parts stay live. The change window and the salon's own summary
 *    sentence are filled in by the server before they are sent, so a clause
 *    saying "24 hours" can never contradict a booking rule set to 48.
 */

import { useSyncExternalStore } from "react";
import { apiGet } from "@/lib/api";

export type TermsClause = { en: string; am: string };

export type TermsSection = {
  /** Anchor on the page — stable across rewordings, so `#window` keeps working. */
  id: string;
  title: TermsClause;
  clauses: TermsClause[];
};

export type TermsDocument = {
  /** `YYYY-MM-DD`, or null when nothing has been published. */
  updatedOn: string | null;
  sections: TermsSection[];
};

type Payload = {
  published?: boolean;
  updated_on?: string | null;
  sections?: Array<{
    id?: string;
    title?: { en?: string; am?: string };
    clauses?: Array<{ en?: string; am?: string }>;
  }>;
};

const EMPTY: TermsDocument = { updatedOn: null, sections: [] };

/** Shape the API's answer into something a component can render, defensively. */
export function normaliseTerms(payload: Payload | null): TermsDocument {
  if (payload === null || payload.published !== true) return EMPTY;

  const sections: TermsSection[] = [];

  for (const section of payload.sections ?? []) {
    const en = typeof section.title?.en === "string" ? section.title.en.trim() : "";
    const am = typeof section.title?.am === "string" ? section.title.am.trim() : "";
    const id = typeof section.id === "string" ? section.id.trim() : "";

    // A heading with no anchor cannot be linked, and a heading in one language
    // only would show a blank in the other. Skip rather than render either.
    if (id === "" || en === "" || am === "") continue;

    const clauses: TermsClause[] = [];

    for (const clause of section.clauses ?? []) {
      const clauseEn = typeof clause.en === "string" ? clause.en.trim() : "";
      const clauseAm = typeof clause.am === "string" ? clause.am.trim() : "";

      if (clauseEn === "" || clauseAm === "") continue;

      clauses.push({ en: clauseEn, am: clauseAm });
    }

    // A section with no clauses is a heading over nothing.
    if (clauses.length === 0) continue;

    sections.push({ id, title: { en, am }, clauses });
  }

  if (sections.length === 0) return EMPTY;

  return {
    updatedOn: typeof payload.updated_on === "string" ? payload.updated_on : null,
    sections,
  };
}

export function fetchTerms(signal?: AbortSignal): Promise<TermsDocument> {
  return apiGet<Payload>("/api/v1/terms", signal).then(normaliseTerms);
}

// ---------------------------------------------------------------------------
// One store, so the page and any future reader share a single request.
// ---------------------------------------------------------------------------

let document: TermsDocument | null = null;
let started = false;

const listeners = new Set<() => void>();

function publish(next: TermsDocument | null): void {
  document = next;

  for (const listener of listeners) listener();
}

/** Fetch once per module instance; a failure leaves the bundled copy in charge. */
export function loadTerms(): void {
  if (started || typeof window === "undefined") return;

  started = true;

  fetchTerms()
    .then((next) => {
      // An unpublished or empty document keeps the page on its bundled copy.
      if (next.sections.length > 0) publish(next);
    })
    .catch(() => {
      // Keep what the page shipped with.
    });
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

function snapshot(): TermsDocument | null {
  return document;
}

/**
 * The published document, or null while the request is in flight or if it never
 * arrived. Null is the signal to fall back, not an error to show.
 */
export function useTermsDocument(): TermsDocument | null {
  loadTerms();

  return useSyncExternalStore(subscribe, snapshot, snapshot);
}

/** Format an API date for the "last updated" line, in the reader's language. */
export function formatUpdatedOn(iso: string | null, lang: "en" | "am"): string {
  if (iso === null || iso === "") return "";

  const date = new Date(`${iso}T00:00:00`);

  if (Number.isNaN(date.getTime())) return "";

  return new Intl.DateTimeFormat(lang === "am" ? "am-ET" : "en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date);
}
