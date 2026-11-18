import { createFileRoute, Link } from "@tanstack/react-router";
import { useMemo, useState } from "react";
import { ArrowLeft, ArrowRight, Check, Printer } from "lucide-react";
import { SiteLayout, PageHero } from "@/components/site/SiteChrome";
import { useBookingPolicy } from "@/lib/site-content";
import { useSalonInfo } from "@/lib/site-content";
import { hasDetails, whatsappHref } from "@/lib/salon-api";
import { formatUpdatedOn, useTermsDocument, type TermsDocument } from "@/lib/terms-api";

/**
 * The booking terms, in English and in Amharic.
 *
 * These are not marketing copy: they are the rules a customer agrees to when
 * they press "Request this appointment", and the answers they will want at the
 * counter when something has gone wrong. Two things follow from that.
 *
 * 1. The change/cancel window is never typed here. It comes from the salon's
 *    own setting (Settings → Booking rules), so this page cannot promise 24
 *    hours while the salon needs 48. The same is true of the text itself: since
 *    2026 the wording is edited in the panel (Administration → Booking terms)
 *    and arrives from `/api/v1/terms`, with the document below kept as the
 *    fallback for a slow connection or an installation that has not published
 *    yet. Nothing here is a second source of truth — it is what shows while
 *    the first one is not there.
 * 2. Both languages are complete versions of the same agreement, not a summary
 *    in one and the detail in the other. The Amharic text says what the English
 *    text says; the toggle switches the whole document, and a `?lang=am` link
 *    opens it in Amharic.
 */

export const Route = createFileRoute("/booking_/information")({
  head: () => ({
    meta: [
      { title: "Booking information and terms | WIZ Beauty Salon" },
      {
        name: "description",
        content:
          "Appointment terms for WIZ Beauty Salon: how bookings are confirmed, the change and cancellation window, late arrivals, no-shows, health questions, photography and privacy — in English and Amharic.",
      },
      { property: "og:title", content: "Booking information and terms | WIZ Beauty Salon" },
      {
        property: "og:description",
        content:
          "Read the terms before you book: confirmation, the change window, late arrivals, no-shows and privacy.",
      },
      { property: "og:type", content: "article" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  // `?lang=am` opens the document in Amharic — the manage link and the
  // confirmation email can point at the reader's own language.
  validateSearch: (search: Record<string, unknown>): { lang: Lang } => ({
    lang: search["lang"] === "am" ? "am" : "en",
  }),
  component: BookingInformation,
});

type Lang = "en" | "am";

type Clause = { en: string; am: string };

type Section = {
  id: string;
  en: string;
  am: string;
  clauses: Clause[];
};

/**
 * The document the site shipped with — the fallback.
 *
 * This is what a reader sees while `/api/v1/terms` is in flight, when it fails,
 * and on an installation whose panel has never saved a section. It is kept in
 * step with the seeded rows in the panel's terms migration, so a salon that
 * never opens the editor still reads the same agreement.
 *
 * Written in pairs so a reader can never be shown a clause in one language
 * without knowing its counterpart exists.
 */
function bundledSections(policy: {
  has_cut_off: boolean;
  hours_label: string;
  summary: string;
}): Section[] {
  const windowEn = policy.has_cut_off
    ? `${policy.hours_label} before the appointment`
    : "any time before the appointment starts";
  const windowAm = policy.has_cut_off
    ? `ከቀጠሮው በፊት ${policy.hours_label}`
    : "ቀጠሮው ከመጀመሩ በፊት በማንኛውም ሰዓት";

  return [
    {
      id: "about",
      en: "About these terms",
      am: "ስለዚህ ስምምነት",
      clauses: [
        {
          en: "These terms cover every appointment booked with WIZ Beauty Salon, whether you book on this website, by phone, on WhatsApp or in person at the salon.",
          am: "ይህ ስምምነት በ WIZ የውበት ሳሎን የሚያዙትን ሁሉንም ቀጠሮዎች ይሸፍናል፤ በዚህ ድረ-ገጽ፣ በስልክ፣ በዋትስአፕ ወይም በሳሎኑ ላይ በአካል ቢያዙም።",
        },
        {
          en: "Booking an appointment means you accept these terms. If anything here is unclear, ask us before you book — we would rather answer a question now than disappoint you later.",
          am: "ቀጠሮ ማስያዝ ማለት ይህን ስምምነት መቀበልዎ ማለት ነው። አንድ ነገር ግልጽ ካልሆነ ከመያዝዎ በፊት ይጠይቁን — አሁን መመለስ ከበኋላ ማሳዘን ይሻላል።",
        },
        {
          en: "We may update these terms as the salon changes. The version in force is the one published here on the day of your appointment.",
          am: "ሳሎኑ ሲሻሻል ይህን ስምምነት ልናስተካክል እንችላለን። በሥራ ላይ ያለው ቅጂ በቀጠሮዎ ቀን በዚህ ገጽ ላይ የታተመው ነው።",
        },
      ],
    },
    {
      id: "booking",
      en: "Making a booking",
      am: "ቀጠሮ ማስያዝ",
      clauses: [
        {
          en: "A booking made on this website is a request. It becomes an appointment only when the salon confirms it — by phone, by message, or by a confirmation email.",
          am: "በዚህ ድረ-ገጽ የሚደረግ ቦታ ማስያዝ ጥያቄ ነው። ሳሎኑ በስልክ፣ በመልእክት ወይም በማረጋገጫ ኢሜል ሲያረጋግጥ ብቻ ቀጠሮ ይሆናል።",
        },
        {
          en: "Every booking gets a reference number. It is the fastest way for us to find you, so keep it — it is printed in your confirmation email and on your manage link.",
          am: "እያንዳንዱ ቀጠሮ የራሱ የማጣቀሻ ቁጥር አለው። እርስዎን ለማግኘት ፈጣኑ መንገድ ስለሆነ ይያዙት — በማረጋገጫ ኢሜልዎና በማስተዳደሪያ ሊንክዎ ላይ ተጽፎ ይገኛል።",
        },
        {
          en: "The manage link we send you is private and expires 24 hours after it is sent. Anyone who has it can move or cancel the appointment, so please do not forward it. You can always ask for a fresh link from the Manage booking page — that link is the only credential, and we will not ask you for a password.",
          am: "የምንልክልዎ የማስተዳደሪያ ሊንክ የግል ነው፤ ከተላከ 24 ሰዓት በኋላ ጊዜው ያበቃል። ያለው ሰው ሁሉ ቀጠሮውን ማንቀሳቀስ ወይም መሰረዝ ይችላል፤ ስለዚህ አታስተላልፉት። በየጊዜው ከ“ቀጠሮ ያስተዳድሩ” ገጽ አዲስ ሊንክ መጠየቅ ይችላሉ — ሊንኩ ብቻውን መታወቂያዎ ነው፤ የይለፍ ቃል አንጠይቅም።",
        },
      ],
    },
    {
      id: "window",
      en: "Changing or cancelling",
      am: "ቀጠሮ መለወጥ ወይም መሰረዝ",
      clauses: [
        {
          en: `You may change or cancel your appointment yourself, without asking us, up to ${windowEn}. The salon's current rule is: “${policy.summary}”`,
          am: `ቀጠሮዎን በራስዎ ${windowAm} መለወጥ ወይም መሰረዝ ይችላሉ። የሳሎኑ የአሁኑ ደንብ፦ “${policy.summary}” ነው።`,
        },
        {
          en: "Use the manage link in your email. You can move the appointment as often as you need inside the window; each move releases your old time back to the salon.",
          am: "በኢሜልዎ የሚገኘውን የማስተዳደሪያ ሊንክ ይጠቀሙ። በዚህ ጊዜ ውስጥ ቀጠሮውን በፈለጉት ያህል ማንቀሳቀስ ይችላሉ፤ በእያንዳንዱ ለውጥ የቀድሞው ሰዓትዎ ወደ ሳሎኑ ይመለሳል።",
        },
        {
          en: "After the window closes, the appointment is the salon's to move. Call us and we will do what we can, but we cannot promise to fill a slot that is already too close.",
          am: "የጊዜ ገደቡ ካለፈ በኋላ ቀጠሮውን ማንቀሳቀስ የሳሎኑ ሥራ ይሆናል። ይደውሉልን፤ የተቻለንን እናደርጋለን፤ ሆኖም በጣም የቀረበ ሰዓት መሙላት ዋስትና ልንሰጥ አንችልም።",
        },
      ],
    },
    {
      id: "late",
      en: "Arriving late",
      am: "ዘግይተው ሲመጡ",
      clauses: [
        {
          en: "Please arrive five minutes before your appointment. If you are running late, call or message us — we will hold your time for fifteen minutes.",
          am: "ከቀጠሮዎ አምስት ደቂቃ በፊት ይምጡ። ከመዘግየትዎ በፊት ይደውሉ ወይም ይጻፉልን — ሰዓትዎን ለአስራ አምስት ደቂቃ እንይዛለን።",
        },
        {
          en: "After fifteen minutes we may need to shorten the service so the customers after you are not kept waiting. We will always tell you what we can still do before we begin.",
          am: "ከአስራ አምስት ደቂቃ በኋላ ከእርስዎ በኋላ ያሉ ደንበኞች እንዳይቆዩ አገልግሎቱን ማሳጠር ሊያስፈልገን ይችላል። ከመጀመራችን በፊት ምን ማድረግ እንደምንችል ሁልጊዜ እንነግርዎታለን።",
        },
        {
          en: "If you arrive more than thirty minutes late we may have to rebook you, because the time is no longer there to give.",
          am: "ከሠላሳ ደቂቃ በላይ ዘግይተው ከመጡ ቀጠሮውን እንደገና ማስያዝ ሊያስፈልገን ይችላል፤ ምክንያቱም የሚሰጥ ጊዜ ከእንግዲህ አይኖርም።",
        },
      ],
    },
    {
      id: "no-show",
      en: "If you do not come",
      am: "ካልመጡ",
      clauses: [
        {
          en: "A no-show is an appointment nobody attended and nobody cancelled. It costs the stylist the hour and the salon the customer who could have had it.",
          am: "አለመምጣት ማለት ማንም ያልመጣበትና ያልተሰረዘ ቀጠሮ ነው። ለባለሙያው ሰዓቱን፣ ለሳሎኑም ሊያገኘው የነበረውን ደንበኛ ያሳጣል።",
        },
        {
          en: "After two no-shows we may ask you to confirm by phone on the day before we hold another long appointment.",
          am: "ከሁለት ጊዜ አለመምጣት በኋላ ሌላ ረጅም ቀጠሮ ከመያዛችን በፊት በቀኑ በስልክ እንዲያረጋግጡ ልንጠይቅ እንችላለን።",
        },
      ],
    },
    {
      id: "salon",
      en: "When the salon has to change something",
      am: "ሳሎኑ ሲያስተካክል",
      clauses: [
        {
          en: "Sometimes we have to move an appointment: a stylist falls ill, water or power goes off, a closure is declared. We will contact you on the number you gave us as early as we can.",
          am: "አንዳንድ ጊዜ ቀጠሮ ማንቀሳቀስ ይኖርብናል፦ ባለሙያ ሲታመም፣ ውሃ ወይም መብራት ሲቋረጥ፣ የመዘጋት ማስታወቂያ ሲወጣ። በተቻለ ፍጥነት በሰጡን ቁጥር እናገኝዎታለን።",
        },
        {
          en: "You may then take a new time, or cancel without any obligation. Neither choice will be held against you.",
          am: "ከዚያ አዲስ ሰዓት መውሰድ ወይም ያለ ምንም ግዴታ መሰረዝ ይችላሉ። ከሁለቱም ምርጫ በኋላ ምንም ቅሬታ አናስቀምጥም።",
        },
      ],
    },
    {
      id: "health",
      en: "Health, allergies and patch tests",
      am: "ጤና፣ አለርጂ እና የቆዳ ምርመራ",
      clauses: [
        {
          en: "Please tell us before your appointment about allergies, skin conditions, pregnancy, recent surgery, medication or anything that changes how a product might affect you. It is not a formality.",
          am: "ከቀጠሮዎ በፊት ስለ አለርጂ፣ ስለ ቆዳ ችግር፣ ስለ እርግዝና፣ ስለ ቅርብ ጊዜ ቀዶ ጥገና፣ ስለ መድኃኒት ወይም ምርቱ በእርስዎ ላይ ሊያሳድረው ስለሚችለው ተጽዕኖ ይንገሩን። ይህ ሥርዓት አይደለም።",
        },
        {
          en: "For colour and chemical services we may ask for a patch test at least 48 hours before your first appointment with a new product. We will not proceed without one if we believe it is needed.",
          am: "ለቀለምና ለኬሚካል አገልግሎቶች አዲስ ምርት ለመጀመሪያ ጊዜ ከሚጠቀሙበት ቀጠሮ ቢያንስ 48 ሰዓት በፊት የቆዳ ምርመራ ልንጠይቅ እንችላለን። አስፈላጊ ነው ብለን ካመንን ያለ ምርመራ አንቀጥልም።",
        },
        {
          en: "We follow the manufacturer's instructions for every product. If a service cannot be done safely we will say so and suggest something else — the answer may be no.",
          am: "ለእያንዳንዱ ምርት የአምራቹን መመሪያ እንከተላለን። አገልግሎቱ ያለ አደጋ ሊከናወን ካልቻለ እንነግርዎታለን፤ ሌላ አማራጭም እንጠቁማለን — መልሱ “አይቻልም” ሊሆን ይችላል።",
        },
      ],
    },
    {
      id: "guests",
      en: "Children and guests",
      am: "ልጆች እና እንግዶች",
      clauses: [
        {
          en: "The salon is a calm place with hot tools, chemicals and sharp implements. Please come with whoever you need, but children must stay beside you and are your responsibility.",
          am: "ሳሎኑ ትኩስ መሣሪያዎች፣ ኬሚካሎችና ሹል መሣሪያዎች ያሉበት ረጋ ያለ ቦታ ነው። አስፈላጊ የሆነውን ሰው አስከትለው ይምጡ፤ ልጆች ግን ከእርስዎ አጠገብ መቀመጥ አለባቸው፤ ኃላፊነቱም የእርስዎ ነው።",
        },
        {
          en: "If you are bringing a guest to a long appointment, please tell us when you book so we can seat them properly.",
          am: "ለረጅም ቀጠሮ እንግድ ካስከተሉ በሚያዙበት ጊዜ ይንገሩን፤ በአግባቡ ልናስቀምጣቸው እንችል ዘንድ።",
        },
      ],
    },
    {
      id: "photography",
      en: "Photography and social media",
      am: "ፎቶግራፍ እና ማህበራዊ ሚዲያ",
      clauses: [
        {
          en: "We photograph finished work for our own portfolio and social media. We will always ask first, and we will not photograph your face or publish anything that identifies you without your clear permission.",
          am: "የተጠናቀቀውን ሥራ ለራሳችን ፖርትፎሊዮና ለማህበራዊ ሚዲያ እናነሳለን። ሁልጊዜ አስቀድመን እንጠይቃለን፤ ግልጽ ፈቃድ ካልሰጡ ፊትዎን አናነሳም፤ ማንነትዎን የሚገልጽ ነገርም አናትም።",
        },
        {
          en: "Saying no changes nothing about your appointment or how you are treated. If you change your mind later, tell us and we will remove the picture.",
          am: "“አይ” ማለት በቀጠሮዎ ወይም በእርስዎ ላይ በሚደረግ አያያዝ ምንም አይለውጥም። በኋላ ሐሳብዎን ከቀየሩ ይንገሩን፤ ፎቶውን እናስወግዳለን።",
        },
      ],
    },
    {
      id: "privacy",
      en: "Your information",
      am: "የእርስዎ መረጃ",
      clauses: [
        {
          en: "We keep your name, phone number, email address (if you gave one) and the notes needed to do your service. We use them to confirm bookings, remind you, and keep a record of anything that affects your hair or skin.",
          am: "ስምዎን፣ ስልክ ቁጥርዎን፣ ኢሜልዎን (ከሰጡ) እና አገልግሎቱን ለመስጠት የሚያስፈልጉ ማስታወሻዎችን እናስቀምጣለን። ቀጠሮ ለማረጋገጥ፣ ለማስታወሻ እና በጸጉርዎ ወይም በቆዳዎ ላይ ተጽዕኖ የሚያሳድር ነገር ለመመዝገብ እንጠቀምባቸዋለን።",
        },
        {
          en: "We do not sell your details and we do not share them, except with the services that send our email — which only carry the message, not a marketing list.",
          am: "መረጃዎን አንሸጥም፤ ኢሜላችንን ከሚልኩ አገልግሎቶች በስተቀር ለማንም አናካፍልም — እነሱም መልእክቱን ብቻ ይይዛሉ፤ የማስታወቂያ ዝርዝር አይደለም።",
        },
        {
          en: "You may ask us at any time what we hold about you, and ask us to correct or delete it. Ask at the salon or use the contact details at the foot of this page.",
          am: "ስለ እርስዎ ያለንን መረጃ በማንኛውም ጊዜ መጠየቅ፣ እንዲስተካከል ወይም እንዲሰረዝ መጠየቅ ይችላሉ። በሳሎኑ ይጠይቁ ወይም በዚህ ገጽ ግርጌ ያለውን የመገኛ መረጃ ይጠቀሙ።",
        },
      ],
    },
    {
      id: "complaints",
      en: "If something is not right",
      am: "ችግር ካለ",
      clauses: [
        {
          en: "Tell us within seven days if you are not happy with a service. Bring your reference if you have it and show us what concerns you — we need to see the work to put it right.",
          am: "በአገልግሎት ካልተደሰቱ በሰባት ቀናት ውስጥ ይንገሩን። የማጣቀሻ ቁጥሩ ካለዎት ይዘው ይምጡ፤ የሚያሳስብዎን ያሳዩን — ለማስተካከል ሥራውን ማየት ያስፈልገናል።",
        },
        {
          en: "We will offer a correction, or a redo of the service concerned, where that is the fair answer. What we will not do is argue about it.",
          am: "ማስተካከያ ወይም ሥራውን እንደገና ማድረግ ፍትሐዊው መልስ ሲሆን እናቀርባለን። የማናደርገው ነገር ስለሱ መከራከር ነው።",
        },
      ],
    },
    {
      id: "law",
      en: "The law that applies",
      am: "የሚመለከተው ሕግ",
      clauses: [
        {
          en: "These terms are governed by the laws of the Federal Democratic Republic of Ethiopia. If a dispute cannot be settled between us, it belongs to the courts of Addis Ababa.",
          am: "ይህ ስምምነት በኢትዮጵያ ፌዴራላዊ ዲሞክራሲያዊ ሪፐብሊክ ሕጎች ይተዳደራል። ክርክር በመካከላችን ሳይፈታ ከቀረ በአዲስ አበባ ፍርድ ቤቶች ይታያል።",
        },
        {
          en: "If one clause here is found to be unenforceable, the rest of the terms still stand.",
          am: "ከዚህ ስምምነት አንድ አንቀጽ የማይፈጸም ሆኖ ከተገኘ ቀሪው ስምምነት አሁንም ይሠራል።",
        },
      ],
    },
  ];
}

const COPY = {
  en: {
    overline: "Booking information",
    title: "Before you book,",
    titleEm: "know the terms.",
    copy: "How confirmation works, how late you can change your mind, what happens if you are delayed, and what we do with your details — written plainly, in two languages.",
    intro:
      "This is the agreement between you and WIZ Beauty Salon. It applies from the moment you request an appointment. The Amharic version says the same things; switch language above the contents.",
    contents: "Contents",
    lastUpdated: "Last updated",
    back: "Back to booking",
    readAm: "አማርኛ",
    readEn: "English",
    print: "Print or save as PDF",
    contactTitle: "Questions about these terms?",
    contactCopy:
      "Call or message the salon before you book — we would rather answer now than disappoint later.",
    book: "Book an appointment",
    manage: "Manage an existing booking",
    langNote:
      "Both versions are complete. If the two ever differ, the English version and the salon's own records decide.",
    langNoteAm: "ሁለቱም ቅጂዎች ሙሉ ናቸው። ልዩነት ካለ የእንግሊዝኛው ቅጂና የሳሎኑ መዝገብ ይወስናሉ።",
  },
  am: {
    overline: "የቀጠሮ መረጃ",
    title: "ከመያዝዎ በፊት፣",
    titleEm: "ደንቦቹን ይወቁ።",
    copy: "ማረጋገጫው እንዴት እንደሚሠራ፣ ሐሳብዎን እስከ መቼ መቀየር እንደሚችሉ፣ ሲዘገዩ ምን እንደሚሆንና መረጃዎን ምን እንደምናደርግበት — በቀላል ቋንቋ፣ በሁለት ቋንቋ።",
    intro:
      "ይህ በእርስዎና በ WIZ የውበት ሳሎን መካከል ያለ ስምምነት ነው። ቀጠሮ ከጠየቁበት ጊዜ ጀምሮ ይሠራል። የአማርኛው ቅጂም ተመሳሳዩን ይናገራል፤ ቋንቋውን ከይዘቱ በላይ ይቀይሩ።",
    contents: "ይዘት",
    lastUpdated: "መጨረሻ የተሻሻለው",
    back: "ወደ ቀጠሮ ማስያዝ ተመለስ",
    readAm: "አማርኛ",
    readEn: "English",
    print: "አትም ወይም PDF አድርገው ያስቀምጡ",
    contactTitle: "ስለ ደንቦቹ ጥያቄ አለዎት?",
    contactCopy: "ከመያዝዎ በፊት ይደውሉ ወይም ይጻፉልን — በኋላ ከማሳዘን አሁን መመለስ እንመርጣለን።",
    book: "ቀጠሮ ይያዙ",
    manage: "ያለውን ቀጠሮ ያስተዳድሩ",
    langNote: "ሁለቱም ቅጂዎች ሙሉ ናቸው። ልዩነት ካለ የእንግሊዝኛው ቅጂና የሳሎኑ መዝገብ ይወስናሉ።",
    langNoteAm: "ሁለቱም ቅጂዎች ሙሉ ናቸው። ልዩነት ካለ የእንግሊዝኛው ቅጂና የሳሎኑ መዝገብ ይወስናሉ።",
  },
} as const;

/**
 * The panel's version, in the shape this page renders.
 *
 * Returns null when nothing has been published, which is the only case where the
 * bundled document is used — an empty answer is treated as "no answer" rather
 * than as a page with no terms.
 */
function publishedSections(document: TermsDocument | null): Section[] | null {
  if (document === null || document.sections.length === 0) return null;

  return document.sections.map((section) => ({
    id: section.id,
    en: section.title.en,
    am: section.title.am,
    clauses: section.clauses,
  }));
}

function BookingInformation() {
  const policy = useBookingPolicy();
  const salon = useSalonInfo();
  const { lang: langParam } = Route.useSearch();
  const [lang, setLang] = useState<Lang>(langParam);
  const published = useTermsDocument();
  const sections = useMemo(
    () => publishedSections(published) ?? bundledSections(policy),
    [published, policy],
  );
  const updatedOn = formatUpdatedOn(published?.updatedOn ?? null, lang);
  const copy = COPY[lang];

  const details = salon?.contact;
  const hasContact = details !== undefined && hasDetails(details);

  return (
    <SiteLayout>
      <PageHero
        variant="terms"
        overline={copy.overline}
        title={
          <>
            {copy.title} <em>{copy.titleEm}</em>
          </>
        }
        copy={copy.copy}
      />

      <div className="terms-page">
        <div className="section-wrap terms-shell">
          {/* Contents: a legal page is read by jumping, not by scrolling. */}
          <nav className="terms-toc" aria-label={copy.contents}>
            <p className="terms-toc__label">{copy.contents}</p>
            <ol>
              {sections.map((section, index) => (
                <li key={section.id}>
                  <a href={`#${section.id}`}>
                    <span className="terms-toc__num">{String(index + 1).padStart(2, "0")}</span>
                    {section[lang]}
                  </a>
                </li>
              ))}
            </ol>
          </nav>

          <article className={`terms-doc terms-doc--${lang}`} lang={lang === "am" ? "am" : "en"}>
            <div className="terms-langbar">
              <div className="terms-langbar__switch" role="group" aria-label="Language">
                <button
                  type="button"
                  className={lang === "en" ? "is-active" : ""}
                  aria-pressed={lang === "en"}
                  onClick={() => setLang("en")}
                >
                  English
                </button>
                <button
                  type="button"
                  className={lang === "am" ? "is-active" : ""}
                  aria-pressed={lang === "am"}
                  onClick={() => setLang("am")}
                >
                  አማርኛ
                </button>
              </div>
              <button type="button" className="terms-print" onClick={() => window.print()}>
                <Printer size={14} aria-hidden="true" />
                {copy.print}
              </button>
            </div>

            <p className="terms-lede">{copy.intro}</p>
            <p className="terms-langnote">
              <Check size={13} aria-hidden="true" /> {copy.langNote}
            </p>

            {sections.map((section, index) => (
              <section key={section.id} id={section.id} className="terms-section">
                <h2 className="terms-heading">
                  <span className="terms-heading__num">{String(index + 1).padStart(2, "0")}</span>
                  {section[lang]}
                </h2>
                <ol className="terms-clauses">
                  {section.clauses.map((clause, i) => (
                    <li key={i}>
                      <span className="terms-clause__num" aria-hidden="true">
                        {index + 1}.{i + 1}
                      </span>
                      <span>{clause[lang]}</span>
                    </li>
                  ))}
                </ol>
              </section>
            ))}

            <p className="terms-foot">
              {copy.lastUpdated}:{" "}
              {updatedOn === "" ? (
                // No date from the panel: the bundled copy's own date, written
                // out rather than left blank.
                <time dateTime="2026-11-17">17 November 2026</time>
              ) : (
                <time dateTime={published?.updatedOn ?? undefined}>{updatedOn}</time>
              )}
            </p>

            <div className="terms-actions">
              <Link to="/booking" className="btn btn-ink">
                {copy.book} <ArrowRight size={16} />
              </Link>
              <Link to="/booking/recover" className="btn btn-outline">
                {copy.manage}
              </Link>
              <Link to="/booking" className="terms-back">
                <ArrowLeft size={14} /> {copy.back}
              </Link>
            </div>
          </article>
        </div>

        <section className="terms-contact">
          <div className="section-wrap terms-contact__inner">
            <div>
              <h2>{copy.contactTitle}</h2>
              <p>{copy.contactCopy}</p>
            </div>
            <div className="terms-contact__actions">
              {hasContact && details.phone !== "" && (
                <a className="btn btn-outline" href={`tel:${details.phone.replace(/[^\d+]/g, "")}`}>
                  {details.phone}
                </a>
              )}
              {hasContact && whatsappHref(details.whatsapp) !== "" && (
                <a
                  className="btn btn-outline"
                  href={whatsappHref(details.whatsapp)}
                  rel="noreferrer"
                  target="_blank"
                >
                  WhatsApp
                </a>
              )}
              <Link to="/contact" className="btn btn-ink">
                Contact the salon <ArrowRight size={16} />
              </Link>
            </div>
          </div>
        </section>
      </div>
    </SiteLayout>
  );
}
