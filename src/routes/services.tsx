import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ArrowRight, X } from "lucide-react";
import { BookingBand, PageHero, PhotoSlot, SiteLayout } from "@/components/site/SiteChrome";
import { categories, images, type Category } from "@/data/wiz";

export const Route = createFileRoute("/services")({
  head: () => ({
    meta: [
      { title: "Services | WIZ Beauty Salon" },
      { name: "description", content: "Every WIZ Beauty Salon service: makeup, nails, pedicure, wax, eye lash, hair treatment, cut, colour, styling, braids, sewing, ponytail and kids braids." },
      { property: "og:title", content: "Services | WIZ Beauty Salon" },
      { property: "og:description", content: "Browse WIZ services and book in a few steps." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Services,
});

function Services() {
  const [open, setOpen] = useState<Category | null>(null);

  useEffect(() => {
    if (!open) return;
    document.body.style.overflow = "hidden";
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(null);
    window.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = "";
      window.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <SiteLayout>
      <PageHero overline="Services" title={<>Every service,<br /><em>beautifully you.</em></>} copy="From braids and styling to nails, lashes and our Kids' Corner. Choose a service to see its options — or book and let us help you decide." image={images.makeup} alt="Demo makeup portrait" />

      <section className="svc-grid-section section-wrap">
        <div className="section-heading">
          <div><span className="overline">Our services</span><h2>Choose your<br /><em>moment.</em></h2></div>
          <p>Tap a service to see its options and book.</p>
        </div>
        <div className="svc-card-grid">
          {categories.map((c) => (
            <button key={c.id} type="button" className={`svc-card ${c.id === "kids-braids" ? "svc-card-kids" : ""}`} onClick={() => setOpen(c)} aria-haspopup="dialog" aria-label={`${c.name} — ${c.amharic}. View options`}>
              {c.image
                ? <span className="svc-card-img img-frame"><img src={c.image} alt={c.alt ?? ""} loading="lazy" /></span>
                : <PhotoSlot label={c.id === "kids-braids" ? "Kids' Corner photo" : `${c.name} photo`} />}
              <span className="svc-card-text">
                <span className="svc-card-names"><strong>{c.name}</strong><span className="svc-card-am" lang="am">{c.amharic}</span></span>
                <span className="svc-card-cta">View options <ArrowRight size={14} aria-hidden="true" /></span>
              </span>
            </button>
          ))}
        </div>
        <span className="demo-note">Demo imagery — WIZ's own photos will replace these.</span>
      </section>

      <BookingBand title={<>Not sure what<br /><em>to choose?</em></>} copy="Choose “Help me choose” when booking and the WIZ team will contact you to find the right service." />

      {open && <ServiceModal category={open} onClose={() => setOpen(null)} />}
    </SiteLayout>
  );
}

function ServiceModal({ category: c, onClose }: { category: Category; onClose: () => void }) {
  const groups = [...new Set(c.items.map((i) => i.group ?? ""))];
  const single = c.items.length === 1 && !c.items[0].options;
  return (
    <div className="svc-modal-backdrop" role="presentation" onClick={onClose}>
      <div className="svc-modal" role="dialog" aria-modal="true" aria-label={`${c.name} — ${c.amharic}`} onClick={(e) => e.stopPropagation()}>
        <button type="button" className="svc-modal-close" aria-label="Close" onClick={onClose}><X size={18} /></button>
        {c.image && <figure className="svc-modal-img img-frame"><img src={c.image} alt={c.alt ?? ""} /></figure>}
        <div className="svc-modal-body">
          <div className="svc-modal-head">
            <h3>{c.name}</h3>
            <span className="svc-card-am" lang="am">{c.amharic}</span>
          </div>
          <p className="svc-modal-intro">{c.intro}</p>

          {groups.map((g) => (
            <div key={g || "all"} className="svc-modal-group">
              {g && <h4 className="svc-group-title">{g}</h4>}
              <ul className="svc-modal-items">
                {c.items.filter((i) => (i.group ?? "") === g).map((item) => (
                  <li key={item.id}>
                    <div className="svc-item-main">
                      <span className="opt-name">{item.name}</span>
                      {item.confirm && <span className="svc-confirm">{item.confirm}</span>}
                      {item.options && (
                        <span className="svc-options" aria-label={`${item.name} options`}>
                          {item.options.map((o) => (
                            <Link key={o.id} to="/booking" search={{ service: item.id, option: o.id }} className="opt-chip" aria-label={`Book ${item.name} – ${o.name}`}>{o.name}</Link>
                          ))}
                        </span>
                      )}
                    </div>
                    {!item.options && (
                      <Link to="/booking" search={{ service: item.id }} className="svc-book" aria-label={`Book ${item.name}`}>Book <ArrowRight size={13} /></Link>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}

          <div className="svc-modal-foot">
            <Link to="/booking" search={single ? { service: c.items[0].id } : {}} className="btn btn-ink">Book this service <ArrowRight size={16} /></Link>
            <span className="demo-note">Not sure? Choose “Help me choose” when booking.</span>
          </div>
        </div>
      </div>
    </div>
  );
}
