import heroWizPurple from "@/assets/hero-wiz-purple.jpg";

/**
 * The salon's hero photograph, in one place.
 *
 * The home page shows it full-bleed. Every other page's hero shows the same
 * picture as a narrow band, so the site reads as one place instead of five
 * different ones — and the band is cropped from the top of the frame, where
 * the tiara, the face and the flowers are, never through them.
 */
export const heroArt = {
  url: heroWizPurple,
  alt: "Model in a white off-shoulder gown and crystal tiara, in a softly lit purple salon",
  width: 1672,
  height: 941,
} as const;
