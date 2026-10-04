import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import logo from "@/assets/wiz-logo.png.asset.json";
import hero from "@/assets/hero-portrait.jpg";
import space from "@/assets/salon-space.jpg";
import hair from "@/assets/hair-detail.jpg";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "WIZ Beauty Salon — Brand & Design Foundation" },
      { name: "description", content: "The visual identity for WIZ Beauty Salon: palette, typography, components and image language derived from the WIZ logo." },
      { property: "og:title", content: "WIZ Beauty Salon — Brand & Design Foundation" },
      { property: "og:description", content: "Palette, typography and interface language for WIZ Beauty Salon." },
    ],
  }),
  component: Foundation,
});

const NAV = ["Home", "Services", "About", "Gallery", "Contact"];

const PALETTE = [
  { name: "Ink", role: "Primary text · Primary CTA", cls: "bg-foreground", light: true, note: "From the logo's illustration" },
  { name: "Aubergine", role: "Secondary brand · CTA hover", cls: "bg-plum", light: true, note: "Deepened logo ink" },
  { name: "Lilac", role: "Signature tint · Highlights", cls: "bg-lilac", note: "The logo disc" },
  { name: "Dusty Mauve", role: "Interactive states · Focus", cls: "bg-lilac-deep", light: true, note: "Lilac, muted" },
  { name: "Bronze", role: "Accent · used sparingly", cls: "bg-bronze", light: true, note: "Warm counterpoint" },
  { name: "Ivory", role: "Main background", cls: "bg-ivory border", note: "Warm, never stark white" },
  { name: "Stone", role: "Secondary background", cls: "bg-stone", note: "Section rhythm" },
];

function Section({ n, title, children, className = "" }: { n: string; title: string; children: React.ReactNode; className?: string }) {
  return (
    <section className={`mx-auto max-w-[1320px] px-6 py-20 md:px-10 md:py-28 ${className}`}>
      <div className="mb-12 flex items-baseline gap-6 border-t pt-6">
        <span className="eyebrow text-muted-foreground">{n}</span>
        <h2 className="text-h2">{title}</h2>
      </div>
      {children}
    </section>
  );
}

function Foundation() {
  const [menu, setMenu] = useState(false);
  return (
    <div className="min-h-screen overflow-x-hidden">
      {/* Navigation */}
      <header className="sticky top-0 z-50 border-b bg-background/85 backdrop-blur-md">
        <div className="mx-auto flex h-20 max-w-[1320px] items-center justify-between px-6 md:px-10">
          <a href="/" className="flex items-center gap-3">
            <img src={logo.url} alt="WIZ Beauty Salon logo" className="h-11 w-11" />
            <span className="font-display text-2xl tracking-[0.18em]">WIZ</span>
          </a>
          <nav className="hidden items-center gap-10 text-sm md:flex">
            {NAV.map((l, i) => (
              <a key={l} href="#" className={`link-underline pb-0.5 ${i === 0 ? "text-foreground" : "text-muted-foreground hover:text-foreground"}`}>{l}</a>
            ))}
          </nav>
          <div className="flex items-center gap-3">
            <a href="#" className="eyebrow rounded-sm bg-primary px-6 py-3.5 text-primary-foreground transition-colors duration-500 hover:bg-primary-hover">Book Now</a>
            <button onClick={() => setMenu(!menu)} className="eyebrow md:hidden" aria-label="Menu">{menu ? "Close" : "Menu"}</button>
          </div>
        </div>
        {menu && (
          <nav className="reveal-up border-t bg-background px-6 py-8 md:hidden">
            {NAV.map((l) => (
              <a key={l} href="#" className="block py-3 font-display text-3xl">{l}</a>
            ))}
          </nav>
        )}
      </header>

      {/* Hero — the direction itself */}
      <section className="relative mx-auto grid max-w-[1320px] grid-cols-12 gap-6 px-6 pt-14 pb-24 md:px-10 md:pt-20">
        <div className="lilac-disc pointer-events-none absolute -right-40 top-10 h-[560px] w-[560px] opacity-60" />
        <div className="relative col-span-12 md:col-span-7 md:pt-16">
          <p className="eyebrow reveal-up text-lilac-deep">WIZ Beauty Salon · Brand foundation</p>
          <h1 className="text-display reveal-up mt-6" style={{ animationDelay: "120ms" }}>
            Beauty,<br />in its own<br /><em className="text-plum">light.</em>
          </h1>
          <p className="reveal-up mt-8 max-w-[44ch] text-lg leading-relaxed text-muted-foreground" style={{ animationDelay: "240ms" }}>
            A visual identity grown from the WIZ logo — the soft lilac disc, the confident black ink, the hair in motion. Quiet, warm, unmistakably feminine.
          </p>
          <div className="reveal-up mt-10 flex flex-wrap items-center gap-6" style={{ animationDelay: "360ms" }}>
            <a href="#" className="eyebrow rounded-sm bg-primary px-8 py-4 text-primary-foreground transition-colors duration-500 hover:bg-primary-hover">Book an appointment</a>
            <a href="#" className="link-underline text-sm">Explore services</a>
          </div>
        </div>
        <div className="relative col-span-12 md:col-span-5">
          <div className="img-frame reveal relative aspect-[4/5]">
            <img src={hero} alt="Editorial beauty portrait (placeholder image)" width={1024} height={1280} className="h-full w-full object-cover" />
          </div>
          <p className="eyebrow mt-3 text-muted-foreground">Placeholder imagery — to be replaced with WIZ photography</p>
        </div>
      </section>

      {/* 01 Logo analysis */}
      <div className="bg-secondary">
        <Section n="01" title="From the logo">
          <div className="grid grid-cols-12 gap-10">
            <div className="col-span-12 flex items-center justify-center md:col-span-5">
              <img src={logo.url} alt="WIZ Beauty Salon original logo" className="w-full max-w-[360px]" />
            </div>
            <div className="col-span-12 grid gap-8 sm:grid-cols-2 md:col-span-7">
              {[
                ["Dominant", "A pale, cool lilac with a soft watercolour edge — calm and feminine, never loud."],
                ["Contrast", "Pure black ink line-work. It gives the mark its confidence; we keep it as the primary text and CTA colour."],
                ["Personality", "Hair in motion, eyes closed — serene, sensual, self-assured. Movement becomes our animation language."],
                ["Typography", "A condensed bold sans in the wordmark. We balance it with a refined serif for warmth and editorial voice."],
              ].map(([t, d]) => (
                <div key={t}>
                  <p className="eyebrow text-lilac-deep">{t}</p>
                  <p className="mt-3 leading-relaxed text-muted-foreground">{d}</p>
                </div>
              ))}
            </div>
          </div>
        </Section>
      </div>

      {/* 02 Palette */}
      <Section n="02" title="Colour, with restraint">
        <div className="grid grid-cols-2 gap-px border bg-border md:grid-cols-7">
          {PALETTE.map((c) => (
            <div key={c.name} className="bg-background">
              <div className={`h-40 md:h-56 ${c.cls}`} />
              <div className="p-4">
                <p className="font-display text-xl">{c.name}</p>
                <p className="mt-1 text-xs text-muted-foreground">{c.role}</p>
                <p className="mt-3 text-[11px] italic text-muted-foreground">{c.note}</p>
              </div>
            </div>
          ))}
        </div>
        <p className="mt-6 max-w-[60ch] text-sm text-muted-foreground">
          Lilac is a tint, not a flood: roughly 70% ivory &amp; stone, 20% ink, 8% lilac, 2% bronze.
        </p>
      </Section>

      {/* 03 Typography */}
      <div className="bg-foreground text-background">
        <section className="mx-auto max-w-[1320px] px-6 py-20 md:px-10 md:py-28">
          <div className="mb-12 flex items-baseline gap-6 border-t border-background/20 pt-6">
            <span className="eyebrow opacity-60">03</span>
            <h2 className="text-h2">Typography</h2>
          </div>
          <div className="grid grid-cols-12 gap-10">
            <div className="col-span-12 md:col-span-7">
              <p className="eyebrow opacity-60">Display — Cormorant Garamond</p>
              <p className="text-display mt-4">Aa Wiz</p>
              <div className="mt-10 space-y-5 border-t border-background/20 pt-8">
                <p className="text-h1">Heading one, editorial</p>
                <p className="text-h2">Heading two, considered</p>
                <p className="font-display text-2xl">Heading three, quietly refined</p>
              </div>
            </div>
            <div className="col-span-12 md:col-span-5">
              <p className="eyebrow opacity-60">Text &amp; interface — Manrope</p>
              <p className="mt-6 text-lg leading-relaxed opacity-90">
                Body copy is set in a clean contemporary sans for effortless reading on any screen. Generous line height, short measures, calm rhythm.
              </p>
              <p className="mt-4 text-sm leading-relaxed opacity-60">Small text and captions keep their clarity at every size.</p>
              <p className="eyebrow mt-8 text-lilac">Eyebrow · labels · navigation</p>
            </div>
          </div>
        </section>
      </div>

      {/* 04 Components */}
      <Section n="04" title="Interface language">
        <div className="grid grid-cols-12 gap-10">
          <div className="col-span-12 space-y-10 md:col-span-5">
            <div>
              <p className="eyebrow mb-5 text-muted-foreground">Buttons</p>
              <div className="flex flex-wrap items-center gap-4">
                <button className="eyebrow rounded-sm bg-primary px-8 py-4 text-primary-foreground transition-colors duration-500 hover:bg-primary-hover">Book Now</button>
                <button className="eyebrow rounded-sm border border-foreground px-8 py-4 transition-colors duration-500 hover:bg-foreground hover:text-background">WhatsApp us</button>
                <button className="link-underline text-sm">View all services →</button>
              </div>
            </div>
            <div>
              <p className="eyebrow mb-5 text-muted-foreground">Form fields</p>
              <div className="space-y-6">
                {["Full name", "Phone or WhatsApp"].map((l) => (
                  <label key={l} className="block">
                    <span className="eyebrow text-muted-foreground">{l}</span>
                    <input placeholder={l === "Full name" ? "Your name" : "+000 000 000"} className="mt-2 w-full border-0 border-b border-input bg-transparent py-3 text-base outline-none transition-colors placeholder:text-muted-foreground/60 focus:border-lilac-deep" />
                  </label>
                ))}
                <label className="block">
                  <span className="eyebrow text-muted-foreground">Service</span>
                  <select className="mt-2 w-full border-0 border-b border-input bg-transparent py-3 text-base outline-none focus:border-lilac-deep">
                    <option>Select a service</option>
                    <option>Hair (placeholder)</option>
                    <option>Skin (placeholder)</option>
                  </select>
                </label>
              </div>
            </div>
          </div>

          <div className="col-span-12 md:col-span-7">
            <p className="eyebrow mb-5 text-muted-foreground">Service presentation — editorial, not boxed</p>
            <div className="divide-y border-y">
              {["Hair", "Skin", "Nails", "Makeup"].map((s, i) => (
                <a key={s} href="#" className="group flex items-center justify-between py-6 transition-colors duration-500 hover:bg-secondary/60 md:px-4">
                  <div className="flex items-baseline gap-6">
                    <span className="eyebrow text-muted-foreground">0{i + 1}</span>
                    <span className="font-display text-3xl md:text-4xl">{s}</span>
                  </div>
                  <span className="text-sm text-muted-foreground transition-transform duration-500 group-hover:translate-x-1">
                    [Price placeholder] →
                  </span>
                </a>
              ))}
            </div>
            <p className="mt-4 text-xs text-muted-foreground">Service categories shown as examples only — final list from WIZ.</p>
          </div>
        </div>
      </Section>

      {/* 05 Imagery */}
      <div className="bg-secondary">
        <Section n="05" title="Image & motion">
          <div className="grid grid-cols-12 items-end gap-6">
            <figure className="img-frame col-span-12 aspect-[16/11] md:col-span-7">
              <img src={space} alt="Salon interior (placeholder image)" loading="lazy" width={1280} height={896} className="h-full w-full object-cover" />
            </figure>
            <div className="col-span-12 md:col-span-5">
              <figure className="img-frame aspect-[4/5] md:-mb-24 md:ml-10">
                <img src={hair} alt="Stylist at work (placeholder image)" loading="lazy" width={896} height={1120} className="h-full w-full object-cover" />
              </figure>
            </div>
          </div>
          <div className="mt-16 grid gap-8 sm:grid-cols-3 md:mt-32">
            {[
              ["Images", "Warm natural light, varied proportions, intentional overlaps. Sharp corners — the frame is the photograph."],
              ["Motion", "Slow reveals and a 1.4s zoom on hover. Like hair settling after it moves. Nothing bounces."],
              ["Space", "An 8px rhythm with generous section breathing room. Whitespace is part of the luxury."],
            ].map(([t, d]) => (
              <div key={t}>
                <p className="eyebrow text-lilac-deep">{t}</p>
                <p className="mt-3 leading-relaxed text-muted-foreground">{d}</p>
              </div>
            ))}
          </div>
        </Section>
      </div>

      {/* Closing CTA */}
      <section className="relative overflow-hidden">
        <div className="mx-auto max-w-[1320px] px-6 py-28 text-center md:px-10">
          <div className="lilac-disc mx-auto mb-10 h-24 w-24" />
          <h2 className="text-h1 mx-auto max-w-[16ch]">Ready to experience WIZ?</h2>
          <a href="#" className="eyebrow mt-10 inline-block rounded-sm bg-primary px-10 py-4 text-primary-foreground transition-colors duration-500 hover:bg-primary-hover">Book an appointment</a>
        </div>
      </section>

      <footer className="border-t">
        <div className="mx-auto flex max-w-[1320px] flex-col gap-4 px-6 py-10 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between md:px-10">
          <span className="font-display text-xl tracking-[0.18em] text-foreground">WIZ</span>
          <span>[Address placeholder] · [Hours placeholder] · [Phone placeholder]</span>
          <span>[Instagram] · [TikTok] · [WhatsApp]</span>
        </div>
      </footer>
    </div>
  );
}
