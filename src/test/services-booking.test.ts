import { describe, expect, it } from "vitest";
import { bookingDetailsSchema, serviceCategories } from "@/data/services";

const expected = [
  ["Make Up", "ሜክ አፕ"],
  ["Nail", "ጥፍር"],
  ["Pedicure", "ፔዲኪውር"],
  ["Wax", "ዋክስ"],
  ["Eye Lash", "ቅንድብ"],
  ["Mesh", "ሜሽ"],
  ["Hair Treatment", "የጸጉር እንክብካቤ"],
  ["Hair Cut", "የጸጉር ቁርጥ"],
  ["Hair Paint", "የጸጉር ቀለም"],
  ["Hair Styling", "የጸጉር ስታይል"],
  ["Braids", "ሹሩባ"],
  ["Sewing", "ስፌት"],
  ["Ponytail", "ፖኒቴል"],
  ["Kids Braids", "የህጻናት ሹሩባ"],
];

describe("the salon's service list", () => {
  it("keeps the salon's own one-level list of services", () => {
    expect(serviceCategories.map((c) => [c.name, c.amharic])).toEqual(expected);
  });

  it("keeps pedicure separate from nail", () => {
    expect(serviceCategories.find((c) => c.id === "pedicure")?.items.map((i) => i.name)).toEqual([
      "Special Pedicure",
      "Pedicure",
    ]);
    expect(
      serviceCategories
        .find((c) => c.id === "nail")
        ?.items.some((i) => i.name.includes("Pedicure")),
    ).toBe(false);
  });

  it("keeps the supplied kids' braiding options", () => {
    expect(serviceCategories.find((c) => c.id === "kids-braids")?.items.map((i) => i.name)).toEqual(
      ["Shuruba", "Kutirtir", "Chele"],
    );
  });

  it("keeps ambiguous supplied terms without inventing meanings", () => {
    expect(serviceCategories.map((c) => c.name)).toEqual(
      expect.arrayContaining(["Mesh", "Braids", "Sewing"]),
    );
    expect(serviceCategories.find((c) => c.id === "sewing")?.items.map((i) => i.name)).toEqual(
      expect.arrayContaining(["Syntthetic"]),
    );
  });

  it("has no obsolete skin services or demo choices", () => {
    expect(
      serviceCategories.some((c) =>
        /facial|skin|glow|everyday look|occasion makeup|bridal makeup/i.test(c.name),
      ),
    ).toBe(false);
  });

  it("does not invent prices or durations", () => {
    expect(serviceCategories.some((c) => "price" in c || "duration" in c)).toBe(false);
  });

  it("has unique service identifiers", () => {
    expect(new Set(serviceCategories.map((c) => c.id)).size).toBe(serviceCategories.length);
  });
});

describe("Booking request rules", () => {
  const details = { name: "Customer", phone: "0911000000", email: "", notes: "" };

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
    expect(
      bookingDetailsSchema.safeParse({ ...details, email: "customer@example.com" }).success,
    ).toBe(true);
  });
});
