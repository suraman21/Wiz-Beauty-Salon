import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ArrowRight } from "lucide-react";
import { BookingBand, PageHero, PhotoSlot, SiteLayout } from "@/components/site/SiteChrome";
import { categories, galleryWork, images, type Category } from "@/data/wiz";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services | WIZ Beauty Salon" },
      { name: "description", content: "Every WIZ Beauty Salon service: hair styling, colour, braids, Kids' Corner, nails, waxing, brows, lashes, hair care and makeup." },
      { property: "og:title", content: "Services | WIZ Beauty Salon" },
      { property: "og:description", content: "Browse WIZ services by category and book in a few steps." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const [active, setActive] = useState<string>("hair-styling");

  useEffect(() => {
    const els = categories.map((c) => document.getElementById(c.id)).filter(Boolean) as HTMLElement[];
    const io = new IntersectionObserver(
      (entries) => entries.forEach((e) => e.isIntersecting && setActive(e.target.id)),
      { rootMargin: "-40% 0px -55% 0px" },
    );
    els.forEach((el) => io.observe(el));
    return () => io.disconnect();
  }, []);

  useEffect(() => {
    document.querySelector(`.cat-nav a[data-id="${active}"]`)?.scrollIntoView({ block: "nearest", inline: "center", behavior: "smooth" });
  }, [active]);

  return (
    <SiteLayout>
      <PageHero overline="Services" title={<>Every service,<br /><em>beautifully you.</em></>} copy="From waves and braids to nails, lashes and our Kids' Corner. Choose a category, pick a service — or book and let us help you decide." image={images.makeup} alt="Demo makeup portrait" />

      <nav className="cat-nav" aria-label="Service categories">
        <div className="cat-nav-track">
          {categories.map((c) => (
            <a key={c.id} href={`#${c.id}`} data-id={c.id} className={`chip ${active === c.id ? "is-active" : ""} ${c.id === "kids" ? "chip-kids" : ""}`} aria-current={active === c.id ? "true" : undefined}>{c.short}</a>
          ))}
        </div>
      </nav>

      <div className="svc-list">
        {categories.map((c, i) => <CategorySection key={c.id} c={c} index={i} />)}
      </div>

      <BookingBand title={<>Not sure what<br /><em>to choose?</em></>} copy="Choose “Help me choose” when booking and the WIZ team will contact you to find the right service." />
    </SiteLayout>
  );
}

function CategorySection({ c, index }: { c: Category; index: number }) {
  const work = galleryWork.filter((g) => g.category === c.id).slice(0, 3);
  const groups = [...new Set(c.items.map((i) => i.group ?? ""))];
  const kids = c.id === "kids";
  return (
    <section id={c.id} className={`svc-cat ${kids ? "svc-kids" : ""}`}>
      <div className="section-wrap">
        <div className="svc-head">
          <span className="overline">{String(index + 1).padStart(2, "0")} / {c.short}</span>
          <h2>{kids ? <>WIZ<br /><em>Kids' Corner.</em></> : c.name}</h2>
          <p className="svc-cat-copy">{c.intro}</p>
        </div>

        <div className="svc-body">
          <div className="svc-groups">
            {groups.map((g) => (
              <div key={g || "all"}>
                {g && <h3 className="svc-group-title">{g}</h3>}
                <ul className="svc-items">
                  {c.items.filter((i) => (i.group ?? "") === g).map((item) => (
                    <li key={item.id}>
                      <div className="svc-item-main">
                        <h4>{item.name}</h4>
                        {item.confirm && <span className="svc-confirm">{item.confirm}</span>}
                        {item.options && (
                          <div className="svc-options" aria-label={`${item.name} options`}>
                            {item.options.map((o) => (
                              <Link key={o.id} to="/booking" search={{ service: item.id, option: o.id }} className="opt-chip" aria-label={`Book ${item.name} – ${o.name}`}>{o.name}</Link>
                            ))}
                          </div>
                        )}
                      </div>
                      <Link to="/booking" search={{ service: item.id }} className="svc-book" aria-label={`Book ${item.name}`}>Book this service <ArrowRight size={14} /></Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          <aside className="svc-work">
            <div className="svc-work-grid">
              {work.length > 0
                ? work.map((w) => <figure key={w.id} className="img-frame"><img src={w.image} alt={w.alt} loading="lazy" /></figure>)
                : [0, 1, 2].map((n) => <PhotoSlot key={n} label={kids ? (n === 0 ? "Kids' space photo" : "Kids' hairstyle photo") : `${c.short} photo`} />)}
            </div>
            <div className="svc-work-foot">
              <span className="demo-note">{work.length ? "Demo imagery — WIZ photos to come." : "Real WIZ photos will appear here."}</span>
              <Link to="/gallery" search={{ category: c.id }} className="editorial-link">Explore our work <ArrowRight size={16} strokeWidth={1.5} /></Link>
            </div>
          </aside>
        </div>
      </div>
    </section>
  );
}
