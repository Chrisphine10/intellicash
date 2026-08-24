import React from "react";
import type { Metadata } from "next";
import { SUPPORT_EMAIL, SUPPORT_EMAIL_HREF } from "@/lib/contact";
import { PublicSiteFooter } from "../../components/public-site-footer";
import { PublicSiteHeader } from "../../components/public-site-header";
import { DocsShot } from "./docs-shot";

const playStoreUrl = "https://play.google.com/store/apps/details?id=com.intellicash.app";

export const metadata: Metadata = {
  title: "How to use Intelli-Cash | Guide",
  description:
    "A walkthrough of the Intelli-Cash phone app for savings groups, individual members, and field agents — with screens from the app itself."
};

/**
 * The public guide.
 *
 * Organised by WHO is holding the phone rather than by feature, because that is
 * the question a person actually arrives with. A treasurer opening this page
 * does not want a list of everything the platform does; they want the four
 * screens their evening runs through.
 *
 * Every screenshot is captured from the app running on a device, not drawn. A
 * guide illustrated with mock-ups goes stale the moment a button moves and
 * nobody notices, because nothing connects the picture to the code.
 */
export default function DocsPage() {
  return (
    <main className="landing-page">
      <section className="store-page-hero">
        <PublicSiteHeader ariaLabel="Guide navigation" playStoreUrl={playStoreUrl} />
        <div className="store-page-hero-copy">
          <p className="eyebrow">Guide</p>
          <h1>How Intelli-Cash works, screen by screen</h1>
          <p>
            Three people use this app for three different jobs: a group keeps its
            record book, a member checks their own savings, and a field agent
            supports the groups they look after. Each has its own walkthrough
            below, with the actual screens.
          </p>
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-start-title">
        <div className="landing-section-header">
          <p className="eyebrow">Getting started</p>
          <h2 id="docs-start-title">Everyone starts with an account</h2>
          <p>
            Install the app, then choose the kind of account you have. That
            choice decides what the app shows you from then on — you are never
            asked to set up a group in order to look at your own savings.
          </p>
        </div>
        <div className="docs-shot-row">
          <DocsShot
            src="/docs/01-welcome.png"
            alt="The Intelli-Cash welcome screen with Create Account and Sign In"
            caption="Create an account, or sign in if you already have one. The language can be changed here before anything else."
          />
          <DocsShot
            src="/docs/02-account-types.png"
            alt="Choosing between Our Group, Just Me, and Field Agent"
            caption="Three kinds of account. Our Group keeps the group book on this phone; Just Me is a personal view; Field Agent is for a VA or CBT supporting several groups."
          />
          <DocsShot
            src="/docs/03-sign-in.png"
            alt="The sign-in screen asking which kind of account is signing in"
            caption="Signing in asks the same question, so a shared phone can move between accounts without confusion."
          />
          <DocsShot
            src="/docs/04-language.png"
            alt="The language picker showing English, Kiswahili, Gikuyu, Dholuo and Kiembu"
            caption="Five languages. The ones still being translated say so, rather than silently falling back to English."
          />
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="docs-group-title">
        <div className="landing-section-header">
          <p className="eyebrow">For a group</p>
          <h2 id="docs-group-title">Running the record book</h2>
          <p>
            The group account is the record book. It works with no signal — every
            figure is written on the phone first and sent to the office when
            there is internet.
          </p>
        </div>
        <div className="docs-shot-row" id="docs-group-shots">
          <DocsShot
            src="/docs/10-group-home.png"
            alt="The group dashboard showing savings, loans and the current cycle"
            caption="The group money at a glance: what has been saved, what is out on loan, and where the cycle has reached."
          />
          <DocsShot
            src="/docs/11-meeting-hub.png"
            alt="The meeting screen with actions for shares, loans, voting and welfare"
            caption="A meeting in progress. Everything the group does together is reached from here — shares, loans, attendance, voting and welfare."
          />
          <DocsShot
            src="/docs/12-unlock.png"
            alt="The three-key unlock asking officials for their PINs"
            caption="A meeting opens only when three officials each enter their own 4-digit PIN. No single person can open the book alone."
          />
          <DocsShot
            src="/docs/13-members.png"
            alt="The member list for a group"
            caption="Members, their roles and their standing. Savings and loan history follow the person, so correcting a name never loses their record."
          />
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-member-title">
        <div className="landing-section-header">
          <p className="eyebrow">For a member</p>
          <h2 id="docs-member-title">Checking your own savings</h2>
          <p>
            A personal account shows one person their own record. It never asks
            you to create or manage a group.
          </p>
        </div>
        <div className="docs-shot-row" id="docs-member-shots">
          <DocsShot
            src="/docs/20-member-home.png"
            alt="A member home screen showing their savings and shares"
            caption="What you have saved, the shares you hold, and anything you owe."
          />
          <DocsShot
            src="/docs/21-member-passbook.png"
            alt="A member passbook listing contributions and repayments"
            caption="Your passbook: every contribution and repayment in order — the same figures the group book holds."
          />
          <DocsShot
            src="/docs/22-member-join.png"
            alt="Joining a group using a code"
            caption="Join a group with the code its officials give you. The group approves the request before you appear in their book."
          />
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="docs-agent-title">
        <div className="landing-section-header">
          <p className="eyebrow">For a field agent</p>
          <h2 id="docs-agent-title">Supporting your groups</h2>
          <p>
            A Village Agent or CBT sees the groups they are responsible for,
            records visits, and scores each group against the assessment.
          </p>
        </div>
        <div className="docs-shot-row" id="docs-agent-shots">
          <DocsShot
            src="/docs/30-agent-home.png"
            alt="The agent caseload listing their groups with credit bands"
            caption="Your caseload. Each group carries its credit rating, so the ones needing support are visible without opening them."
          />
          <DocsShot
            src="/docs/31-agent-group.png"
            alt="A group detail screen as an agent sees it"
            caption="A group standing, with the rating explained factor by factor rather than as a single number."
          />
          <DocsShot
            src="/docs/32-visit.png"
            alt="Recording a field visit"
            caption="Recording a visit. Location is captured and checked against the registered meeting point by the server, not by the phone."
          />
          <DocsShot
            src="/docs/33-assessment.png"
            alt="The group assessment scorecard, one section at a time"
            caption="The scorecard, one section at a time, with the running score and band visible so you can tell the group how they did before you leave."
          />
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-help-title">
        <div className="landing-section-header">
          <p className="eyebrow">If something is wrong</p>
          <h2 id="docs-help-title">Getting help</h2>
        </div>
        <div className="governance-table">
          <div className="governance-row">
            <span>The app says there is no connection</span>
            <strong>
              Keep working. Meetings, savings and loans are recorded on the phone
              and sent when there is internet. Nothing is lost.
            </strong>
          </div>
          <div className="governance-row">
            <span>A member forgot their PIN</span>
            <strong>
              It cannot be looked up — it is stored in a form nobody can read
              back. An official sets a new one for them.
            </strong>
          </div>
          <div className="governance-row">
            <span>You were signed out unexpectedly</span>
            <strong>
              Sessions end after a period of inactivity. Sign in again; the
              records already on the phone are untouched.
            </strong>
          </div>
          <div className="governance-row">
            <span>Anything else</span>
            <strong>
              Email <a href={SUPPORT_EMAIL_HREF}>{SUPPORT_EMAIL}</a>.
            </strong>
          </div>
        </div>
      </section>

      <PublicSiteFooter playStoreUrl={playStoreUrl} />
    </main>
  );
}
