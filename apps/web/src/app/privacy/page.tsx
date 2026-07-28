import React from "react";
import {
  FIELD_SUPPORT_EMAIL_HREF,
  SUPPORT_EMAIL,
  SUPPORT_EMAIL_HREF,
  SUPPORT_PHONE,
  SUPPORT_PHONE_HREF
} from "@/lib/contact";
import type { Metadata } from "next";
import { PublicSiteFooter } from "../../components/public-site-footer";
import { PublicSiteHeader } from "../../components/public-site-header";

const playStoreUrl = "https://play.google.com/store/apps/details?id=com.intellicash.app";

export const metadata: Metadata = {
  title: "Privacy Notice | Intelli Cash",
  description:
    "How Intelli-Cash collects, uses, protects, and retains personal data for savings groups, members, partners, and public visitors."
};

const collectionRows: Array<[string, string, string]> = [
  [
    "Group members",
    "Name, phone number, group role, meeting attendance, savings and loan records, hashed national ID",
    "Running group meetings, passbooks, and credit readiness on behalf of your group"
  ],
  [
    "Store buyers and booking visitors",
    "Name, email, phone number, county, group or business name",
    "Reviewing product credit requests and arranging VA / CBT field support"
  ],
  [
    "Partner and programme contacts",
    "Contact name, work email, work phone, organization details",
    "Operating partner programmes, funding, and reporting"
  ],
  [
    "Platform users",
    "Login email, password (stored as a bcrypt hash), role, session records",
    "Authenticating you and scoping what your account can see"
  ]
];

const protections = [
  "Passwords, meeting PINs, and OTPs are stored only as strong one-way hashes; national ID numbers are stored hashed, never in plain text.",
  "PIN and OTP SMS content is encrypted at rest, and phone numbers are masked in operational screens and SMS delivery logs.",
  "Payment and SMS provider credentials are stored encrypted; webhooks and payments carry references, not card or wallet secrets.",
  "Access is role-scoped: members see their own records, group accounts see their group, partners see their programmes, and only platform administrators see cross-programme data.",
  "Public pages and public API responses never include member records, and staff contact details (agents, suppliers, partner contacts) are limited to names and coverage areas.",
  "Request logs carry trace IDs, not request bodies, and sensitive query values are redacted before logging.",
  "Financial records are append-only with an audit trail, so changes are attributable and reviewable."
];

const rights = [
  "Ask what personal data we hold about you and receive a copy",
  "Ask us to correct inaccurate or outdated details",
  "Ask us to delete data we no longer need for the purposes above",
  "Withdraw consent for a request you submitted through a public form",
  "Complain to the Office of the Data Protection Commissioner (Kenya) if you believe your data is mishandled"
];

export default function PrivacyPage() {
  return (
    <main className="landing-page">
      <section className="store-page-hero">
        <PublicSiteHeader ariaLabel="Privacy notice navigation" playStoreUrl={playStoreUrl} />
        <div className="store-page-hero-copy">
          <p className="eyebrow">Privacy Notice</p>
          <h1>How Intelli-Cash protects personal data</h1>
          <p>
            Intelli-Cash digitises savings groups, so we handle personal and
            financial records with the same discipline the groups themselves
            practice. This notice explains what we collect, why, and your
            rights under the Kenya Data Protection Act, 2019.
          </p>
        </div>
      </section>

      <section className="landing-section" aria-labelledby="privacy-collect-title">
        <div className="landing-section-header">
          <p className="eyebrow">What We Collect</p>
          <h2 id="privacy-collect-title">Only what the service needs</h2>
        </div>
        <div className="governance-table">
          {collectionRows.map(([who, what, why]) => (
            <div className="governance-row" key={who}>
              <span>{who}</span>
              <strong>
                {what}
                <br />
                <small>Purpose: {why}</small>
              </strong>
            </div>
          ))}
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="privacy-protect-title">
        <div className="governance-copy">
          <p className="eyebrow">How We Protect It</p>
          <h2 id="privacy-protect-title">Protection built into the platform</h2>
          <p>
            These controls are part of the software itself, not just policy on
            paper. They are documented for partners and auditors in our data
            protection protocol.
          </p>
        </div>
        <div className="governance-table">
          {protections.map((item) => (
            <div className="governance-row" key={item}>
              <span>Control</span>
              <strong>{item}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="landing-section" aria-labelledby="privacy-rights-title">
        <div className="landing-section-header">
          <p className="eyebrow">Your Rights</p>
          <h2 id="privacy-rights-title">You stay in control of your data</h2>
          <p>
            Under the Kenya Data Protection Act, 2019 you can, at any time:
          </p>
        </div>
        <div className="governance-table">
          {rights.map((item) => (
            <div className="governance-row" key={item}>
              <span>Right</span>
              <strong>{item}</strong>
            </div>
          ))}
        </div>
        <p className="section-footnote">
          To exercise any of these rights, contact{" "}
          <a href={SUPPORT_EMAIL_HREF}>{SUPPORT_EMAIL}</a> or call{" "}
          <a href={SUPPORT_PHONE_HREF}>{SUPPORT_PHONE}</a>. We respond within 14 days. Retention:
          public store and booking requests are kept for 24 months after closure; group financial
          records are kept for the life of the group plus statutory retention periods; server logs
          are kept for 90 days.
        </p>
      </section>

      <PublicSiteFooter playStoreUrl={playStoreUrl} />
    </main>
  );
}
