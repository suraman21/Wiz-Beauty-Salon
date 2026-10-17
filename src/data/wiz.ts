// WIZ content. The 14 service categories below come from WIZ's own service list
// (service_wiz) and are the single source of truth for the Services, Booking and
// Gallery pages. English and Amharic names are kept exactly as supplied by WIZ.
// Sub-options come only from the old customer order form; options without a
// confirmed Amharic translation keep their original name and are flagged.
// Prices, durations and descriptions are intentionally absent.
// Imagery is temporary demo material — replace with WIZ's own photos.
import hair from "@/assets/hair-detail.jpg";
import nails from "@/assets/nails-editorial.jpg";
import makeup from "@/assets/makeup-editorial.jpg";
import skin from "@/assets/skin-editorial.jpg";
import portrait from "@/assets/hero-portrait.jpg";
import space from "@/assets/salon-space.jpg";
import hero from "@/assets/home-hero.jpg";
import before from "@/assets/transformation-before.jpg";
import after from "@/assets/transformation-after.jpg";

export const images = { hair, nails, makeup, skin, portrait, space, hero, before, after };

export const nav = [
  { label: "Home", to: "/" },
  { label: "Services", to: "/services" },
  { label: "About", to: "/about" },
  { label: "Gallery", to: "/gallery" },
  { label: "Contact", to: "/contact" },
] as const;

export type CategoryId =
  | "makeup"
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

export type ServiceOption = { id: string; name: string };
export type ServiceItem = {
  id: string;
  name: string;
  /** Group heading inside a category popup (e.g. "Face", "Body"). */
  group?: string;
  options?: ServiceOption[];
  /** Flag for terms whose meaning/spelling WIZ still needs to confirm. */
  confirm?: string;
};

export type Category = {
  id: CategoryId;
  /** English name, exactly as on WIZ's service list. */
  name: string;
  /** Amharic name, exactly as supplied by WIZ. */
  amharic: string;
  /** Short label for chips and compact UI. */
  short: string;
  intro: string;
  image?: string;
  alt?: string;
  items: ServiceItem[];
};

const opts = (prefix: string, names: string[]): ServiceOption[] =>
  names.map((n) => ({ id: `${prefix}-${n.toLowerCase().replace(/[^a-z0-9]+/g, "-")}`, name: n }));

export const categories: Category[] = [
  {
    id: "makeup",
    name: "Make Up",
    amharic: "ሜክ አፕ",
    short: "Make Up",
    intro: "Makeup by WIZ. Specific looks and packages will be listed once confirmed by the salon.",
    image: makeup,
    alt: "Demo beauty makeup portrait",
    items: [{ id: "makeup", name: "Makeup", confirm: "Looks and packages to be confirmed by WIZ" }],
  },
  {
    id: "nail",
    name: "Nail",
    amharic: "ጥፍር",
    short: "Nail",
    intro: "Manicure and nail finishes.",
    image: nails,
    alt: "Demo image of a manicure",
    items: [
      { id: "manicure", name: "Manicure", group: "Manicure" },
      { id: "special-manicure", name: "Special Manicure", group: "Manicure", confirm: "Details to be confirmed by WIZ" },
      { id: "gel", name: "Gel", group: "Gel & Extensions" },
      { id: "gel-off", name: "Gel Off", group: "Gel & Extensions" },
      { id: "refill", name: "Refill", group: "Gel & Extensions" },
      { id: "acrylic-extension", name: "Acrylic Extension", group: "Gel & Extensions" },
      { id: "normal-polish", name: "Normal Polish", group: "Polish & Shellac" },
      { id: "shellac", name: "Shellac", group: "Polish & Shellac" },
      { id: "shellac-off", name: "Shellac Off", group: "Polish & Shellac" },
    ],
  },
  {
    id: "pedicure",
    name: "Pedicure",
    amharic: "ፔዲኪውር",
    short: "Pedicure",
    intro: "Pedicure care.",
    image: nails,
    alt: "Demo image of nail care",
    items: [
      { id: "pedicure", name: "Pedicure" },
      { id: "special-pedicure", name: "Special Pedicure", confirm: "Details to be confirmed by WIZ" },
    ],
  },
  {
    id: "wax",
    name: "Wax",
    amharic: "ዋክስ",
    short: "Wax",
    intro: "Hair removal by area. The method for each area is confirmed with you at the salon.",
    items: [
      { id: "wax-full-face", name: "Full Face", group: "Face" },
      { id: "wax-eye-brows", name: "Eye brows", group: "Face" },
      { id: "wax-upper-lip", name: "Upper Lip", group: "Face" },
      { id: "wax-chin", name: "Chin", group: "Face" },
      { id: "wax-full-arm", name: "Full Arm", group: "Arms and legs" },
      { id: "wax-half-arm", name: "Half arm", group: "Arms and legs" },
      { id: "wax-full-leg", name: "Full Leg", group: "Arms and legs" },
      { id: "wax-half-leg", name: "Half Leg", group: "Arms and legs" },
      { id: "wax-under-arm", name: "Under arm", group: "Arms and legs" },
      { id: "wax-full-body", name: "Full body", group: "Body" },
      { id: "wax-chest", name: "Chest", group: "Body" },
      { id: "wax-abdomen", name: "Abdomen", group: "Body" },
      { id: "wax-back", name: "Back", group: "Body" },
      { id: "wax-brazilian", name: "Brazilian", group: "Body" },
      { id: "wax-bikinil", name: "Bikinil", group: "Body", confirm: "Spelling as on the order form" },
      { id: "wax-other", name: "Other", group: "Body" },
      { id: "string", name: "String", group: "Methods" },
      { id: "blade", name: "Blade", group: "Methods" },
      { id: "henna", name: "Henna", group: "Methods" },
    ],
  },
  {
    id: "eye-lash",
    name: "Eye Lash",
    amharic: "ቅንድብ",
    short: "Eye Lash",
    intro: "Eyelash services.",
    items: [{ id: "eyelash", name: "Eyelash", options: opts("lash", ["Normal", "One by one"]) }],
  },
  {
    id: "mesh",
    name: "Mesh",
    amharic: "ሜሽ",
    short: "Mesh",
    intro: "Details for this service will be added once confirmed by WIZ.",
    items: [{ id: "mesh", name: "Mesh", confirm: "Service details to be confirmed by WIZ" }],
  },
  {
    id: "hair-treatment",
    name: "Hair Treatment",
    amharic: "የጸጉር እንክብካቤ",
    short: "Hair Treatment",
    intro: "Washing, blow-dry and hair treatments.",
    image: before,
    alt: "Demo image of natural wavy hair",
    items: [
      { id: "wash", name: "Wash" },
      { id: "casc", name: "Casc", confirm: "Term as on the order form" },
      { id: "braid-off", name: "Braid off" },
      { id: "blow-dry", name: "Blow Dry" },
      { id: "treatment", name: "Treatment" },
      { id: "oil", name: "Oil" },
      { id: "fruits-herbs", name: "Fruits & Herbs" },
      { id: "mix", name: "Mix" },
    ],
  },
  {
    id: "hair-cut",
    name: "Hair Cut",
    amharic: "የጸጉር ቁርጥ",
    short: "Hair Cut",
    intro: "Details for this service will be added once confirmed by WIZ.",
    items: [{ id: "hair-cut", name: "Hair Cut", confirm: "Styles and details to be confirmed by WIZ" }],
  },
  {
    id: "hair-paint",
    name: "Hair Paint",
    amharic: "የጸጉር ቀለም",
    short: "Hair Paint",
    intro: "Colour and relaxer services, with the option to bring your own product.",
    image: after,
    alt: "Demo image of long styled hair",
    items: [
      { id: "hair-color", name: "Hair Color", options: opts("color", ["Full", "Half", "Highlight", "Retouch", "Front"]) },
      { id: "color-from-customer", name: "Color From Customer", confirm: "Customer supplies the colour" },
      { id: "relaxer", name: "Relaxer", options: opts("relaxer", ["Full", "Half", "Retouch"]) },
      { id: "relaxer-from-customer", name: "Relaxer from customer", confirm: "Customer supplies the product" },
    ],
  },
  {
    id: "hair-styling",
    name: "Hair Styling",
    amharic: "የጸጉር ስታይል",
    short: "Hair Styling",
    intro: "Styling, waves and curls from the WIZ service menu.",
    image: hair,
    alt: "Demo image of hair being styled",
    items: [
      { id: "payestra", name: "Payestra" },
      { id: "wave", name: "Wave" },
      { id: "deep-wave", name: "Deep Wave" },
      { id: "curl", name: "Curl" },
      { id: "deep-freeze", name: "Deep Freeze" },
      { id: "hair-styling", name: "Hair Styling" },
    ],
  },
  {
    id: "braids",
    name: "Braids",
    amharic: "ሹሩባ",
    short: "Braids",
    intro: "Traditional styles, braids, wigs and extensions.",
    image: portrait,
    alt: "Demo editorial hair portrait",
    items: [
      { id: "shuruba", name: "Shuruba" },
      { id: "kutirtir", name: "Kutirtir" },
      { id: "twist", name: "Twist" },
      { id: "albaso", name: "Albaso" },
      { id: "chele", name: "Chele" },
      { id: "wig", name: "Wig" },
      { id: "human-hair", name: "Human Hair" },
      { id: "synthetic", name: "Synthetic" },
      { id: "edge", name: "Edge" },
      { id: "freeze", name: "Freeze" },
      {
        id: "braid-number",
        name: "Numbered choice",
        options: opts("num", ["1", "2", "3", "4", "5", "6", "7", "8", "9"]),
        confirm: "Numbers 1–9 appear on the order form — meaning to be confirmed by WIZ",
      },
    ],
  },
  {
    id: "sewing",
    name: "Sewing",
    amharic: "ስፌት",
    short: "Sewing",
    intro: "Sewing service from the WIZ menu.",
    items: [{ id: "sewing", name: "Sewing" }],
  },
  {
    id: "ponytail",
    name: "Ponytail",
    amharic: "ፖኒቴል",
    short: "Ponytail",
    intro: "Ponytail styling from the WIZ menu.",
    items: [{ id: "ponytail", name: "Ponytail" }],
  },
  {
    id: "kids-braids",
    name: "Kids Braids",
    amharic: "የህጻናት ሹሩባ",
    short: "Kids Braids",
    intro: "A dedicated, welcoming space for children, with hairstyles made just for them.",
    items: [
      { id: "kids-braids", name: "Kids' braids" },
      { id: "kids-shuruba", name: "Shuruba" },
      { id: "kids-kutirtir", name: "Kutirtir" },
      { id: "kids-chele", name: "Chele" },
      { id: "kids-twist", name: "Twist" },
      { id: "kids-other", name: "Other children's style", confirm: "Further styles to be confirmed by WIZ" },
    ],
  },
];

export const allServices = categories.flatMap((c) =>
  c.items.map((i) => ({ ...i, category: c.name, categoryId: c.id })),
);
export const findService = (id?: string) => allServices.find((s) => s.id === id);

/** Featured on Home. */
export const homeFeatured = [
  { id: "hair-styling" as const, name: "Hair", tagline: "Waves, curls and styling.", image: hair, alt: "Demo image of hair being styled" },
  { id: "braids" as const, name: "Braids", tagline: "Traditional styles and extensions.", image: portrait, alt: "Demo editorial hair portrait" },
  { id: "makeup" as const, name: "Makeup", tagline: "Your features, beautifully expressed.", image: makeup, alt: "Demo makeup portrait" },
  { id: "nail" as const, name: "Nails", tagline: "The detail that changes everything.", image: nails, alt: "Demo manicure" },
];

/**
 * Gallery items per category. All current images are demo material.
 * Categories with no entries show "photos coming soon" placeholders.
 * Add WIZ photos here (only with client permission for identifiable people).
 */
export const galleryWork: { id: string; category: CategoryId; image: string; alt: string; title: string; tall?: boolean }[] = [
  { id: "g1", category: "braids", image: portrait, alt: "Demo editorial hair portrait", title: "Soft volume", tall: true },
  { id: "g2", category: "nail", image: nails, alt: "Demo manicure detail", title: "Quiet polish" },
  { id: "g3", category: "makeup", image: makeup, alt: "Demo makeup portrait", title: "Evening glow", tall: true },
  { id: "g4", category: "makeup", image: skin, alt: "Demo beauty portrait", title: "Fresh look" },
  { id: "g5", category: "hair-styling", image: hair, alt: "Demo hair styling detail", title: "Movement", tall: true },
  { id: "g6", category: "hair-styling", image: after, alt: "Demo styled waves", title: "Long waves" },
  { id: "g7", category: "makeup", image: hero, alt: "Demo editorial beauty portrait", title: "Lilac mood" },
  { id: "g8", category: "hair-treatment", image: before, alt: "Demo natural wavy hair", title: "Natural texture" },
];

// Clearly marked sample stories — replace with real, permission-given client words.
export const stories = [
  { id: "s1", quote: "A short client story about how their visit felt will appear here.", who: "Client name · placeholder", service: "Hair" },
  { id: "s2", quote: "Space for a second real client story, shared with their permission.", who: "Client name · placeholder", service: "Makeup" },
  { id: "s3", quote: "A third story — a few honest words about a WIZ moment.", who: "Client name · placeholder", service: "Nails" },
];

// Contact placeholders — every value is to be supplied by WIZ.
export const contact = {
  phone: "[Phone number — to be added]",
  whatsapp: "[WhatsApp number — to be added]",
  email: "[Email address — to be added]",
  instagram: "[@instagram — to be added]",
  tiktok: "[@tiktok — to be added]",
  address: "[Salon address — to be added]",
  hours: [
    { day: "Monday – Friday", time: "[Hours TBC]" },
    { day: "Saturday", time: "[Hours TBC]" },
    { day: "Sunday", time: "[Hours TBC]" },
  ],
};

// Preferred-time choices only — not live availability.
export const demoTimes = ["09:00", "10:00", "11:30", "13:00", "14:30", "16:00", "17:30"];
