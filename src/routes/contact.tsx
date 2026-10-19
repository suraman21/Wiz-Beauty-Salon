import { createFileRoute } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import { Clock, Mail, MapPin, MessageCircle, Phone, Instagram, Music2 } from "lucide-react";
import { EditorialLink, SiteLayout } from "@/components/site/SiteChrome";
import { contact, images } from "@/data/wiz";
import { ApiError } from "@/lib/api";
import { sendMessage } from "@/lib/contact-api";

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

const channels = [
  { icon: Phone, label: "Phone", value: contact.phone },
  { icon: MessageCircle, label: "WhatsApp", value: contact.whatsapp },
  { icon: Mail, label: "Email", value: contact.email },
  { icon: Instagram, label: "Instagram", value: contact.instagram },
  { icon: Music2, label: "TikTok", value: contact.tiktok },
];

type FormState = { name: string; contact: string; message: string };

const emptyForm: FormState = { name: "", contact: "", message: "" };

function Contact() {
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
            <ul className="channels">
              {channels.map(({ icon: Icon, label, value }) => (
                <li key={label}>
                  <Icon size={18} strokeWidth={1.4} />
                  <span className="ch-label">{label}</span>
                  <span className="ch-value">{value}</span>
                </li>
              ))}
            </ul>
            <span className="demo-note">
              All contact details are placeholders until WIZ provides them.
            </span>
          </div>
          <figure className="contact-image img-frame reveal">
            <img src={images.skin} alt="Demo skincare portrait" />
          </figure>
        </div>
      </section>

      <section className="visit section-wrap">
        <div
          className="map-placeholder"
          role="img"
          aria-label="Map placeholder — location to be added"
        >
          <MapPin size={30} strokeWidth={1.2} />
          <p>Map will appear here</p>
          <span>{contact.address}</span>
        </div>
        <div className="visit-copy">
          <span className="overline">Visit us</span>
          <h2>
            Location
            <br />
            <em>&amp; hours.</em>
          </h2>
          <p className="visit-address">
            <MapPin size={16} /> {contact.address}
          </p>
          <dl className="hours">
            {contact.hours.map((h) => (
              <div key={h.day}>
                <dt>
                  <Clock size={14} /> {h.day}
                </dt>
                <dd>{h.time}</dd>
              </div>
            ))}
          </dl>
          <EditorialLink to="/booking">Book an appointment</EditorialLink>
        </div>
      </section>

      <section className="message-section">
        <div className="section-wrap message-inner">
          <div>
            <span className="overline">Send a message</span>
            <h2>
              Write
              <br />
              <em>to us.</em>
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
