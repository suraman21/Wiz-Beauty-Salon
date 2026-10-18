import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { ArrowRight } from "lucide-react";
import { BookingBand, PageHero, SiteLayout } from "@/components/site/SiteChrome";
import { Button } from "@/components/ui/button";
import { images, PRICE_PLACEHOLDER } from "@/data/wiz";
import { serviceCategories, filterServiceCategories, type ServiceCategoryId } from "@/data/services";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services | WIZ Beauty Salon" },
      { name: "description", content: "Explore WIZ Beauty Salon's 14 service categories in English and Amharic, including hair, nails, makeup and kids' braids." },
      { property: "og:title", content: "Services | WIZ Beauty Salon" },
      { property: "og:description", content: "Explore WIZ's bilingual service menu and choose your appointment or an undecided request." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const [filter, setFilter] = useState<ServiceCategoryId | "all">("all");
  const shown = filterServiceCategories(filter);
  return (
    <SiteLayout>
      <PageHero overline="Services" title={<>Made for<br /><em>every moment.</em></>} copy="Hair, nails, makeup and more — choose a category, then select your appointment." image={images.makeup} alt="Demo makeup portrait" />

      <div className="filter-bar section-wrap" role="tablist" aria-label="Service categories">
        <Button variant="ghost" role="tab" aria-selected={filter === "all"} className={`chip h-auto rounded-none ${filter === "all" ? "is-active" : ""}`} onClick={() => setFilter("all")}>All</Button>
        {serviceCategories.map((c) => (
          <Button variant="ghost" key={c.id} role="tab" aria-selected={filter === c.id} className={`chip h-auto rounded-none flex-wrap whitespace-normal ${filter === c.id ? "is-active" : ""}`} onClick={() => setFilter(c.id)}>{c.name} <span lang="am">{c.amharic}</span></Button>
        ))}
      </div>

      <div className="svc-list">
        {shown.map((c, i) => (
          <section id={c.id} key={c.id} className={`svc-cat ${i % 2 ? "svc-flip" : ""}`}>
            <div className="section-wrap svc-cat-inner">
              <figure className="svc-cat-image img-frame"><img src={c.image} alt={c.alt} loading="lazy" /></figure>
              <div className="svc-cat-body">
                <span className="overline">{String(serviceCategories.indexOf(c) + 1).padStart(2, "0")} / {c.name}</span>
                <h2 className="break-words">{c.name}<br /><em lang="am" className="font-sans text-2xl leading-normal not-italic">{c.amharic}</em></h2>
                <ul className="svc-items">
                  {c.items.map((item) => (
                    <li key={item.id}>
                      <div className="min-w-0 break-words"><h3>{item.name}</h3><span>{PRICE_PLACEHOLDER}</span></div>
                      <Link to="/booking" search={{ service: item.id }} className="svc-book" aria-label={`Book ${item.name}`}>Book <ArrowRight size={15} /></Link>
                    </li>
                  ))}
                </ul>
                <span className="demo-note">Illustrative image only. Prices and durations to be confirmed by WIZ.</span>
              </div>
            </div>
          </section>
        ))}
      </div>

      <BookingBand title={<>Not sure what<br /><em>to choose?</em></>} copy="Book a time and tell us what you have in mind — we'll guide you from there." />
    </SiteLayout>
  );
}
