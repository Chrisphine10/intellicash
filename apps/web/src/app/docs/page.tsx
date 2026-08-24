import React from "react";
import type { Metadata } from "next";
import Link from "next/link";
import { meetingSteps, meetingStepLabels } from "@intellicash/shared";
import { SUPPORT_EMAIL, SUPPORT_EMAIL_HREF } from "@/lib/contact";
import { PublicSiteFooter } from "../../components/public-site-footer";
import { PublicSiteHeader } from "../../components/public-site-header";
import { DocsShot } from "./docs-shot";

const playStoreUrl = "https://play.google.com/store/apps/details?id=com.intellicash.app";

export const metadata: Metadata = {
  title: "How to use Intelli-Cash | Guide",
  description:
    "A walkthrough of the Intelli-Cash phone app for savings groups, individual members, and field agents — the meeting method, the three-key unlock, offline working, and what each kind of account can do."
};

/**
 * The public guide.
 *
 * Organised by WHO is holding the phone rather than by feature, because that is
 * the question a person arrives with. A treasurer does not want a list of
 * everything the platform does; they want the screens their evening runs
 * through.
 *
 * The meeting order is read from `meetingSteps` in the shared package rather
 * than typed out here. A guide that lists the steps by hand starts lying the
 * first time one is added, and nothing connects the prose to the code.
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
            supports the groups they look after. Each has a walkthrough below,
            with the actual screens.
          </p>
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-who-title">
        <div className="landing-section-header">
          <p className="eyebrow">Before you start</p>
          <h2 id="docs-who-title">Which account do you need?</h2>
          <p>
            The kind of account you choose decides what the app shows you from
            then on. You are never asked to set up a group in order to look at
            your own savings.
          </p>
        </div>
        <div className="governance-table">
          <div className="governance-row">
            <span>Our Group</span>
            <strong>
              Keeps the group&rsquo;s record book on this phone — meetings,
              savings, loans, welfare and votes.
              <br />
              <small>
                One per group, usually held by the secretary or treasurer. Asks
                for a group name when you register.
              </small>
            </strong>
          </div>
          <div className="governance-row">
            <span>Just Me</span>
            <strong>
              Shows one person their own savings, shares and loans.
              <br />
              <small>
                Read-only. Joining a group needs a code from its officials, and
                the group approves you before you appear in their book.
              </small>
            </strong>
          </div>
          <div className="governance-row">
            <span>Field Agent</span>
            <strong>
              A Village Agent or CBT supporting several groups: caseload, visits,
              assessments and coaching.
              <br />
              <small>
                Asks for a county when you register. An agent can record what
                they see but cannot alter a group&rsquo;s money.
              </small>
            </strong>
          </div>
        </div>
        <div className="docs-shot-row">
          <DocsShot
            src="/docs/01-welcome.png"
            alt="The Intelli-Cash welcome screen with Create Account and Sign In"
            caption="The first screen. Change the language here before anything else if you would rather not work in English."
          />
          <DocsShot
            src="/docs/02-account-types.png"
            alt="Choosing between Our Group, Just Me, and Field Agent"
            caption="The three account types, each with a line describing who it is for."
          />
          <DocsShot
            src="/docs/03-sign-in.png"
            alt="The sign-in screen asking which kind of account is signing in"
            caption="Signing in asks the same question, so a shared phone can move between accounts without confusion."
          />
          <DocsShot
            src="/docs/05-signin-group.png"
            alt="The group sign-in form asking for phone number and password"
            caption="Your phone number is your sign-in. A session lasts 8 hours; the record book keeps working offline once you are in."
          />
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="docs-register-title">
        <div className="landing-section-header">
          <p className="eyebrow">Registering</p>
          <h2 id="docs-register-title">What each account asks for</h2>
          <p>
            Creating an account needs an internet connection — it is the one step
            that cannot be done offline, because the account has to exist on the
            server before this phone can hold anything against it.
          </p>
        </div>
        <div className="docs-shot-row">
          <DocsShot
            src="/docs/06-create-group.png"
            alt="Creating a group account, asking for a group name"
            caption="A group registers with its NAME, a phone number and a password. That phone number becomes the group's sign-in."
          />
          <DocsShot
            src="/docs/07-create-agent.png"
            alt="Creating a field agent account, asking for a county"
            caption="An agent registers with their own name and, optionally, the county they cover — which is how their caseload is organised."
          />
          <DocsShot
            src="/docs/04-language.png"
            alt="The language picker showing English, Kiswahili, Gikuyu, Dholuo and Kiembu"
            caption="Five languages. The ones still being translated say so rather than silently falling back to English."
          />
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-group-title">
        <div className="landing-section-header">
          <p className="eyebrow">For a group</p>
          <h2 id="docs-group-title">Running the record book</h2>
          <p>
            The group account is the record book. Every figure is written on the
            phone first and sent to the office when there is internet, so a
            meeting in a place with no signal runs exactly as it would with one.
          </p>
        </div>

        <h3>A meeting, in order</h3>
        <p className="docs-lead">
          The app walks a meeting through the same {meetingSteps.length} steps
          every time, so nothing is skipped and the minutes always read the same
          way:
        </p>
        <ol className="docs-steps">
          {meetingSteps.map((step, index) => (
            <li key={step}>
              <strong>{index + 1}. {meetingStepLabels[step]}</strong>
            </li>
          ))}
        </ol>
        <p className="docs-note">
          A meeting opens only when <strong>three officials</strong> each enter
          their own 4-digit PIN, or five members do — whichever the group
          reaches first. No single person can open the book alone, which is the
          point: the phone should be no easier to misuse than a cash box with
          three padlocks.
        </p>

        <div className="docs-shot-row" id="docs-group-shots">
          <DocsShot
            src="/docs/10-group-home.png"
            alt="The group dashboard showing savings, loans and the current cycle"
            caption="The group's money at a glance: what has been saved, what is out on loan, and where the cycle has reached."
          />
          <DocsShot
            src="/docs/11-meeting-hub.png"
            alt="The meeting screen with actions for shares, loans, voting and welfare"
            caption="A meeting in progress. Shares, loans, attendance, voting and welfare are all reached from here."
          />
          <DocsShot
            src="/docs/12-unlock.png"
            alt="The three-key unlock asking officials for their PINs"
            caption="The three-key unlock. Each official enters their own PIN; the app counts the keys turned rather than trusting one person."
          />
          <DocsShot
            src="/docs/13-members.png"
            alt="The member list for a group"
            caption="Members, their roles and their standing. Savings and loan history follow the person, so correcting a name never loses their record."
          />
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="docs-member-title">
        <div className="landing-section-header">
          <p className="eyebrow">For a member</p>
          <h2 id="docs-member-title">Checking your own savings</h2>
          <p>
            A personal account shows one person their own record — the same
            figures the group&rsquo;s book holds, not a separate copy that can
            drift out of step.
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
            caption="Your passbook: every contribution and repayment in order, with the meeting each belongs to."
          />
          <DocsShot
            src="/docs/22-member-join.png"
            alt="Joining a group using a code"
            caption="Join a group with the code its officials give you. They approve the request before you appear in their book."
          />
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-agent-title">
        <div className="landing-section-header">
          <p className="eyebrow">For a field agent</p>
          <h2 id="docs-agent-title">Supporting your groups</h2>
          <p>
            An agent sees the groups they are responsible for, records visits,
            and scores each group against the assessment. What an agent writes is
            evidence about a group — it never moves the group&rsquo;s money.
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
            caption="A group's standing, with the rating explained factor by factor rather than as a single number."
          />
          <DocsShot
            src="/docs/32-visit.png"
            alt="Recording a field visit"
            caption="Recording a visit. Location is captured and checked against the group's registered meeting point by the server, not by the phone."
          />
          <DocsShot
            src="/docs/33-assessment.png"
            alt="The group assessment scorecard, one section at a time"
            caption="The scorecard, one section at a time, with the running score and band visible so you can tell the group how they did before you leave."
          />
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="docs-offline-title">
        <div className="landing-section-header">
          <p className="eyebrow">Working without signal</p>
          <h2 id="docs-offline-title">What happens with no internet</h2>
        </div>
        <div className="governance-table">
          <div className="governance-row">
            <span>Runs offline</span>
            <strong>
              Meetings, attendance, share purchases, loan repayments, welfare,
              votes and the whole record book. Everything is written to the phone
              as it happens.
            </strong>
          </div>
          <div className="governance-row">
            <span>Needs internet</span>
            <strong>
              Creating an account, signing in for the first time, joining a
              group, and anything that reads live figures from the office.
            </strong>
          </div>
          <div className="governance-row">
            <span>How it catches up</span>
            <strong>
              Records queue on the phone and are sent when signal returns. A
              record is only removed from the phone once the office has confirmed
              it — an interrupted upload is retried, never dropped.
            </strong>
          </div>
        </div>
      </section>

      <section className="landing-section" aria-labelledby="docs-security-title">
        <div className="landing-section-header">
          <p className="eyebrow">Keeping the book safe</p>
          <h2 id="docs-security-title">PINs, passwords and who can see what</h2>
        </div>
        <div className="governance-table">
          <div className="governance-row">
            <span>Your password</span>
            <strong>
              Signs you in to your account. At least 6 characters, and never
              shown back to anyone — not even to support staff.
            </strong>
          </div>
          <div className="governance-row">
            <span>Your meeting PIN</span>
            <strong>
              4 digits, chosen by you, used to turn your key when a meeting
              opens. Obvious values like 1234 or 0000 are refused.
            </strong>
          </div>
          <div className="governance-row">
            <span>If a PIN is forgotten</span>
            <strong>
              It cannot be looked up. It is stored in a form nobody can read
              back, so an official sets a new one instead.
            </strong>
          </div>
          <div className="governance-row">
            <span>Who sees a member&rsquo;s details</span>
            <strong>
              The member, their own group, and platform administrators. Everyone
              else sees a phone number masked to its last three digits. Full
              detail is in the{" "}
              <Link href="/privacy">privacy notice</Link>.
            </strong>
          </div>
        </div>
      </section>

      <section className="landing-section governance-band" aria-labelledby="docs-words-title">
        <div className="landing-section-header">
          <p className="eyebrow">The words used</p>
          <h2 id="docs-words-title">Terms you will see</h2>
        </div>
        <div className="governance-table">
          <div className="governance-row">
            <span>Share</span>
            <strong>
              One unit of savings. A group sets what a share is worth and how
              many a member may buy at a meeting.
            </strong>
          </div>
          <div className="governance-row">
            <span>Social fund</span>
            <strong>
              A small separate pot for emergencies — kept apart from savings, and
              paid out by agreement rather than lent.
            </strong>
          </div>
          <div className="governance-row">
            <span>Cycle</span>
            <strong>
              One saving period, usually a year. At the end the group shares out
              what it holds and starts again.
            </strong>
          </div>
          <div className="governance-row">
            <span>Share-out</span>
            <strong>
              Closing a cycle: every member receives their savings plus their
              portion of what the group earned.
            </strong>
          </div>
          <div className="governance-row">
            <span>Credit rating</span>
            <strong>
              A score worked out from the group&rsquo;s own record — meetings
              held, repayments made, savings kept up. It is calculated, never
              entered by hand.
            </strong>
          </div>
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
              It cannot be looked up. An official sets a new one for them, and
              the member chooses 4 digits of their own.
            </strong>
          </div>
          <div className="governance-row">
            <span>You were signed out unexpectedly</span>
            <strong>
              Sessions last 8 hours. Sign in again; the records already on the
              phone are untouched.
            </strong>
          </div>
          <div className="governance-row">
            <span>A meeting will not open</span>
            <strong>
              Three officials must each turn their key, or five members. Check
              who has entered a PIN — the screen shows the count so far.
            </strong>
          </div>
          <div className="governance-row">
            <span>A figure looks wrong</span>
            <strong>
              Records are append-only: nothing is edited away. Raise it with your
              officials, who can add a correcting entry that leaves the original
              visible.
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
