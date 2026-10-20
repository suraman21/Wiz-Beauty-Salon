// The photographs, navigation and fallback copy the site ships with. Anything
// the salon edits in the panel overrides these at runtime.
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
    alt: "Hair being styled at WIZ Beauty Salon",
    items: [
      { id: "hair-cut", name: "Cut & finish", note: "", duration: "" },
      { id: "hair-colour", name: "Colour", note: "", duration: "" },
      {
        id: "hair-style",
        name: "Styling & blow-dry",
        note: "",
        duration: "",
      },
      {
        id: "hair-treatment",
        name: "Hair treatment",
        note: "",
        duration: "",
      },
    ],
  },
  {
    id: "nails",
    name: "Nails",
    tagline: "The detail that changes everything.",
    copy: "Clean shapes, considered colour and finishing touches that feel quietly polished.",
    image: nails,
    alt: "Manicure detail at WIZ Beauty Salon",
    items: [
      { id: "nails-mani", name: "Manicure", note: "", duration: "" },
      { id: "nails-pedi", name: "Pedicure", note: "", duration: "" },
      { id: "nails-art", name: "Nail art", note: "", duration: "" },
    ],
  },
  {
    id: "makeup",
    name: "Makeup",
    tagline: "Your features, beautifully expressed.",
    copy: "From soft daytime looks to occasion glamour — always led by your features, never hiding them.",
    image: makeup,
    alt: "Makeup portrait at WIZ Beauty Salon",
    items: [
      {
        id: "makeup-day",
        name: "Everyday look",
        note: "",
        duration: "",
      },
      {
        id: "makeup-event",
        name: "Occasion makeup",
        note: "",
        duration: "",
      },
      {
        id: "makeup-bridal",
        name: "Bridal makeup",
        note: "",
        duration: "",
      },
    ],
  },
  {
    id: "skin",
    name: "Skin",
    tagline: "A fresh way to feel like yourself.",
    copy: "Calm, restorative care for skin that looks rested and feels like your own.",
    image: skin,
    alt: "Skincare portrait at WIZ Beauty Salon",
    items: [
      { id: "skin-facial", name: "Facial", note: "", duration: "" },
      {
        id: "skin-brows",
        name: "Brow shaping",
        note: "",
        duration: "",
      },
      {
        id: "skin-glow",
        name: "Glow treatment",
        note: "",
        duration: "",
      },
    ],
  },
];

export const allServices = categories.flatMap((c) =>
  c.items.map((i) => ({ ...i, category: c.name, categoryId: c.id })),
);

export const galleryWork: {
  id: string;
  category: CategoryId;
  image: string;
  alt: string;
  title: string;
  tall?: boolean;
}[] = [
  {
    id: "g1",
    category: "hair",
    image: portrait,
    alt: "Editorial hair portrait at WIZ Beauty Salon",
    title: "Soft volume",
    tall: true,
  },
  {
    id: "g2",
    category: "nails",
    image: nails,
    alt: "Manicure detail at WIZ Beauty Salon",
    title: "Quiet polish",
  },
  {
    id: "g3",
    category: "makeup",
    image: makeup,
    alt: "Makeup portrait at WIZ Beauty Salon",
    title: "Evening glow",
    tall: true,
  },
  {
    id: "g4",
    category: "skin",
    image: skin,
    alt: "Skincare portrait at WIZ Beauty Salon",
    title: "Fresh skin",
  },
  {
    id: "g5",
    category: "hair",
    image: hair,
    alt: "Hair styling detail at WIZ Beauty Salon",
    title: "Movement",
    tall: true,
  },
  {
    id: "g6",
    category: "hair",
    image: after,
    alt: "Styled waves at WIZ Beauty Salon",
    title: "Long waves",
  },
  {
    id: "g7",
    category: "makeup",
    image: hero,
    alt: "Editorial beauty portrait at WIZ Beauty Salon",
    title: "Lilac mood",
  },
];
