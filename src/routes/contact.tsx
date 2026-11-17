import { createFileRoute } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import { Clock, Mail, MapPin, MessageCircle, Phone, Instagram, Music2 } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { EditorialLink, SiteLayout } from "@/components/site/SiteChrome";
import { contact } from "@/data/wiz";
import { ApiError } from "@/lib/api";
import { sendMessage } from "@/lib/contact-api";
import {
  closureLabel,
  hasDetails,
  hoursRows,
  whatsappHref,
  type SalonContact,
} from "@/lib/salon-api";
import { useSalonInfo, useSiteImages } from "@/lib/site-content";
import { useReveal } from "@/lib/use-reveal";

export const Route = createFileRoute("/contact")({
  head: () => ({
    meta: [
      { title: "Contact | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "Get in touch with WIZ Beauty Salon by phone, WhatsApp, email or social media, and find our location and hours.",
      },
      { property: "og:title", content: "Contact | WIZ Beauty Salon" },
      {
        property: "og:description",
        content: "Phone, WhatsApp, email, social, location and hours for WIZ Beauty Salon.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Contact,
});

type Channel = { icon: LucideIcon; label: string; value: string; href: string | undefined };

const digits = (value: string) => value.replace(/[^\d+]/g, "");

/** Only the details the salon has actually published, each one dialable. */
const channelsFor = (salon: SalonContact): Channel[] =>
  [
    {
      icon: Phone,
      label: "Phone",
      value: salon.phone,
      href: salon.phone === "" ? undefined : `tel:${digits(salon.phone)}`,
    },
    {
      icon: MessageCircle,
      label: "WhatsApp",
      value: salon.whatsapp,
      href: whatsappHref(salon.whatsapp) === "" ? undefined : whatsappHref(salon.whatsapp),
    },
    {
      icon: Mail,
      label: "Email",
      value: salon.email,
      href: salon.email === "" ? undefined : `mailto:${salon.email}`,
    },
    {
      icon: Instagram,
      label: "Instagram",
      value: salon.instagram === "" ? "" : `@${salon.instagram}`,
      href: salon.instagram === "" ? undefined : `https://instagram.com/${salon.instagram}`,
    },
    {
      icon: Music2,
      label: "TikTok",
      value: salon.tiktok === "" ? "" : `@${salon.tiktok}`,
      href: salon.tiktok === "" ? undefined : `https://tiktok.com/@${salon.tiktok}`,
    },
  ].filter((channel) => channel.value !== "");

type FormState = { name: string; contact: string; message: string };

const emptyForm: FormState = { name: "", contact: "", message: "" };

function Contact() {
  const images = useSiteImages();
  const salon = useSalonInfo();
  const visitReveal = useReveal<HTMLElement>();
  const messageReveal = useReveal<HTMLElement>();

  const published = salon !== null && hasDetails(salon.contact);
  const channels = published && salon !== null ? channelsFor(salon.contact) : [];
  const address = published && salon !== null ? salon.contact.address : contact.address;
  const hours = salon !== null && salon.hours.length > 0 ? hoursRows(salon.hours) : contact.hours;
  const closures = salon?.closures ?? [];

  const [form, setForm] = useState<FormState>(emptyForm);
  const [sent, setSent] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState("");

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (sending) return;

    setSending(true);
    setError("");

    try {
      await sendMessage({
        name: form.name.trim(),
        contact: form.contact.trim(),
        message: form.message.trim(),
      });
      setSent(true);
      setForm(emptyForm);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : "The message could not be sent. Please try again.",
      );
    } finally {
      setSending(false);
    }
  };
  return (
    <SiteLayout>
      <section className="contact-hero">
        <div className="section-wrap contact-grid">
          <div className="reveal-up">
            <span className="overline">Contact</span>
            <h1>
              Let's talk
              <br />
              <em>beauty.</em>
            </h1>
            <p className="contact-lead">
              Questions, ideas or special requests — reach us the way that suits you.
            </p>
            {channels.length === 0 && (
              <a className="editorial-link contact-write" href="#message">
                Send us a message <span aria-hidden="true">→</span>
              </a>
            )}
            <ul className="channels" hidden={channels.length === 0}>
              {channels.map(({ icon: Icon, label, value, href }) => (
                <li key={label}>
                  <Icon size={18} strokeWidth={1.4} />
                  <span className="ch-label">{label}</span>
                  {href === undefined ? (
                    <span className="ch-value">{value}</span>
                  ) : (
                    <a className="ch-value" href={href} rel="noreferrer">
                      {value}
                    </a>
                  )}
                </li>
              ))}
            </ul>
          </div>
          <figure className="contact-image img-frame reveal">
            <img src={images.skin.url} alt={images.skin.alt} />
          </figure>
        </div>
      </section>

      <section
        ref={visitReveal.ref}
        className={`visit${address === "" ? " visit-solo" : ""}${visitReveal.revealed ? " is-revealed" : ""}`}
      >
        <noscript>
          <style>{`.visit-eyebrow,.visit-line,.hours,.closures,.visit .editorial-link,.feeling-rule{opacity:1!important;animation:none!important;transform:none!important}.visit-bloom,.visit-map,.visit-chip{opacity:1!important}`}</style>
        </noscript>
        <span className="visit-bloom" aria-hidden="true" />
        <div className="section-wrap visit-inner">
          {address !== "" && (
            <figure className="visit-map">
              <span className="visit-map-fallback" aria-hidden="true">
                <MapPin size={30} strokeWidth={1.2} />
                <span>{address}</span>
              </span>
              <iframe
                title="Wiz Beauty Salon on Google Maps"
                src={contact.mapEmbed}
                allowFullScreen
                loading="lazy"
                referrerPolicy="strict-origin-when-cross-origin"
              />
              <figcaption className="visit-chip">
                <span className="visit-chip-pin" aria-hidden="true">
                  <MapPin size={16} strokeWidth={1.6} />
                </span>
                <span className="visit-chip-text">
                  <strong>{address}</strong>
                  <a
                    href="https://www.google.com/maps/search/?api=1&query=Wiz+Beauty+Salon"
                    target="_blank"
                    rel="noreferrer"
                  >
                    Get directions <span aria-hidden="true">→</span>
                  </a>
                </span>
              </figcaption>
            </figure>
          )}
          <div className="visit-copy">
            <p className="visit-eyebrow">
              <span className="feeling-rule" aria-hidden="true" />
              <span className="visit-eyebrow-text">Visit us</span>
              <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
            </p>
            <h2 className="visit-title">
              <span className="feeling-line visit-line">Location</span>
              <span className="feeling-line feeling-line-em visit-line">&amp; hours.</span>
            </h2>
            {hours.length > 0 && (
              <dl className="hours">
                {hours.map((h) => (
                  <div key={h.day}>
                    <dt>
                      <Clock size={14} /> {h.day}
                    </dt>
                    <dd>{h.time}</dd>
                  </div>
                ))}
              </dl>
            )}
            {closures.length > 0 && (
              <ul className="closures">
                {closures.slice(0, 3).map((closure) => (
                  <li key={`${closure.from}-${closure.to}`}>Closed {closureLabel(closure)}</li>
                ))}
              </ul>
            )}
            <EditorialLink to="/booking">Book an appointment</EditorialLink>
          </div>
        </div>
      </section>

      <section
        ref={messageReveal.ref}
        className={`message-section${messageReveal.revealed ? " is-revealed" : ""}`}
        id="message"
      >
        <noscript>
          <style>{`.message-eyebrow,.message-line,.wiz-form,.form-done,.feeling-rule{opacity:1!important;animation:none!important;transform:none!important}.message-bloom{opacity:1!important}`}</style>
        </noscript>
        <span className="message-bloom" aria-hidden="true" />
        <div className="section-wrap message-inner">
          <div className="message-copy">
            <p className="message-eyebrow">
              <span className="feeling-rule" aria-hidden="true" />
              <span className="message-eyebrow-text">Send a message</span>
              <span className="feeling-rule feeling-rule-end" aria-hidden="true" />
            </p>
            <h2 className="message-title">
              <span className="feeling-line message-line">Write</span>
              <span className="feeling-line feeling-line-em message-line">to us.</span>
            </h2>
          </div>
          {sent ? (
            <div className="form-done" role="status">
              <h3>Thank you.</h3>
              <p>
                Your message is with the salon. We will get back to you by email or phone — usually
                the same day.
              </p>
              <button className="btn btn-ink" onClick={() => setSent(false)}>
                Write another
              </button>
            </div>
          ) : (
            <form className="wiz-form" onSubmit={(e) => void submit(e)}>
              <label>
                Name
                <input
                  required
                  name="name"
                  autoComplete="name"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                />
              </label>
              <label>
                Email or phone
                <input
                  required
                  name="contact"
                  value={form.contact}
                  onChange={(e) => setForm({ ...form, contact: e.target.value })}
                />
              </label>
              <label className="full">
                Message
                <textarea
                  required
                  name="message"
                  rows={4}
                  value={form.message}
                  onChange={(e) => setForm({ ...form, message: e.target.value })}
                />
              </label>

              {error !== "" && (
                <p className="form-error full" role="alert">
                  {error}
                </p>
              )}

              <button type="submit" className="btn btn-ink" disabled={sending}>
                {sending ? "Sending…" : "Send message"}
              </button>
            </form>
          )}
        </div>
      </section>
    </SiteLayout>
  );
}
