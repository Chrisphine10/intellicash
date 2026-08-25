import React from "react";
import {
  FIELD_SUPPORT_EMAIL_HREF,
  SUPPORT_EMAIL,
  SUPPORT_EMAIL_HREF,
  SUPPORT_PHONE,
  SUPPORT_PHONE_HREF
} from "@/lib/contact";
import Link from "next/link";
import {
  ArrowRight,
  BadgeCheck,
  Banknote,
  Bot,
  Building2,
  CalendarCheck,
  CircleHelp,
  ClipboardList,
  Download,
  HandCoins,
  Landmark,
  Leaf,
  Mail,
  MapPinned,
  MegaphoneHornIcon,
  NetworkNodesIcon,
  PackagePlus,
  Phone,
  RocketLaunchIcon,
  Send,
  ShieldCheck,
  Smartphone,
  TrendingUpChartIcon,
  UsersRound
} from "@/lib/theme-icons";
import { PublicSiteFooter } from "../components/public-site-footer";
import { PublicSiteHeader } from "../components/public-site-header";
import { IntelliStoreSection } from "../components/intelli-store-section";
import { GroupRegistrationSection } from "../components/group-registration-section";

const playStoreUrl =
  "https://play.google.com/store/apps/details?id=com.intellicash.app";

type IllustrationKind =
  | "champions"
  | "enterprise"
  | "groups"
  | "partners"
  | "lenders"
  | "training"
  | "finance"
  | "payments"
  | "banking"
  | "marketing"
  | "ai"
  | "web3"
  | "reports"
  | "map"
  | "package"
  | "trust"
  | "growth";

const illustrationIcons: Record<
  IllustrationKind,
  React.ComponentType<{ className?: string; size?: number | string }>
> = {
  ai: Bot,
  banking: Building2,
  champions: RocketLaunchIcon,
  enterprise: Leaf,
  finance: HandCoins,
  groups: UsersRound,
  growth: TrendingUpChartIcon,
  lenders: Banknote,
  map: MapPinned,
  marketing: MegaphoneHornIcon,
  package: PackagePlus,
  partners: Landmark,
  payments: Smartphone,
  reports: ClipboardList,
  training: CalendarCheck,
  trust: ShieldCheck,
  web3: NetworkNodesIcon
};

const operatingSignals = [
  { label: "Works without internet", illustration: "champions" },
  { label: "Savings, loans and shares", illustration: "finance" },
  { label: "Three keys to open a meeting", illustration: "trust" },
  { label: "Statements you can share", illustration: "reports" },
  { label: "Five languages", illustration: "training" }
] as const satisfies ReadonlyArray<{ label: string; illustration: IllustrationKind }>;

const partnerLogos = [
  {
    name: "The Coca-Cola Foundation",
    href: "https://www.coca-colacompany.com/shared-future/coca-cola-foundation",
    src: "/partners/coca-cola-foundation.jpg",
    programmes: ["Grants for small enterprises", "Training and field support"]
  },
  {
    name: "County Government of Embu",
    href: "https://embu.go.ke/",
    src: "/partners/embu-county-government.png",
    programmes: ["Registering groups in the county", "Support for farming businesses"]
  },
  {
    name: "Rainforest Alliance",
    href: "https://www.rainforest-alliance.org/",
    src: "/partners/rainforest-alliance.png",
    programmes: ["Farming that works with the climate", "Getting produce to market"]
  },
  {
    name: "Intelli-Wealth",
    href: "https://intelliwealth.org/",
    src: "/partners/intelli-wealth.png",
    programmes: ["The Intelli-Cash app", "Helping enterprises reach customers"]
  }
];

const audienceCards = [
  {
    title: "Your meetings, written as they happen",
    text: "Attendance, savings, shares, fines and loans are recorded in the room, in the order a meeting actually runs. Nothing is written up afterwards from memory.",
    illustration: "groups"
  },
  {
    title: "Every member's savings, always to hand",
    text: "Any member can see what they have saved, what they hold in shares, and what they still owe — without waiting for the book to be opened.",
    illustration: "finance"
  },
  {
    title: "Loans the group can follow",
    text: "Who borrowed, how much, what has come back and what is still out. The figures update themselves as repayments are recorded.",
    illustration: "lenders"
  },
  {
    title: "A share-out that adds up",
    text: "At the end of a cycle the app works out what each member is owed, takes off what they still owe, and shows the arithmetic to the whole group.",
    illustration: "growth"
  },
  {
    title: "Decisions on the record",
    text: "Loans, elections and rule changes are put to a vote and the tally is kept. An election can be secret. One member, one vote.",
    illustration: "trust"
  },
  {
    title: "Proof when you need it",
    text: "A statement for the group or for one member, ready to share or print — for a lender, a partner, or a member who simply wants to see.",
    illustration: "reports"
  }
] as const satisfies ReadonlyArray<{ title: string; text: string; illustration: IllustrationKind }>;

const workflowSteps = [
  {
    title: "Set the group up once",
    text: "Enter the group's name, add your members and say who holds which office. Ten minutes, once — and only this step needs internet.",
    illustration: "groups"
  },
  {
    title: "Open the meeting with three keys",
    text: "Three officials each enter their own 4-digit PIN before the book will open. No one person can open it alone.",
    illustration: "trust"
  },
  {
    title: "Record the evening as it happens",
    text: "Attendance, the social fund, shares bought, repayments, new loan requests and any vote — in the order your meeting already follows.",
    illustration: "reports"
  },
  {
    title: "Close and lock",
    text: "The same three keys seal the meeting. What is sealed becomes part of the group's permanent record and cannot be quietly changed.",
    illustration: "package"
  },
  {
    title: "Send it up when you have signal",
    text: "Back in network, the phone sends everything to the office by itself. Nothing waits for a connection during the meeting.",
    illustration: "payments"
  },
  {
    title: "Share out, and start again",
    text: "At the end of the cycle every member gets their savings plus their share of what the group earned, less anything they still owe.",
    illustration: "growth"
  }
] as const satisfies ReadonlyArray<{ title: string; text: string; illustration: IllustrationKind }>;

const securityRows = [
  [
    "No one opens the book alone",
    "Three officials must each enter their own PIN before a meeting will open — or five members, if the officials are not there. The phone is no easier to misuse than a cash box with three padlocks."
  ],
  [
    "A PIN belongs to one person",
    "Each member chooses their own four digits. Nobody can look one up, not the group, not us. If it is forgotten, an official clears it and the member picks a new one."
  ],
  [
    "Figures are never quietly changed",
    "Money is written once. A correction is a new entry that explains itself, so the history stays whole and anybody can read back what happened."
  ],
  [
    "The group decides, not the app",
    "Loans, grants, elections and rule changes go to a vote and the tally is kept. The app records the decision; it never makes one."
  ],
  [
    "Members' details stay private",
    "A member sees their own record and nobody else's. Outside the group, a phone number shows only its last three digits."
  ]
];

const partnerOutcomes = [
  {
    title: "Where your groups are",
    text: "Which groups are running, in which county, and how many members they hold between them.",
    illustration: "map"
  },
  {
    title: "Whether they are meeting",
    text: "Meetings held and sealed, attendance kept up, and savings going in month after month.",
    illustration: "training"
  },
  {
    title: "Whether loans come back",
    text: "How much has been lent, how much repaid, and which groups are keeping to their own rules.",
    illustration: "lenders"
  },
  {
    title: "What your field team found",
    text: "Visits made, groups scored against the assessment, and what each one was advised to do next.",
    illustration: "trust"
  }
] as const satisfies ReadonlyArray<{ title: string; text: string; illustration: IllustrationKind }>;

const financialRailCards = [
  {
    title: "Money in, by phone",
    text: "Members can pay their shares and repayments by M-Pesa or card, straight into the group's own account — not ours.",
    illustration: "payments"
  },
  {
    title: "A welfare pot kept separate",
    text: "The social fund sits apart from savings and is never lent out. What is left at the end of the cycle goes back to members.",
    illustration: "finance"
  },
  {
    title: "Buy stock on credit",
    text: "Order goods through Intelli-Store against the group's own standing, and repay from the group's fund.",
    illustration: "package"
  },
  {
    title: "Borrow from outside",
    text: "Money the group takes from a bank or a programme is held in its own pot, so borrowed money is never mistaken for savings.",
    illustration: "banking"
  },
  {
    title: "A rating you can show a lender",
    text: "The app works out how the group is doing from its own record — meetings held, repayments made, savings kept up — and says plainly what would raise it.",
    illustration: "growth"
  },
  {
    title: "Statements for anyone who asks",
    text: "A report for the group, or one for a single member, ready to share or print in seconds.",
    illustration: "reports"
  }
] as const satisfies ReadonlyArray<{ title: string; text: string; illustration: IllustrationKind }>;

/**
 * The phone screens shown on the landing page.
 *
 * These are the same captures the guide and the printed manual use, read from
 * `/docs`, so a visitor sees the app that actually ships rather than a mock-up
 * — and all three surfaces are updated by recapturing one folder.
 */
const appScreens = [
  {
    src: "/docs/12-unlock.webp",
    title: "Opening the meeting",
    text: "Three officials turn their key, and the room can see the count as it goes."
  },
  {
    src: "/docs/10-group-home.webp",
    title: "The group at a glance",
    text: "Savings, loans out, members, meetings, fines and the welfare pot."
  },
  {
    src: "/docs/11-meeting-hub.webp",
    title: "Everything the evening needs",
    text: "Shares, repayments, fines, welfare and voting, in one place."
  },
  {
    src: "/docs/13-members.webp",
    title: "Your members",
    text: "Where each member stands on savings and on what they owe."
  },
  {
    src: "/docs/18-voting.webp",
    title: "Decisions on the record",
    text: "A vote keeps its tally. An election can be secret."
  },
  {
    src: "/docs/21-member-passbook.webp",
    title: "A member's own passbook",
    text: "What they have paid in, what they owe, every entry in order."
  },
  {
    src: "/docs/23-member-report.webp",
    title: "A statement to share",
    text: "A dated record a member can send to anyone who asks."
  },
  {
    src: "/docs/17-welfare.webp",
    title: "The welfare fund",
    text: "What the pot holds, and what it has paid out this cycle."
  }
];

/** The three screens fanned behind the hero headline. */
const heroScreens = [
  { src: "/docs/10-group-home.webp", alt: "The group dashboard: savings, loans, members and meetings" },
  { src: "/docs/11-meeting-hub.webp", alt: "Inside an open meeting, with every action the group takes" },
  { src: "/docs/21-member-passbook.webp", alt: "A member's passbook showing their savings and what they owe" }
];

// NOTE: Illustrative testimonials by role only (no named people/organizations).
// TODO: Replace with real, attributed and consented quotes before public launch.
const testimonials = [
  {
    quote:
      "We run the whole meeting on the phone even when there is no network, and it goes up on its own once we are back in town.",
    name: "Group Chairperson",
    role: "Savings group, Kiambu County",
    illustration: "groups"
  },
  {
    quote:
      "I can see whether my groups are meeting and whether their loans come back, without ever opening a member's private record.",
    name: "Programme Lead",
    role: "Partner organisation",
    illustration: "partners"
  },
  {
    quote:
      "The members used to wait for the book to be opened to know what they had saved. Now each of them can look on their own phone.",
    name: "Group Secretary",
    role: "Savings group, Embu County",
    illustration: "finance"
  }
] as const satisfies ReadonlyArray<{
  quote: string;
  name: string;
  role: string;
  illustration: IllustrationKind;
}>;

// NOTE: Access tiers reflect the current programme-funded model, not final commercial pricing.
// TODO: Confirm commercial terms / amounts with the business before launch.
const pricingTiers = [
  {
    name: "Group accounts",
    price: "Free",
    cadence: "via partner programmes",
    text: "For VSLAs, Chamas, credit unions, and cooperatives onboarded through a partner.",
    features: [
      "The whole meeting, with or without network",
      "Members, officials and their PINs",
      "Loans, share-out and statements",
      "The Android app, and the same thing in a browser"
    ],
    cta: { label: "Register a group", href: "#group-registration" },
    featured: false
  },
  {
    name: "Partners & donors",
    price: "Custom",
    cadence: "tailored to programme",
    text: "For NGOs, donors, government programmes, and accelerators running field operations.",
    features: [
      "Your own account for programme money",
      "Reports on reach, meetings and repayment",
      "Text messages to your groups",
      "Paying groups and being paid"
    ],
    cta: { label: "Talk to us", href: "/contact" },
    featured: true
  },
  {
    name: "Lenders & funds",
    price: "Custom",
    cadence: "tailored to portfolio",
    text: "For MFIs, SACCOs, and funds identifying green businesses ready for responsible capital.",
    features: [
      "How each group repays, in its own record",
      "Reports by portfolio and by county",
      "Payouts by M-Pesa, card or bank",
      "A full history you can check"
    ],
    cta: { label: "Talk to us", href: "/contact" },
    featured: false
  }
] as const;

const faqs = [
  {
    question: "Does it work where there is no network?",
    answer:
      "Yes. The whole meeting runs on the phone — attendance, savings, shares, loans, fines, welfare and votes. When you are back in signal the phone sends everything up by itself. Only creating an account and signing in the first time need internet."
  },
  {
    question: "What if the phone is lost or breaks?",
    answer:
      "Everything already sent to the office is safe, and you carry on from a new phone by signing in and loading your group again. Sync after each meeting and there is nothing to lose."
  },
  {
    question: "Can one person change the figures on their own?",
    answer:
      "No. A meeting only opens when three officials each enter their own PIN, and it takes the same three to close it. Money is written once — a correction is a new entry that says what it is, so the history stays whole."
  },
  {
    question: "Can members see their own savings?",
    answer:
      "Yes. A member signs in with their own phone number and sees what they have saved, the shares they hold and anything they owe — in every group they belong to. They see nobody else's record."
  },
  {
    question: "How does a group get started?",
    answer:
      "Register the group on this page or through a partner, add your members, and give each official a PIN. You can run your next meeting on it."
  },
  {
    question: "What does it cost?",
    answer:
      "Groups joining through a partner programme use it free. Partners, lenders and funds are quoted for what they need — talk to us."
  },
  {
    question: "Which languages does it speak?",
    answer:
      "Five, chosen on each phone. Members do not have to work in English."
  },
  {
    question: "Is there an app to download?",
    answer:
      "Yes, on the Google Play Store. It can also be opened straight from the browser on a phone."
  }
];

export default function LandingPage() {
  return (
    <main className="landing-page">
      <section className="landing-hero is-minimal">
        <PublicSiteHeader
          ariaLabel="Landing navigation"
          playStoreUrl={playStoreUrl}
          showAccessLinks={false}
        />

        <HeroOrbitMotif />

        <div className="landing-hero-content">
          <p className="eyebrow">The savings group record book, on a phone</p>
          <h1>Your group&rsquo;s book, in your pocket</h1>
          <p>
            Intelli-Cash keeps the meetings, savings, shares and loans of a
            savings group on an ordinary Android phone &mdash; and keeps working
            when the network does not. Every member can see their own money,
            and nobody can open the book alone.
          </p>
          <div className="hero-actions">
            <a
              className="button app-store-button"
              href={playStoreUrl}
              rel="noopener noreferrer"
              target="_blank"
            >
              <Download size={18} />
              Get it on Play Store
            </a>
            <a className="button secondary light" href="#screens">
              See the app
              <ArrowRight size={18} />
            </a>
            <a className="button secondary light" href="#group-registration">
              Register your group
              <UsersRound size={18} />
            </a>
          </div>
          <div className="hero-metrics" aria-label="What the app does">
            <span>
              <ShieldCheck size={15} />
              Three keys open a meeting
            </span>
            <span>
              <Smartphone size={15} />
              Works with no network
            </span>
            <span>
              <UsersRound size={15} />
              Every member sees their own savings
            </span>
            <span>
              <ClipboardList size={15} />
              Statements you can share
            </span>
          </div>
        </div>
        <div className="landing-hero-scene">
          <div className="hero-phone-fan">
            {heroScreens.map((screen, index) => (
              <figure className={`hero-phone hero-phone-${index + 1}`} key={screen.src}>
                <img alt={screen.alt} loading={index === 0 ? "eager" : "lazy"} src={screen.src} />
              </figure>
            ))}
          </div>
        </div>
      </section>

      <section className="landing-signal-band proof-strip" aria-label="Platform signals">
        {operatingSignals.map((signal) => (
          <div className="landing-signal" key={signal.label}>
            <LandingIllustration compact kind={signal.illustration} />
            <span>{signal.label}</span>
          </div>
        ))}
      </section>

      <section className="partner-proof-row" id="partners" aria-labelledby="partner-proof-title">
        <div className="partner-proof-copy">
          <p className="eyebrow">Who we work with</p>
          <h2 id="partner-proof-title">Trusted by the programmes that support savings groups</h2>
        </div>
        <div className="partner-logo-grid">
          {partnerLogos.map((partner) => (
            <a
              aria-label={`Visit ${partner.name}`}
              className="partner-logo-card"
              href={partner.href}
              key={partner.name}
              rel="noopener noreferrer"
              target="_blank"
            >
              <img alt={`${partner.name} logo`} loading="lazy" src={partner.src} />
              <strong>{partner.name}</strong>
              <ul className="partner-program-list" aria-label={`${partner.name} programs`}>
                {partner.programmes.map((programme) => (
                  <li key={programme}>{programme}</li>
                ))}
              </ul>
            </a>
          ))}
        </div>
      </section>

      <section className="landing-section intro-section" id="platform">
        <div className="landing-section-header wide">
          <p className="eyebrow">What it does</p>
          <h2>Everything a savings group already does, written down properly</h2>
          <p>
            Savings groups, chamas, credit unions and cooperatives have run on
            paper books and a locked box for years. Intelli-Cash keeps the same
            method &mdash; the same meeting, the same three keys, the same
            share-out &mdash; and takes away the arithmetic and the lost pages.
          </p>
        </div>
        <div className="audience-grid">
          {audienceCards.map((card) => (
            <article className="pillar-card audience-card illustrated-card" key={card.title}>
              <LandingIllustration kind={card.illustration} />
              <h3>{card.title}</h3>
              <p>{card.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="landing-section financial-rails-section" aria-labelledby="financial-rails-title">
        <div className="landing-section-header wide">
          <p className="eyebrow">Money coming in and going out</p>
          <h2 id="financial-rails-title">The group&rsquo;s money stays the group&rsquo;s money</h2>
          <p>
            Members can pay by phone straight into the group&rsquo;s own
            account. Savings, the welfare pot and anything borrowed from outside
            are each kept apart, so no total ever flatters the group by counting
            borrowed money as its own.
          </p>
        </div>
        <div className="financial-rails-grid">
          {financialRailCards.map((card) => (
            <article className="financial-rail-card illustrated-card" key={card.title}>
              <LandingIllustration kind={card.illustration} />
              <h3>{card.title}</h3>
              <p>{card.text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="landing-section app-screens-section" id="screens" aria-labelledby="app-screens-title">
        <div className="landing-section-header wide">
          <p className="eyebrow">See the app</p>
          <h2 id="app-screens-title">This is what your secretary will be looking at</h2>
          <p>
            Real screens from the app as it ships today &mdash; not drawings of
            one. Big type, few words per screen, and the meeting laid out in the
            order your group already follows.
          </p>
        </div>
        <div className="app-screens-grid">
          {appScreens.map((screen) => (
            <figure className="app-screen" key={screen.src}>
              <img alt={`${screen.title}: ${screen.text}`} loading="lazy" src={screen.src} />
              <figcaption>
                <strong>{screen.title}</strong>
                <span>{screen.text}</span>
              </figcaption>
            </figure>
          ))}
        </div>
        <p className="app-screens-footnote">
          Want the whole thing screen by screen?{" "}
          <Link href="/docs">Read the guide</Link>.
        </p>
      </section>

      <section className="landing-section works-section" id="how-it-works">
        <div className="landing-section-header">
          <p className="eyebrow">How it works</p>
          <h2>From setting up to sharing out</h2>
        </div>
        <div className="workflow-grid">
          {workflowSteps.map((step, index) => (
            <article className="workflow-step illustrated-card" key={step.title}>
              <span className="step-number">{String(index + 1).padStart(2, "0")}</span>
              <LandingIllustration kind={step.illustration} />
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </article>
          ))}
        </div>
      </section>

      <IntelliStoreSection />

      <GroupRegistrationSection />

      <section className="landing-section governance-band" id="governance">
        <div className="governance-copy">
          <p className="eyebrow">Keeping the money safe</p>
          <h2>Built so that no one person can be trusted alone</h2>
          <p>
            A savings group&rsquo;s cash box has three padlocks and three
            different people holding the keys. Intelli-Cash keeps that idea
            rather than replacing it &mdash; because the phone should be no
            easier to misuse than the box was.
          </p>
        </div>
        <div className="governance-table">
          {securityRows.map(([label, value]) => (
            <div className="governance-row" key={label}>
              <span>{label}</span>
              <strong>{value}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="landing-section partner-section">
        <div className="partner-copy">
          <p className="eyebrow">For partners and funders</p>
          <h2>See how your groups are doing, without reading their books</h2>
          <p>
            Partners and funders see how the groups they support are doing
            &mdash; whether they meet, whether they save, whether loans come
            back &mdash; without reading any member&rsquo;s private record.
          </p>
        </div>
        <div className="partner-outcomes">
          {partnerOutcomes.map((outcome) => (
            <div className="partner-outcome illustrated-outcome" key={outcome.title}>
              <LandingIllustration compact kind={outcome.illustration} />
              <strong>{outcome.title}</strong>
              <span>{outcome.text}</span>
            </div>
          ))}
        </div>
      </section>

      <section className="landing-section app-download-band">
        <div className="download-copy">
          <p className="eyebrow">The app</p>
          <h2>One phone in the room is enough</h2>
          <p>
            The secretary&rsquo;s phone holds the book. Everyone else can put
            the app on their own phone to see their savings, but the meeting
            only needs one.
          </p>
        </div>
        <div className="download-actions">
          <a
            className="button app-store-button"
            href={playStoreUrl}
            rel="noopener noreferrer"
            target="_blank"
          >
            <Download size={18} />
            Download on Play Store
          </a>
          <Link className="button secondary" href="#platform">
            Explore services
            <ArrowRight size={18} />
          </Link>
        </div>
      </section>
      <section className="landing-section testimonials-section" id="testimonials" aria-labelledby="testimonials-title">
        <div className="landing-section-header wide">
          <p className="eyebrow">From the field</p>
          <h2 id="testimonials-title">Built around the people doing the work</h2>
          <p>
            How the groups, partners and enterprises using Intelli-Cash
            describe it.
          </p>
        </div>
        <div className="testimonial-grid">
          {testimonials.map((item) => (
            <figure className="testimonial-card" key={item.name}>
              <blockquote>{item.quote}</blockquote>
              <figcaption>
                <LandingIllustration compact kind={item.illustration} />
                <span>
                  <strong>{item.name}</strong>
                  <small>{item.role}</small>
                </span>
              </figcaption>
            </figure>
          ))}
        </div>
        <p className="section-footnote">Illustrative examples by role &mdash; verified, attributed quotes will be added before launch.</p>
      </section>

      <section className="landing-section pricing-section" id="pricing" aria-labelledby="pricing-title">
        <div className="landing-section-header wide">
          <p className="eyebrow">What it costs</p>
          <h2 id="pricing-title">Free for groups joining through a partner</h2>
          <p>
            A savings group pays nothing when it joins through one of our
            partner programmes. Partners, funders and lenders are quoted for
            what they actually need.
          </p>
        </div>
        <div className="pricing-grid">
          {pricingTiers.map((tier) => (
            <article className={`pricing-card${tier.featured ? " is-featured" : ""}`} key={tier.name}>
              {tier.featured ? <span className="pricing-badge">Most common</span> : null}
              <h3>{tier.name}</h3>
              <p className="pricing-price">
                <strong>{tier.price}</strong>
                <span>{tier.cadence}</span>
              </p>
              <p className="pricing-text">{tier.text}</p>
              <ul className="pricing-features">
                {tier.features.map((feature) => (
                  <li key={feature}>
                    <BadgeCheck size={18} />
                    {feature}
                  </li>
                ))}
              </ul>
              {tier.cta.href.startsWith("/") ? (
                <Link className={`button${tier.featured ? "" : " secondary"}`} href={tier.cta.href}>
                  {tier.cta.label}
                  <ArrowRight size={18} />
                </Link>
              ) : (
                <a className={`button${tier.featured ? "" : " secondary"}`} href={tier.cta.href}>
                  {tier.cta.label}
                  <ArrowRight size={18} />
                </a>
              )}
            </article>
          ))}
        </div>
        <p className="section-footnote">Tiers reflect the current programme-funded model; final commercial terms to be confirmed.</p>
      </section>

      <section className="landing-section faq-section" id="faq" aria-labelledby="faq-title">
        <div className="landing-section-header">
          <p className="eyebrow">Common questions</p>
          <h2 id="faq-title">Answers before you start</h2>
        </div>
        <div className="faq-list">
          {faqs.map((faq) => (
            <details className="faq-item" key={faq.question}>
              <summary>
                <CircleHelp size={20} />
                <span>{faq.question}</span>
                <i className="faq-marker" aria-hidden="true" />
              </summary>
              <p>{faq.answer}</p>
            </details>
          ))}
        </div>
      </section>

      <section className="landing-section landing-contact-band" id="contact" aria-labelledby="contact-band-title">
        <div className="contact-band-copy">
          <p className="eyebrow">Contact</p>
          <h2 id="contact-band-title">Talk to the Intelli-Cash team</h2>
          <p>
            Registering a group, joining as a partner, or just a question
            about how it works &mdash; we would rather you asked.
          </p>
          <div className="hero-actions">
            <Link className="button" href="/contact">
              Contact us
              <ArrowRight size={18} />
            </Link>
            <a className="button secondary" href="#group-registration">
              Register group
              <UsersRound size={18} />
            </a>
          </div>
        </div>
        <div className="contact-band-channels">
          <a className="contact-band-card" href={`mailto:${SUPPORT_EMAIL}`}>
            <Mail size={22} />
            <strong>Email</strong>
            <span>{SUPPORT_EMAIL}</span>
          </a>
          <a className="contact-band-card" href={SUPPORT_PHONE_HREF}>
            <Phone size={22} />
            <strong>Phone</strong>
            <span>{SUPPORT_PHONE}</span>
          </a>
          <a className="contact-band-card" href={`mailto:${SUPPORT_EMAIL}?subject=Field%20support%20request`}>
            <Send size={22} />
            <strong>Field support</strong>
            <span>Kenya field operations</span>
          </a>
        </div>
      </section>

      <PublicSiteFooter playStoreUrl={playStoreUrl} showAccessLinks={false} />
    </main>
  );
}

const ORBIT_MEMBER_COUNT = 12;
const ORBIT_RADIUS = 168;
const ORBIT_CENTER = 200;

const orbitNodes = Array.from({ length: ORBIT_MEMBER_COUNT }, (_, index) => {
  const angle = (index / ORBIT_MEMBER_COUNT) * Math.PI * 2 - Math.PI / 2;
  return {
    isPot: index === 0,
    x: ORBIT_CENTER + ORBIT_RADIUS * Math.cos(angle),
    y: ORBIT_CENTER + ORBIT_RADIUS * Math.sin(angle)
  };
});

/**
 * Decorative backdrop for the hero: a ring of member nodes orbiting a shared
 * pot, standing in for the rotating-contribution cycle at the heart of a
 * VSLA/Chama meeting. Purely atmospheric (aria-hidden), so it never competes
 * with the real product screenshots layered above it.
 */
function HeroOrbitMotif() {
  return (
    <div aria-hidden="true" className="landing-hero-motif">
      <svg className="hero-orbit-motif" viewBox="0 0 400 400">
        <circle className="hero-orbit-ring" cx={ORBIT_CENTER} cy={ORBIT_CENTER} r={ORBIT_RADIUS} />
        <circle className="hero-orbit-core" cx={ORBIT_CENTER} cy={ORBIT_CENTER} r={46} />
        <g className="hero-orbit-nodes">
          {orbitNodes.map((node, index) => (
            <circle
              className={`hero-orbit-node${node.isPot ? " is-pot" : ""}`}
              cx={node.x}
              cy={node.y}
              key={index}
              r={node.isPot ? 9 : 5}
            />
          ))}
        </g>
      </svg>
    </div>
  );
}

function LandingIllustration({
  kind,
  compact = false
}: {
  kind: IllustrationKind;
  compact?: boolean;
}) {
  const Icon = illustrationIcons[kind];

  return (
    <span
      aria-hidden="true"
      className={`landing-illustration landing-icon-illustration ${compact ? "compact" : ""}`}
    >
      <span className="landing-icon-core">
        <Icon size={compact ? 30 : 58} />
      </span>
      {!compact ? (
        <span className="landing-icon-bars">
          <i />
          <i />
          <i />
        </span>
      ) : null}
    </span>
  );
}

function IllustrationObject({ kind }: { kind: IllustrationKind }) {
  switch (kind) {
    case "enterprise":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="42" rx="8" width="70" x="125" y="118" />
          <path className="illustration-line" d="M125 132h70M142 118v42M177 118v42" />
          <path className="illustration-leaf" d="M158 115c-8-22 8-41 30-42 2 24-10 39-30 42Z" />
          <path className="illustration-accent" d="M151 119c-19-12-20-35-5-50 17 14 18 34 5 50Z" />
          <path className="illustration-line" d="M159 119c5-24 16-38 30-46M151 119c-3-18-4-32-5-50" />
        </g>
      );
    case "groups":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="46" rx="12" width="102" x="111" y="113" />
          <path className="illustration-line" d="M125 136h74M141 121v-13M162 121v-13M183 121v-13" />
          <circle className="illustration-accent" cx="141" cy="100" r="10" />
          <circle className="illustration-gold" cx="162" cy="96" r="10" />
          <circle className="illustration-shirt" cx="183" cy="100" r="10" />
        </g>
      );
    case "partners":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="66" rx="14" width="104" x="108" y="86" />
          <path className="illustration-line" d="M127 122l25 18 45-48" />
          <path className="illustration-accent" d="M123 104h32l12 13-20 18-35-20Z" />
          <path className="illustration-gold" d="M199 104h-32l-12 13 20 18 35-20Z" />
        </g>
      );
    case "lenders":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="84" rx="10" width="78" x="121" y="72" />
          <path className="illustration-line" d="M138 130V98M160 130v-46M182 130v-26M133 135h54" />
          <circle className="illustration-gold" cx="205" cy="142" r="18" />
          <path className="illustration-line" d="M198 142h14M205 135v14" />
        </g>
      );
    case "training":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="64" rx="9" width="102" x="109" y="78" />
          <path className="illustration-line" d="M124 100h45M124 116h70M124 132h48M198 142l14 21" />
          <path className="illustration-accent" d="M177 95l12 8-12 8Z" />
        </g>
      );
    case "finance":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="58" rx="12" width="98" x="111" y="105" />
          <path className="illustration-line" d="M111 121h98M179 134h28" />
          <circle className="illustration-gold" cx="194" cy="135" r="8" />
          <path className="illustration-leaf" d="M135 101c-10-24 12-44 36-37 0 25-14 38-36 37Z" />
          <path className="illustration-line" d="M136 101c10-15 21-27 35-37" />
        </g>
      );
    case "payments":
      return (
        <g className="illustration-object">
          <rect className="illustration-dark-fill" height="92" rx="14" width="56" x="131" y="68" />
          <rect className="illustration-card-fill" height="67" rx="8" width="42" x="138" y="81" />
          <path className="illustration-line" d="M148 101h24M148 116h24M148 132h13" />
          <circle className="illustration-gold" cx="193" cy="94" r="15" />
          <path className="illustration-line" d="M187 94h13M194 87v14" />
        </g>
      );
    case "banking":
      return (
        <g className="illustration-object">
          <path className="illustration-card-fill" d="M107 104h106v20H107Z" />
          <path className="illustration-accent" d="M99 104l61-40 61 40Z" />
          <path className="illustration-line" d="M119 124v42M142 124v42M165 124v42M188 124v42M104 166h112" />
        </g>
      );
    case "marketing":
      return (
        <g className="illustration-object">
          <path className="illustration-card-fill" d="M107 121l65-31v63l-65-24Z" />
          <path className="illustration-accent" d="M172 90c16 4 28 17 28 32s-12 28-28 32Z" />
          <path className="illustration-line" d="M104 129l17 42M199 98l20-14M205 122h28M199 145l20 14" />
          <circle className="illustration-gold" cx="226" cy="82" r="7" />
        </g>
      );
    case "ai":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="68" rx="17" width="88" x="116" y="92" />
          <path className="illustration-line" d="M160 92V75M146 123h1M178 123h1M142 141c12 8 32 8 44 0" />
          <circle className="illustration-gold" cx="160" cy="70" r="8" />
          <path className="illustration-accent" d="M205 84h36v25h-18l-9 11v-11h-9Z" />
        </g>
      );
    case "web3":
      return (
        <g className="illustration-object">
          <circle className="illustration-card-fill" cx="160" cy="116" r="26" />
          <circle className="illustration-accent" cx="112" cy="90" r="16" />
          <circle className="illustration-gold" cx="214" cy="95" r="16" />
          <circle className="illustration-shirt" cx="205" cy="151" r="16" />
          <path className="illustration-line" d="M126 96l28 16M176 111l27-13M179 130l25 18M129 146l27-18" />
          <circle className="illustration-accent" cx="117" cy="150" r="16" />
        </g>
      );
    case "reports":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="86" rx="11" width="82" x="118" y="70" />
          <path className="illustration-line" d="M135 94h47M135 112h31M135 135l15-14 16 10 20-25" />
          <circle className="illustration-gold" cx="210" cy="135" r="16" />
          <path className="illustration-line" d="M203 135l6 6 12-15" />
        </g>
      );
    case "map":
      return (
        <g className="illustration-object">
          <path className="illustration-card-fill" d="M104 88l49 17 49-17 18 15v64l-49-17-49 17-18-15Z" />
          <path className="illustration-line" d="M153 105v45M171 105v45" />
          <path className="illustration-accent" d="M160 65c19 0 34 15 34 33 0 24-34 55-34 55s-34-31-34-55c0-18 15-33 34-33Z" />
          <circle className="illustration-card-fill" cx="160" cy="98" r="12" />
        </g>
      );
    case "package":
      return (
        <g className="illustration-object">
          <path className="illustration-card-fill" d="M107 105l53-29 53 29-53 29Z" />
          <path className="illustration-accent" d="M107 105v56l53 28v-55Z" />
          <path className="illustration-gold" d="M213 105v56l-53 28v-55Z" />
          <path className="illustration-line" d="M127 94l53 29M190 92l-53 29" />
        </g>
      );
    case "trust":
      return (
        <g className="illustration-object">
          <path className="illustration-card-fill" d="M160 62l62 24v41c0 40-31 59-62 73-31-14-62-33-62-73V86Z" />
          <path className="illustration-line" d="M133 122l24 24 41-57" />
          <circle className="illustration-gold" cx="207" cy="82" r="12" />
        </g>
      );
    case "growth":
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="76" rx="11" width="104" x="108" y="86" />
          <path className="illustration-line" d="M125 140l26-24 21 12 34-44M126 152h84" />
          <path className="illustration-leaf" d="M151 107c-1-25 18-38 42-31-3 23-17 36-42 31Z" />
          <circle className="illustration-gold" cx="207" cy="84" r="9" />
        </g>
      );
    case "champions":
    default:
      return (
        <g className="illustration-object">
          <rect className="illustration-card-fill" height="76" rx="13" width="112" x="104" y="73" />
          <path className="illustration-line" d="M122 132h72M122 115h22M153 115h38M122 96h62" />
          <path className="illustration-accent" d="M201 76l20-13v41l-20-11Z" />
          <circle className="illustration-gold" cx="134" cy="97" r="8" />
          <circle className="illustration-shirt" cx="160" cy="97" r="8" />
          <circle className="illustration-accent" cx="186" cy="97" r="8" />
        </g>
      );
  }
}
