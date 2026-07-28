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
  Activity,
  BadgeCheck,
  BookOpenText,
  CircleHelp,
  ClipboardList,
  HandCoins,
  LifeBuoy,
  Mail,
  Phone,
  ShieldCheck,
  ShoppingBag,
  UsersRound,
  WalletCards
} from "@/lib/theme-icons";

const gettingStarted = [
  ["Sign in", "Use the access door for your role (member, group, partner, lender, or admin) and the credentials shared by your programme."],
  ["Find your workspace", "The left menu changes with your role. Start on the Dashboard for a summary, then open the module you need."],
  ["Set up your account", "Open the profile menu (top right) to update your details, switch language, or change your password."],
  ["Work offline when needed", "Field pages keep working without a connection and sync automatically once you are back online."]
] as const;

const roleGuide = [
  ["Members", "See your own passbook, savings and loan records, meeting history, and store requests."],
  ["Group accounts", "Run meetings, register members, record savings and repayments, and submit store requests for the group."],
  ["Partners & donors", "Track programme reach, group activity, field quality, and impact reports for the groups you support."],
  ["Lenders & funds", "Review credit readiness, portfolio, and repayment signals to identify businesses ready for capital."],
  ["Platform admins", "Manage groups, users, payments, integrations, SMS, and audit across every programme."]
] as const;

const featureGuides = [
  {
    title: "Meetings",
    icon: Activity,
    text: "Open a meeting with three independent key-holders, record attendance, capture savings and loan transactions, and close with a signed summary.",
    href: "/dashboard/meetings",
    action: "Open meetings",
    roles: "Group accounts, members, partners"
  },
  {
    title: "Passbook & savings",
    icon: BookOpenText,
    text: "Every contribution, share purchase, loan, and repayment is recorded to an append-only passbook you can review any time.",
    href: "/dashboard/passbook",
    action: "Open passbook",
    roles: "Members"
  },
  {
    title: "Groups & members",
    icon: UsersRound,
    text: "Register members with roles and KYC, issue meeting PINs, and keep group records, officials, and contact details up to date.",
    href: "/dashboard/groups",
    action: "Open groups",
    roles: "Group accounts, admins"
  },
  {
    title: "Intelli-Store",
    icon: ShoppingBag,
    text: "Browse productive assets, add them to the cart, and submit a credit request. Requests move through programme review, financing, and VA / CBT delivery.",
    href: "/dashboard/intelli-store",
    action: "Open Intelli-Store",
    roles: "Everyone"
  },
  {
    title: "Reports",
    icon: ClipboardList,
    text: "Generate portfolio, county, phase, ledger, and impact reports. Sensitive member details stay protected by role.",
    href: "/dashboard/reports",
    action: "Open reports",
    roles: "Admins, partners, lenders, group accounts"
  },
  {
    title: "Payments",
    icon: WalletCards,
    text: "Move money with M-Pesa, Paystack, and KCB Buni, track callbacks, and manage partner wallet operations.",
    href: "/dashboard/payments",
    action: "Open payments",
    roles: "Admins"
  }
] as const;

const concepts = [
  ["VA / CBT", "Village Agents and Community-Based Trainers deliver products in the field, onboard groups, and provide coaching. You can book them from Intelli-Store."],
  ["Credit request flow", "A store request goes Pending → Under review → Approved → Fulfilled. Financing from a partner or lender creates an installment schedule the buyer repays over time."],
  ["Three-key meeting unlock", "Group money can only move after three independent key-holders approve the meeting, protecting members from unauthorised transactions."],
  ["Offline-first", "Field teams can open meetings and capture records without internet. Data is stored safely on the device and syncs when connectivity returns."],
  ["Data protection", "Access is scoped to your role, phone numbers are masked for oversight roles, and financial records are append-only and auditable."]
] as const;

const faqs = [
  {
    question: "How do I unlock a meeting?",
    answer:
      "Three group officials (or five active members) verify their identity with a default PIN or a one-time code. Once the policy is satisfied, the meeting opens and transactions can be recorded."
  },
  {
    question: "I forgot my meeting PIN — what now?",
    answer:
      "A group account or admin can re-issue a default PIN from the member's record. The new PIN is delivered by SMS. For login passwords, use the profile menu to change your password once signed in."
  },
  {
    question: "Does Intelli-Cash work without internet?",
    answer:
      "Yes. It is an offline-first Progressive Web App. Previously loaded pages, meeting unlock, and record capture keep working offline, then sync automatically when you reconnect."
  },
  {
    question: "How is my personal data protected?",
    answer:
      "Access is limited to your role, member phone numbers are masked for oversight roles, passwords and PINs are stored as one-way hashes, and financial records are append-only. See the public privacy notice for details."
  },
  {
    question: "How does a product credit request get financed?",
    answer:
      "After you submit a request, programme staff review it, a partner or lender finances the balance beyond your deposit, and a repayment schedule is created. A VA / CBT then delivers the product."
  }
];

export default function HelpDocsPage() {
  return (
    <>
      <section className="page-heading">
        <div>
          <p className="eyebrow">Help &amp; Docs</p>
          <h2>How to use Intelli-Cash</h2>
        </div>
        <Link className="button secondary" href="/dashboard">
          Dashboard
        </Link>
      </section>

      <section className="data-card docs-intro">
        <header>
          <div>
            <h3>Welcome to the guide</h3>
            <span>Everything you need to run savings groups, green enterprises, and programme operations.</span>
          </div>
          <LifeBuoy size={20} />
        </header>
        <p className="docs-lead">
          Intelli-Cash digitises VSLAs, Chamas, credit unions, and green
          enterprises. This page explains how to get started, what each part of
          the platform does, and where to get help. Use the sections below, or
          jump straight into a module from the menu.
        </p>
      </section>

      <section className="docs-section">
        <div className="docs-section-head">
          <p className="eyebrow">Getting Started</p>
          <h3>Four steps to get going</h3>
        </div>
        <ol className="docs-steps">
          {gettingStarted.map(([title, text], index) => (
            <li key={title}>
              <span className="docs-step-number">{index + 1}</span>
              <div>
                <strong>{title}</strong>
                <span>{text}</span>
              </div>
            </li>
          ))}
        </ol>
      </section>

      <section className="docs-section">
        <div className="docs-section-head">
          <p className="eyebrow">Who Does What</p>
          <h3>Guidance by role</h3>
        </div>
        <div className="governance-table docs-role-table">
          {roleGuide.map(([role, text]) => (
            <div className="governance-row" key={role}>
              <span>{role}</span>
              <strong>{text}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="docs-section">
        <div className="docs-section-head">
          <p className="eyebrow">Feature Guides</p>
          <h3>What each module does</h3>
        </div>
        <div className="account-action-grid">
          {featureGuides.map((guide) => {
            const Icon = guide.icon;
            return (
              <article className="data-card account-action-card docs-guide-card" key={guide.title}>
                <header>
                  <div>
                    <h3>{guide.title}</h3>
                    <span>{guide.text}</span>
                  </div>
                  <Icon size={18} />
                </header>
                <div className="docs-guide-footer">
                  <span className="pill">{guide.roles}</span>
                  <Link className="button secondary" href={guide.href}>
                    {guide.action}
                  </Link>
                </div>
              </article>
            );
          })}
        </div>
      </section>

      <section className="docs-section">
        <div className="docs-section-head">
          <p className="eyebrow">Key Concepts</p>
          <h3>Terms and how things work</h3>
        </div>
        <div className="governance-table docs-role-table">
          {concepts.map(([term, text]) => (
            <div className="governance-row" key={term}>
              <span>{term}</span>
              <strong>{text}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="docs-section">
        <div className="docs-section-head">
          <p className="eyebrow">Frequently Asked Questions</p>
          <h3>Quick answers</h3>
        </div>
        <div className="faq-list docs-faq">
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

      <section className="docs-section">
        <div className="docs-section-head">
          <p className="eyebrow">Still Need Help</p>
          <h3>Contact the Intelli-Cash team</h3>
        </div>
        <div className="account-action-grid">
          <article className="data-card account-action-card">
            <header>
              <div>
                <h3>Email support</h3>
                <span>Account, login, access, and role questions.</span>
              </div>
              <Mail size={18} />
            </header>
            <a className="button" href={SUPPORT_EMAIL_HREF}>
              <Mail size={16} />
              support@intellicash.co.ke
            </a>
          </article>

          <article className="data-card account-action-card">
            <header>
              <div>
                <h3>Call field support</h3>
                <span>Kenya field operations and programme help.</span>
              </div>
              <Phone size={18} />
            </header>
            <a className="button secondary" href={SUPPORT_PHONE_HREF}>
              <Phone size={16} />
              {SUPPORT_PHONE}
            </a>
          </article>

          <article className="data-card account-action-card">
            <header>
              <div>
                <h3>Privacy &amp; data</h3>
                <span>How we collect, protect, and retain your data.</span>
              </div>
              <ShieldCheck size={18} />
            </header>
            <a className="button secondary" href="/privacy" rel="noopener noreferrer" target="_blank">
              <BadgeCheck size={16} />
              Read privacy notice
            </a>
          </article>
        </div>
        <p className="section-footnote">
          <HandCoins size={14} /> Tip: most day-to-day tasks start from the
          Dashboard summary and the module menu on the left.
        </p>
      </section>
    </>
  );
}
