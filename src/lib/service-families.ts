/**
 * Groups the panel's categories into the four families the site is organised
 * by. The panel owns the categories and their order; this only decides which
 * heading a category appears under, so a category the panel adds later still
 * lands somewhere sensible instead of vanishing.
 */

import { images } from "@/data/wiz";
import type { ServicePage } from "@/lib/service-pages";

export type FamilyId = "hair" | "nails" | "face" | "kids";

export type ServiceFamily = {
  id: FamilyId;
  name: string;
  amharic: string;
  note: string;
  image: string;
  pages: ServicePage[];
};

type FamilySpec = Omit<ServiceFamily, "pages"> & {
  /** Local slugs, matched first. */
  ids: string[];
  /** Fallback matcher for categories that only exist in the panel. */
  match: RegExp;
};

/**
 * Order matters: "kids-braids" contains "braid", so the kids test must run
 * before the hair test or children end up in the adult hair list.
 */
const SPECS: FamilySpec[] = [
  {
    id: "kids",
    name: "Kids",
    amharic: "ህጻናት",
    note: "Braids for girls, with the patience it takes.",
    image: images.kids,
    ids: ["kids-braids"],
    match: /kid|child|ህጻን/,
  },
  {
    id: "hair",
    name: "Hair",
    amharic: "ፀጉር",
    note: "Braids, sewing, colour, relaxers and styling.",
    image: images.braiding,
    ids: [
      "braids",
      "sewing",
      "ponytail",
      "mesh",
      "hair-cut",
      "hair-paint",
      "hair-styling",
      "hair-treatment",
    ],
    match: /hair|braid|sew|pony|mesh|shuruba|ሹሩባ|ስፌት|ፀጉር|ፖኒ/,
  },
  {
    id: "nails",
    name: "Nails",
    amharic: "ጥፍር",
    note: "Manicure, pedicure, gel and acrylic.",
    image: images.nails,
    ids: ["nail", "pedicure"],
    match: /nail|mani|pedi|ጥፍር|ፔዲ/,
  },
  {
    id: "face",
    name: "Face and body",
    amharic: "ፊት",
    note: "Makeup, brows, lashes and waxing.",
    image: images.makeup,
    ids: ["make-up", "eye-lash", "wax"],
    match: /make|lash|brow|wax|skin|face|ሜክ|ቅንድብ|ዋክስ/,
  },
];

function familyFor(page: ServicePage): FamilyId {
  const id = page.id.toLowerCase();
  const name = page.name.toLowerCase();

  const byId = SPECS.find((spec) => spec.ids.includes(id));

  if (byId !== undefined) return byId.id;

  const combined = `${id} ${name}`;
  // Kids first, then hair, then the rest — the same order as SPECS.
  const byName = SPECS.find((spec) => spec.match.test(combined));

  return byName === undefined ? "hair" : byName.id;
}

/**
 * Families that actually hold something, in a fixed editorial order.
 *
 * Within a family the categories follow `ids` above rather than the panel's
 * order: braids lead the hair list because that is the work this salon is known
 * for, and the technical services follow. Anything the panel adds that is not
 * named here keeps its panel position at the end of its family.
 */
export function groupIntoFamilies(pages: ServicePage[]): ServiceFamily[] {
  const buckets = new Map<FamilyId, ServicePage[]>();

  for (const spec of SPECS) buckets.set(spec.id, []);

  for (const page of pages) {
    const family = familyFor(page);
    buckets.get(family)?.push(page);
  }

  for (const [familyId, list] of buckets) {
    const spec = SPECS.find((entry) => entry.id === familyId);
    if (spec === undefined) continue;

    const rank = (page: ServicePage) => {
      const index = spec.ids.indexOf(page.id.toLowerCase());
      return index === -1 ? spec.ids.length + list.indexOf(page) : index;
    };

    list.sort((left, right) => rank(left) - rank(right));
  }

  return SPECS.map((spec) => ({
    id: spec.id,
    name: spec.name,
    amharic: spec.amharic,
    note: spec.note,
    image: spec.image,
    pages: buckets.get(spec.id) ?? [],
  })).filter((family) => family.pages.length > 0);
}

/** "12 options", "1 option" — the count that replaces a decorative badge. */
export function optionCount(page: ServicePage): string {
  const count = page.items.length;

  return `${count} ${count === 1 ? "option" : "options"}`;
}
