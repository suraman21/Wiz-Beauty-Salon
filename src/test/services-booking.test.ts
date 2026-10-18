import { describe, expect, it } from "vitest";
import { serviceCategories, bookableServices, filterServiceCategories, findBookableService, isValidServiceSelection, bookingDetailsSchema, UNDECIDED_SERVICE } from "@/data/services";

const expected = [
  ["Make Up", "ሜክ አፕ"], ["Nail", "ጥፍር"], ["Pedicure", "ፔዲኪውር"], ["Wax", "ዋክስ"],
  ["Eye Lash", "ቅንድብ"], ["Mesh", "ሜሽ"], ["Hair Treatment", "የጸጉር እንክብካቤ"],
  ["Hair Cut", "የጸጉር ቁርጥ"], ["Hair Paint", "የጸጉር ቀለም"], ["Hair Styling", "የጸጉር ስታይል"],
  ["Braids", "ሹሩባ"], ["Sewing", "ስፌት"], ["Ponytail", "ፖኒቴል"], ["Kids Braids", "የህጻናት ሹሩባ"],
];
describe("WIZ service catalog", () => {
  it("uses exactly the 14 supplied bilingual categories", () => {
    expect(serviceCategories.map((c) => [c.name, c.amharic])).toEqual(expected);
  });
  it("All restores all 14 categories", () => {
    expect(filterServiceCategories("all")).toHaveLength(14);
  });
  for (const c of serviceCategories) {
    it(`filters only ${c.name} and resolves its exact booking options`, () => {
      expect(filterServiceCategories(c.id).map((x) => x.id)).toEqual([c.id]);
      for (const item of c.items) expect(findBookableService(item.id)).toEqual({ ...item, categoryId: c.id, category: c.name, amharic: c.amharic });
    });
  }
  it("keeps pedicure separate from nail", () => {
    expect(serviceCategories.find((c) => c.id === "pedicure")?.items.map((i) => i.name)).toEqual(["Special Pedicure", "Pedicure"]);
    expect(serviceCategories.find((c) => c.id === "nail")?.items.some((i) => i.name.includes("Pedicure"))).toBe(false);
  });
  it("keeps the supplied kids' braiding options", () => {
    expect(serviceCategories.find((c) => c.id === "kids-braids")?.items.map((i) => i.name)).toEqual(["Shuruba", "Kutirtir", "Chele"]);
  });
  it("keeps ambiguous supplied terms without inventing meanings", () => {
    expect(bookableServices.map((s) => s.name)).toEqual(expect.arrayContaining(["Mesh", "Casc", "Publice", "Payestra", "Syntthetic"]));
  });
  it("excludes obsolete skin services and demo choices", () => {
    expect(bookableServices.some((s) => /facial|skin|glow|everyday look|occasion makeup|bridal makeup/i.test(s.name))).toBe(false);
    expect(findBookableService("skin-facial")).toBeUndefined();
    expect(findBookableService("hair-cut")).toBeUndefined();
  });
  it("does not invent prices or durations", () => {
    expect(bookableServices.some((s) => "price" in s || "duration" in s)).toBe(false);
  });
  it("has unique service identifiers", () => {
    expect(new Set(bookableServices.map((s) => s.id)).size).toBe(bookableServices.length);
  });
});
describe("Booking request rules", () => {
  const details = { name: "Customer", phone: "0911000000", email: "", notes: "" };
  it("allows an undecided request without category or subservice", () => {
    expect(isValidServiceSelection(UNDECIDED_SERVICE)).toBe(true);
    expect(findBookableService(UNDECIDED_SERVICE)).toBeUndefined();
  });
  it("otherwise requires a valid service", () => {
    expect(isValidServiceSelection("")).toBe(false);
    expect(isValidServiceSelection("skin-facial")).toBe(false);
    expect(isValidServiceSelection("nail-1")).toBe(true);
  });
  it("requires a customer name", () => {
    expect(bookingDetailsSchema.safeParse({ ...details, name: " " }).success).toBe(false);
  });
  it("requires a phone", () => {
    expect(bookingDetailsSchema.safeParse({ ...details, phone: " " }).success).toBe(false);
  });
  it("allows no email", () => {
    expect(bookingDetailsSchema.safeParse(details).success).toBe(true);
  });
  it("validates an email when supplied", () => {
    expect(bookingDetailsSchema.safeParse({ ...details, email: "invalid" }).success).toBe(false);
    expect(bookingDetailsSchema.safeParse({ ...details, email: "customer@example.com" }).success).toBe(true);
  });
});