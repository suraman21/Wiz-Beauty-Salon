import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { ArrowRight } from "lucide-react";
import { BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { categories, images, PRICE_PLACEHOLDER, type CategoryId } from "@/data/wiz";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services | WIZ Beauty Salon" },
      { name: "description", content: "Hair, nails, makeup and skin services at WIZ Beauty Salon. Choose your treatment and book in a few steps." },
      { property: "og:title", content: "Services | WIZ Beauty Salon" },
      { property: "og:description", content: "Explore hair, nails, makeup and skin services and book your WIZ moment." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const [filter, setFilter] = useState<CategoryId | "all">("all");
  const shown = filter === "all" ? categories : categories.filter((c) => c.id === filter);
  return (
    <SiteLayout>
      <PageHero overline="Services" title={<>Made for<br /><em>every moment.</em></>} copy="Hair, nails, makeup and skin — each one shaped around you. Choose a category, then book in a few easy steps." image={images.makeup} alt="Demo makeup portrait" />

      <div className="filter-bar section-wrap" role="tablist" aria-label="Service categories">
        {[{ id: "all", name: "All" }, ...categories].map((c) => (
          <button key={c.id} role="tab" aria-selected={filter === c.id} className={`chip ${filter === c.id ? "is-active" : ""}`} onClick={() => setFilter(c.id as CategoryId | "all")}>{c.name}</button>
        ))}
      </div>

      <div className="svc-list">
        {shown.map((c, i) => (
          <section id={c.id} key={c.id} className={`svc-cat ${i % 2 ? "svc-flip" : ""}`}>
            <div className="section-wrap svc-cat-inner">
              <figure className="svc-cat-image img-frame"><img src={c.image} alt={c.alt} loading="lazy" /></figure>
              <div className="svc-cat-body">
                <span className="overline">0{categories.indexOf(c) + 1} / {c.name}</span>
                <h2>{c.name}<br /><em>{c.tagline.split(".")[0]}.</em></h2>
                <p className="svc-cat-copy">{c.copy}</p>
                <ul className="svc-items">
                  {c.items.map((item) => (
                    <li key={item.id}>
                      <div><h3>{item.name}</h3><span>{item.duration} · {PRICE_PLACEHOLDER}</span></div>
                      <Link to="/booking" search={{ service: item.id }} className="svc-book" aria-label={`Book ${item.name}`}>Book <ArrowRight size={15} /></Link>
                    </li>
                  ))}
                </ul>
                <span className="demo-note">Service names, durations and prices are demo placeholders.</span>
              </div>
            </div>
          </section>
        ))}
      </div>

      <BookingBand title={<>Not sure what<br /><em>to choose?</em></>} copy="Book a time and tell us what you have in mind — we'll guide you from there." />
    </SiteLayout>
  );
}
