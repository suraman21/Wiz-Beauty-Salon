import { z } from "zod";
import { images } from "@/data/wiz";

// Category names are from WIZ's revised category sheet and explicit brief.
// Options are from its older receiving form; photos remain illustrative.
export type ServiceCategoryId =
  | "make-up"
  | "nail"
  | "pedicure"
  | "wax"
  | "eye-lash"
  | "mesh"
  | "hair-treatment"
  | "hair-cut"
  | "hair-paint"
  | "hair-styling"
  | "braids"
  | "sewing"
  | "ponytail"
  | "kids-braids";
type ServiceCategory = {
  id: ServiceCategoryId;
  name: string;
  amharic: string;
  image: string;
  alt: string;
  items: { id: string; name: string }[];
};
const category = (
  id: ServiceCategoryId,
  name: string,
  amharic: string,
  image: string,
  options: string[],
): ServiceCategory => ({
  id,
  name,
  amharic,
  image,
  alt: `${name} at WIZ Beauty Salon`,
  items: options.map((option, i) => ({ id: `${id}-${i + 1}`, name: option })),
});

export const serviceCategories = [
  category("make-up", "Make Up", "ሜክ አፕ", images.makeup, ["Make Up"]),
  category("nail", "Nail", "ጥፍር", images.nails, [
    "Special Manicure",
    "Manicure",
    "Shellac",
    "Shellac Off",
    "Gel",
    "Gel Off",
    "Refill",
    "Acrylic Extension",
    "Normal Polish",
    "Other",
  ]),
  category("pedicure", "Pedicure", "ፔዲኪውር", images.nails, ["Special Pedicure", "Pedicure"]),
  category("wax", "Wax", "ዋክስ", images.space, [
    "Full Face",
    "Eyebrows",
    "Upper Lip",
    "Chin",
    "Full Arm",
    "Half Arm",
    "Under Arm",
    "Full Leg",
    "Half Leg",
    "Full Body",
    "Chest",
    "Abdomen",
    "Brazilian",
    "Bikini",
    "Back",
    "Other",
  ]),
  category("eye-lash", "Eye Lash", "ቅንድብ", images.makeup, [
    "Brow — Wax",
    "Brow — String",
    "Brow — Blade",
    "Brow — Henna",
    "Eyelash Normal",
    "One by One",
  ]),
  category("mesh", "Mesh", "ሜሽ", images.hair, ["Mesh"]),
  category("hair-treatment", "Hair Treatment", "የጸጉር እንክብካቤ", images.hair, [
    "Wash",
    "Blow Dry",
    "Treatment",
    "Oil",
    "Fruits & Herbs",
    "Mix",
    "Casc",
    "Braid off",
    "Relaxer — Full",
    "Relaxer — Retouch",
    "Relaxer from customer",
  ]),
  category("hair-cut", "Hair Cut", "የጸጉር ቁርጥ", images.hair, ["Hair Cut"]),
  category("hair-paint", "Hair Paint", "የጸጉር ቀለም", images.hair, [
    "Full",
    "Half",
    "Highlight",
    "Retouch",
    "Front",
    "Color From Customer",
  ]),
  category("hair-styling", "Hair Styling", "የጸጉር ስታይል", images.portrait, [
    "Wave",
    "Deep Wave",
    "Curl",
    "Deep Freeze",
    "Payestra",
    "Hair Styling",
    "Human Hair",
    "Publice",
  ]),
  category("braids", "Braids", "ሹሩባ", images.hair, [
    "Shuruba",
    "Kutirtir",
    "Twist",
    "Albaso",
    "Wig",
  ]),
  category("sewing", "Sewing", "ስፌት", images.hair, ["Sewing", "Human Hair", "Syntthetic"]),
  category("ponytail", "Ponytail", "ፖኒቴል", images.portrait, [
    "Ponytail",
    "Gel",
    "Edge",
    "Normal",
    "Freeze",
  ]),
  category("kids-braids", "Kids Braids", "የህጻናት ሹሩባ", images.space, [
    "Shuruba",
    "Kutirtir",
    "Chele",
  ]),
];
export const bookableServices = serviceCategories.flatMap((c) =>
  c.items.map((s) => ({ ...s, categoryId: c.id, category: c.name, amharic: c.amharic })),
);
export type BookableService = (typeof bookableServices)[number];
export const UNDECIDED_SERVICE = "undecided";
export const UNDECIDED_LABEL = "I'm not sure what to book";
export const findBookableService = (id?: string) => bookableServices.find((s) => s.id === id);
export const isValidServiceSelection = (id: string) =>
  id === UNDECIDED_SERVICE || Boolean(findBookableService(id));
export const bookingDetailsSchema = z.object({
  name: z.string().trim().min(1),
  phone: z.string().trim().min(1),
  email: z.union([z.literal(""), z.string().trim().email()]),
  notes: z.string(),
});
export const filterServiceCategories = (id: ServiceCategoryId | "all") =>
  id === "all" ? serviceCategories : serviceCategories.filter((c) => c.id === id);
