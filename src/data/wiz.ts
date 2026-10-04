// WIZ demo content. Everything here is replaceable placeholder material —
// swap in the salon's confirmed services, photos, details and stories.
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

export type CategoryId = "hair" | "nails" | "makeup" | "skin";

export type ServiceItem = { id: string; name: string; note: string; duration: string };

export const PRICE_PLACEHOLDER = "Price · to be confirmed";

export const categories: {
  id: CategoryId;
  name: string;
  tagline: string;
  copy: string;
  image: string;
  alt: string;
  items: ServiceItem[];
}[] = [
  {
    id: "hair",
    name: "Hair",
    tagline: "A little movement. A lot of feeling.",
    copy: "Cuts, colour and styling shaped around your texture, your routine and the way you want to feel.",
    image: hair,
    alt: "Demo image of hair being styled",
    items: [
      { id: "hair-cut", name: "Cut & finish", note: "Demo service name", duration: "Duration TBC" },
      { id: "hair-colour", name: "Colour", note: "Demo service name", duration: "Duration TBC" },
      { id: "hair-style", name: "Styling & blow-dry", note: "Demo service name", duration: "Duration TBC" },
      { id: "hair-treatment", name: "Hair treatment", note: "Demo service name", duration: "Duration TBC" },
    ],
  },
  {
    id: "nails",
    name: "Nails",
    tagline: "The detail that changes everything.",
    copy: "Clean shapes, considered colour and finishing touches that feel quietly polished.",
    image: nails,
    alt: "Demo image of a manicure",
    items: [
      { id: "nails-mani", name: "Manicure", note: "Demo service name", duration: "Duration TBC" },
      { id: "nails-pedi", name: "Pedicure", note: "Demo service name", duration: "Duration TBC" },
      { id: "nails-art", name: "Nail art", note: "Demo service name", duration: "Duration TBC" },
    ],
  },
  {
    id: "makeup",
    name: "Makeup",
    tagline: "Your features, beautifully expressed.",
    copy: "From soft daytime looks to occasion glamour — always led by your features, never hiding them.",
    image: makeup,
    alt: "Demo beauty makeup portrait",
    items: [
      { id: "makeup-day", name: "Everyday look", note: "Demo service name", duration: "Duration TBC" },
      { id: "makeup-event", name: "Occasion makeup", note: "Demo service name", duration: "Duration TBC" },
      { id: "makeup-bridal", name: "Bridal makeup", note: "Demo service name", duration: "Duration TBC" },
    ],
  },
  {
    id: "skin",
    name: "Skin",
    tagline: "A fresh way to feel like yourself.",
    copy: "Calm, restorative care for skin that looks rested and feels like your own.",
    image: skin,
    alt: "Demo skincare portrait",
    items: [
      { id: "skin-facial", name: "Facial", note: "Demo service name", duration: "Duration TBC" },
      { id: "skin-brows", name: "Brow shaping", note: "Demo service name", duration: "Duration TBC" },
      { id: "skin-glow", name: "Glow treatment", note: "Demo service name", duration: "Duration TBC" },
    ],
  },
];

export const allServices = categories.flatMap((c) =>
  c.items.map((i) => ({ ...i, category: c.name, categoryId: c.id })),
);

export const galleryWork: { id: string; category: CategoryId; image: string; alt: string; title: string; tall?: boolean }[] = [
  { id: "g1", category: "hair", image: portrait, alt: "Demo editorial hair portrait", title: "Soft volume", tall: true },
  { id: "g2", category: "nails", image: nails, alt: "Demo manicure detail", title: "Quiet polish" },
  { id: "g3", category: "makeup", image: makeup, alt: "Demo makeup portrait", title: "Evening glow", tall: true },
  { id: "g4", category: "skin", image: skin, alt: "Demo skincare portrait", title: "Fresh skin" },
  { id: "g5", category: "hair", image: hair, alt: "Demo hair styling detail", title: "Movement", tall: true },
  { id: "g6", category: "hair", image: after, alt: "Demo styled waves", title: "Long waves" },
  { id: "g7", category: "makeup", image: hero, alt: "Demo editorial beauty portrait", title: "Lilac mood" },
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

// Demo appointment times — replace with the salon's real availability.
export const demoTimes = ["09:00", "10:00", "11:30", "13:00", "14:30", "16:00", "17:30"];
