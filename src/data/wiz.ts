// What the site shows for the moment before the panel answers — and if it never
// does. Everything the salon can edit lives in the panel; this is only a net.
import hair from "@/assets/hair-detail.jpg";
import nails from "@/assets/nails-editorial.jpg";
import makeup from "@/assets/makeup-editorial.jpg";
import skin from "@/assets/skin-editorial.jpg";
import portrait from "@/assets/hero-portrait.jpg";
import space from "@/assets/salon-space.jpg";
import hero from "@/assets/home-hero.jpg";
import before from "@/assets/transformation-before.jpg";
import after from "@/assets/transformation-after.jpg";
import servicesHero from "@/assets/services-hero-bride.png";

export const images = {
  hair,
  nails,
  makeup,
  skin,
  portrait,
  space,
  hero,
  before,
  after,
  servicesHero,
};

export const nav = [
  { label: "Home", to: "/" },
  { label: "Services", to: "/services" },
  { label: "About", to: "/about" },
  { label: "Gallery", to: "/gallery" },
  { label: "Contact", to: "/contact" },
] as const;

export type CategoryId = "hair" | "nails" | "makeup" | "skin";

export type ServiceItem = { id: string; name: string; note: string; duration: string };

export const PRICE_PLACEHOLDER = "Price on request";

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
    alt: "Hair being styled",
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
    alt: "Manicure detail",
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
    alt: "Makeup portrait",
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
    alt: "Skincare portrait",
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
    alt: "Hair portrait",
    title: "Soft volume",
    tall: true,
  },
  { id: "g2", category: "nails", image: nails, alt: "Manicure detail", title: "Quiet polish" },
  {
    id: "g3",
    category: "makeup",
    image: makeup,
    alt: "Makeup portrait",
    title: "Evening glow",
    tall: true,
  },
  { id: "g4", category: "skin", image: skin, alt: "Skincare portrait", title: "Fresh skin" },
  {
    id: "g5",
    category: "hair",
    image: hair,
    alt: "Hair styling detail",
    title: "Movement",
    tall: true,
  },
  { id: "g6", category: "hair", image: after, alt: "Styled waves", title: "Long waves" },
  {
    id: "g7",
    category: "makeup",
    image: hero,
    alt: "Beauty portrait",
    title: "Lilac mood",
  },
];

// The salon's details, as the panel publishes them. The salon has given us its
// location, its opening hours and its map, so they live here until the panel
// publishes them itself.
export const contact = {
  phone: "",
  whatsapp: "",
  email: "",
  instagram: "",
  tiktok: "",
  address: "Wiz Beauty Salon",
  hours: [{ day: "Monday – Saturday", time: "9:00 AM – 7:00 PM" }],
  mapEmbed:
    "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4675.060274126608!2d38.78350027569145!3d8.952384490182014!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x164b830018fa71dd%3A0xd006890ffa49c035!2sWiz%20Beauty%20Salon!5e1!3m2!1sen!2set!4v1794870347332!5m2!1sen!2set",
};

// Appointment times, shown only until the panel's calendar answers.
export const demoTimes = ["09:00", "10:00", "11:30", "13:00", "14:30", "16:00", "17:30"];
