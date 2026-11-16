import { createFileRoute, Link } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import { Clock, Mail, MapPin, MessageCircle, Phone, Instagram, Music2 } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { ClosingCta, PageHead, SiteLayout } from "@/components/site/SiteChrome";
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

  const published = salon !== null && hasDetails(salon.contact);
  const channels = published && salon !== null ? channelsFor(salon.contact) : [];
  const address = published && salon !== null ? salon.contact.address : contact.address;
  const hours = salon !== null && salon.hours.length > 0 ? hoursRows(salon.hours) : contact.hours;
  const closures = salon?.closures ?? [];

  const hasAnything = channels.length > 0 || hours.length > 0 || address !== "";

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
      <PageHead
        title={
          <>
            Talk to
            <br />
            the salon.
          </>
        }
        amharic="ያግኙን"
        intro="Questions about a service, a price or whether a style will suit your hair — ask before you book. Phone and WhatsApp reach us fastest."
      />

      <section className={`wz-wrap wz-contact${hasAnything ? "" : " wz-contact-solo"}`}>
        {hasAnything && (
          <div>
            <h2 className="wz-h2">Reach us</h2>

            {channels.length > 0 ? (
              <dl className="wz-contact-list wz-mt-md">
                {channels.map(({ label, value, href }) => (
                  <div className="wz-contact-row" key={label}>
                    <dt>{label}</dt>
                    <dd>
                      {href === undefined ? (
                        value
                      ) : (
                        <a href={href} rel="noreferrer">
                          {value}
                        </a>
                      )}
                    </dd>
                  </div>
                ))}
                {address !== "" && (
                  <div className="wz-contact-row">
                    <dt>Address</dt>
                    <dd>{address}</dd>
                  </div>
                )}
              </dl>
            ) : null}

            {hours.length > 0 && (
              <>
                <h3
                  className="wz-mt-lg"
                  style={{ fontFamily: "var(--wz-sans)", fontSize: "0.9375rem", fontWeight: 600 }}
                >
                  Opening hours
                </h3>
                <dl className="wz-contact-list wz-mt-sm">
                  {hours.map((row) => (
                    <div className="wz-contact-row" key={row.day}>
                      <dt>{row.day}</dt>
                      <dd>{row.time}</dd>
                    </div>
                  ))}
                </dl>
              </>
            )}

            {closures.length > 0 && (
              <p className="wz-note wz-mt-md">
                {closures
                  .slice(0, 3)
                  .map((closure) => `Closed ${closureLabel(closure)}`)
                  .join(". ")}
                .
              </p>
            )}
          </div>
        )}

        <div id="message">
          <h2 className="wz-h2">Write to us</h2>
          <p className="wz-prose wz-mt-sm">
            One message is enough. Tell us what you have in mind and when you would like to come in.
          </p>

          {sent ? (
            <div className="wz-form-done" role="status">
              <h3>Message sent.</h3>
              <p>It is with the salon. We reply by email or phone, usually the same day.</p>
              <button type="button" className="wz-btn wz-btn-ghost" onClick={() => setSent(false)}>
                Write another
              </button>
            </div>
          ) : (
            <form className="wz-form" onSubmit={(event) => void submit(event)}>
              <label>
                Name
                <input
                  required
                  name="name"
                  autoComplete="name"
                  value={form.name}
                  onChange={(event) => setForm({ ...form, name: event.target.value })}
                />
              </label>
              <label>
                Email or phone
                <input
                  required
                  name="contact"
                  value={form.contact}
                  onChange={(event) => setForm({ ...form, contact: event.target.value })}
                />
              </label>
              <label className="wz-form-full">
                Message
                <textarea
                  required
                  name="message"
                  rows={5}
                  value={form.message}
                  onChange={(event) => setForm({ ...form, message: event.target.value })}
                />
              </label>

              {error !== "" && (
                <p className="wz-form-error wz-form-full" role="alert">
                  {error}
                </p>
              )}

              <div className="wz-form-full">
                <button type="submit" className="wz-btn" disabled={sending}>
                  {sending ? "Sending…" : "Send message"}
                </button>
              </div>
            </form>
          )}

          <p className="wz-meta wz-mt-md">
            Booking an appointment is faster than messaging — the date and time are confirmed
            straight away.
          </p>
          <p className="wz-mt-sm">
            <Link to="/booking" className="wz-link">
              Book an appointment
            </Link>
          </p>
        </div>
      </section>

      <ClosingCta
        title={
          <>
            Not sure what to ask for?
            <br />
            Say it in your own words.
          </>
        }
        copy="You do not need the name of the service. Describe what you want in the booking notes and we will work it out together when you arrive."
      />
    </SiteLayout>
  );
}
