"""
The words of the field agent guide.

Kept apart from the layout code so the two can be edited without stepping on
each other: a supervisor correcting a procedure should not have to read
reportlab, and a change to the page furniture should not risk the text.
"""

from __future__ import annotations

from PIL import Image
from reportlab.lib.units import mm
from reportlab.platypus import (
    Image as RLImage,
    NextPageTemplate,
    PageBreak,
    Paragraph,
    Spacer,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents

from build_field_agent_guide import (
    BUILT,
    CONTENT_W,
    GREEN,
    LINE,
    LOGO,
    ST,
    VERSION,
    BandStrip,
    Rule,
    VisitFlow,
    bullets,
    callout,
    para,
    part,
    shots,
    table,
)


def story():
    f = []

    # ── Cover ───────────────────────────────────────────────────────────────
    if LOGO.exists():
        with Image.open(LOGO) as im:
            lw, lh = im.size
        logo = RLImage(str(LOGO), width=62 * mm, height=62 * mm * lh / lw)
        logo.hAlign = "LEFT"
        f += [Spacer(1, 4 * mm), logo]

    f += [
        Spacer(1, 38 * mm),
        Paragraph("Field Agent", ST["cover_title"]),
        Paragraph("Guide", ST["cover_title"]),
        Spacer(1, 7 * mm),
        Paragraph(
            "For Village Agents and CBTs: running a visit, scoring a group, "
            "coaching its officials, and looking after what you collect.",
            ST["cover_sub"],
        ),
        Spacer(1, 8 * mm),
        Paragraph(
            "Everything on your phone works without a network. The visit is "
            "written where it happens and sent when you are back in signal.",
            ST["cover_meta"],
        ),
        Spacer(1, 56 * mm),
        Paragraph(f"{VERSION}  &nbsp;|&nbsp;  {BUILT}", ST["cover_meta"]),
        Paragraph("PhineTech Ltd &nbsp;|&nbsp; intellicash.co.ke", ST["cover_meta"]),
        NextPageTemplate("body"),
        PageBreak(),
    ]

    # ── Contents ────────────────────────────────────────────────────────────
    toc = TableOfContents()
    toc.levelStyles = [ST["toc1"], ST["toc2"]]
    toc.tableStyle = TableStyle(
        [
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 0),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ]
    )
    f += [Paragraph("Contents", ST["h1_plain"]), Rule(GREEN, 1.4, space_after=8), toc]

    # ── Part 1 ──────────────────────────────────────────────────────────────
    f += part(
        "Part 1",
        "Your job on this phone",
        "You carry a caseload of savings groups, you visit them, and you record "
        "what you find. The app does not change what a good agent does — it "
        "changes what happens to the evidence afterwards.",
    )

    f += [
        Paragraph("What an agent can and cannot do", ST["h1"]),
        para(
            "This boundary is deliberate, and it protects you as much as it "
            "protects the group. You are a witness to a group’s record, never "
            "a party to its money."
        ),
        table(
            [
                ("See your caseload", "Yes — the groups assigned to you, and no others."),
                ("See a group’s standing", "Yes — its credit rating, members, meetings and history."),
                ("Record a visit", "Yes — location, scorecard, coaching, the group’s enterprise and your notes."),
                ("Score a group", "Yes. The assessment is your professional judgement, and it is recorded as yours."),
                ("Photograph evidence", "Yes, but only from the question it answers. There is no loose camera button."),
                ("Move a group’s money", "<b>No.</b> Not a share, not a loan, not a fine. You cannot open a meeting or touch the ledger."),
                ("Change a group’s figures", "<b>No.</b> What the group recorded is theirs. You record what you saw, separately."),
                ("See another agent’s groups", "<b>No.</b> A group outside your caseload does not exist as far as your phone is concerned."),
            ],
            widths=[CONTENT_W * 0.28, CONTENT_W * 0.72],
            head=["", "Can you?"],
        ),
        callout(
            "Two ratings, and they are not the same thing",
            "The <b>credit rating</b> (A–D, out of 100) is worked out by the "
            "system from the group’s own ledger — meetings sealed, loans "
            "repaid, savings kept up. Nobody types it in. The <b>assessment "
            "band</b> (Weak to Excellent) comes from the scorecard <i>you</i> "
            "fill in with the officials. They can disagree, and when they do "
            "that is information rather than an error: a group can keep a tidy "
            "ledger and still be badly governed.",
        ),
    ]

    f += shots(
        [
            ("va01_caseload.png", "Your caseload. Each group carries its credit band, so the ones needing attention are visible before you open anything."),
            ("va02_group.png", "A group’s standing, factor by factor — and a plain list of what would raise it."),
            ("va10_account.png", "Your account: who is signed in, your language, and which server this phone talks to."),
        ]
    )

    f += [
        Paragraph("Before you set out", ST["h1"]),
        *bullets(
            [
                "<b>Open each group you plan to visit while you still have "
                "signal.</b> The app caches what it needs; a group you have never "
                "opened has nothing to show you in the field.",
                "<b>Check the assessment form has downloaded.</b> It is fetched "
                "once and then works offline. If Score the group says the form is "
                "not available, connect before you travel.",
                "<b>Read what was left open last time.</b> The visit screen shows "
                "it, but knowing before you arrive is the difference between "
                "checking a promise and discovering one.",
                "<b>Charge the phone.</b> A visit that cannot be finished is a "
                "visit that has to be written twice.",
            ]
        ),
    ]

    # ── Part 2 ──────────────────────────────────────────────────────────────
    f += part(
        "Part 2",
        "The visit",
        "One visit is one record: where you were, what you scored, what you "
        "coached, what the group thought of it, and what everyone agreed to do "
        "next. It is written on the phone as it happens and sent when you are "
        "back in signal.",
    )

    f += [
        Paragraph("The visit, end to end", ST["h1"]),
        VisitFlow(),
        Spacer(1, 4),
        table(
            [
                ("1. Open", "Choose the visit type. Five types exist because they mean different things to the office; what you capture is the same."),
                ("2. Where you are", "Your coordinates and their accuracy are read once, here. Whether that matches the group’s registered point is worked out by the office, not by your phone."),
                ("3. Last time", "Anything the group still owes from the previous visit, listed before you start so it cannot be quietly skipped."),
                ("4. Score", "The scorecard, worked through with the officials. See Part 3."),
                ("5. Coach", "What you covered, in your own words — then hand the phone over."),
                ("6. Business", "The group’s own enterprise, if it runs one."),
                ("7. Finish", "Saves the whole document on the phone. It uploads itself when you have signal."),
            ],
            widths=[CONTENT_W * 0.22, CONTENT_W * 0.78],
        ),
    ]

    f += shots(
        [
            ("va03_visit.png", "Visit type, your location with its accuracy, and anything left open from last time."),
            ("va04_visit_parts.png", "The rest of the same screen: scorecard, coaching, the group’s enterprise, your notes, and Finish."),
            ("va08_business.png", "The group’s enterprise — what they run together, and how it is doing."),
        ]
    )

    f += [
        Paragraph("About the location", ST["h1"]),
        *bullets(
            [
                "It is read <b>once</b>, when you open the visit, and only then. "
                "The app does not follow you around.",
                "A visit can be recorded with <b>no</b> location at all. If the "
                "group met somewhere unusual, record it and say so in your notes.",
                "Your phone never decides whether you were in the right place. It "
                "reports coordinates; the office compares them with the group’s "
                "registered point. There is no argument to have with the phone.",
                "<b>Never falsify it.</b> A visit recorded from somewhere you were "
                "not is the one thing here that cannot be quietly corrected later.",
            ]
        ),
        callout(
            "If the phone cannot find a fix",
            "Move into the open and tap Update location. If it still fails, record "
            "the visit without one — that is a supported case, not a failure. "
            "Waiting on GPS while a group sits in front of you is the wrong trade.",
        ),
    ]

    # ── Part 3 ──────────────────────────────────────────────────────────────
    f += part(
        "Part 3",
        "Scoring the group",
        "The scorecard is 92 points across 7 sections. It is worked through with "
        "the officials, in front of them, one section at a time — not filled "
        "in afterwards from memory on the way home.",
    )

    f += [
        Paragraph("How the scorecard works", ST["h1"]),
        *bullets(
            [
                "<b>One section per page.</b> The running score and band sit at the "
                "top the whole way, so you always know where the group stands.",
                "<b>Four answers:</b> Yes, Partial, No, Not applicable. <i>Not "
                "applicable</i> is a real answer rather than a way of skipping — "
                "it takes the points out of the total instead of scoring zero.",
                "<b>Unanswered is visible.</b> The visit screen shows how many are "
                "still blank. A half-finished scorecard is a half-finished visit.",
                "<b>It works offline</b> once the form has been downloaded once.",
            ]
        ),
        Paragraph("The bands", ST["h2"]),
        para(
            "Scored as a percentage of the points that apply, so a question marked "
            "<i>Not applicable</i> never drags a group down:"
        ),
        BandStrip(),
        Spacer(1, 2),
        table(
            [
                ("Weak", "Below 40%", "Needs close support. Agree a plan before you leave."),
                ("Fair", "40% and above", "Functioning, with gaps to close in specific areas."),
                ("Good", "60% and above", "Sound practice. Keep the weaker sections moving."),
                ("Excellent", "80% and above", "Strong across the board. A candidate to mentor other groups."),
            ],
            widths=[CONTENT_W * 0.16, CONTENT_W * 0.20, CONTENT_W * 0.64],
            head=["Band", "Score", "What it means for your next visit"],
        ),
        callout(
            "Tell the group their band before you leave",
            "You will know it before you stand up — the running total is on "
            "screen the whole time. A group that hears its score from you, with "
            "the reasons, will work on it. A group that hears it three weeks later "
            "from the office will argue with it.",
        ),
    ]

    f += [
        Paragraph("Photographs as evidence", ST["h1"]),
        *bullets(
            [
                "<b>You can only take a photo from the question it answers.</b> "
                "There is no general camera button anywhere in a visit, and that is "
                "on purpose: a picture with no claim attached to it is evidence of "
                "nothing.",
                "Each photo is bound to the group, the visit, the section, the "
                "question, you, and the time it was taken.",
                "Photos are shrunk on the phone before they queue, so a full visit "
                "does not need much data.",
                "There is a cap per visit. When you reach it the app says so rather "
                "than silently dropping one.",
                "A photo that fails to upload <b>never</b> fails the visit. The "
                "visit goes; the photo retries on its own.",
            ]
        ),
        callout(
            "Ask before you photograph anyone",
            "A register, a certificate or a cash box needs no permission. A person "
            "does. Say what the photograph is for and who will see it, and do not "
            "take it if they would rather you did not — the scorecard has a "
            "place for what you observed either way.",
            tone="warn",
        ),
    ]

    f += shots(
        [
            ("va05_assessment.png", "Section 1 of 7, with the running score and band above it. Yes, Partial, No, Not applicable — and Add photo on every question."),
        ],
        per_row=3,
    )

    # ── Part 4 ──────────────────────────────────────────────────────────────
    f += part(
        "Part 4",
        "Coaching, and being scored yourself",
        "The mentorship step records what you taught. Then you hand the phone to "
        "an official and the group scores the session. That way round is "
        "deliberate.",
    )

    f += [
        Paragraph("Recording your coaching", ST["h1"]),
        *bullets(
            [
                "Tick the topics you actually covered, from the list the office "
                "maintains — so a quarter’s coaching can be counted across "
                "every agent rather than only described.",
                "Write what you advised in your own words. This is the part a "
                "supervisor reads when a group asks for support later.",
                "Leave a topic unticked if you only mentioned it in passing. An "
                "inflated coaching record helps nobody and is easy to spot.",
            ]
        ),
        Paragraph("Then hand the phone over", ST["h2"]),
        para(
            "The group rates the session out of five on four questions: was the "
            "advice clear, was it useful, were they treated with respect, and were "
            "you prepared."
        ),
        callout(
            "The rating is theirs, not yours",
            "An agent rating their own coaching would be uniformly four or five and "
            "worth nothing in aggregate. Hand the phone to an official and step "
            "back while they answer. A visit can be recorded without a rating, but "
            "the group’s view is the only useful measure of whether the "
            "coaching landed.",
        ),
    ]

    f += shots(
        [
            ("va06_mentorship.png", "The coaching topics, maintained by the office so the same words mean the same thing across every agent."),
            ("va07_rating.png", "Then the phone goes to the group: four questions, one to five. Your score, in their hands."),
            ("va09_report.png", "Your caseload report — groups, how many rated, how many need support. Shareable from the phone."),
        ]
    )

    # ── Part 5 ──────────────────────────────────────────────────────────────
    f += part(
        "Part 5",
        "Looking after what you collect",
        "You gather other people’s personal data on somebody else’s "
        "behalf. Under the Kenya Data Protection Act, 2019 that carries duties "
        "— and they fall on you in the field, not on the office afterwards.",
    )

    f += [
        Paragraph("What a visit collects about people", ST["h1"]),
        table(
            [
                ("Your location", "Coordinates and accuracy, at the moment you open the visit. Yours, not the members’."),
                ("Photographs", "Of premises, records and — if you take them — people. Bound to a question, a visit and your name."),
                ("Assessment answers", "Your judgement of how the group is run."),
                ("Coaching notes", "Free text, which often names individuals."),
                ("The group’s enterprise", "Turnover, costs and employment for a business real people depend on."),
                ("Action items", "Who agreed to do what, and by when."),
            ],
            widths=[CONTENT_W * 0.26, CONTENT_W * 0.74],
        ),
        Paragraph("What is expected of you", ST["h1"]),
        *bullets(
            [
                "<b>Say why you are recording.</b> A group is entitled to know what "
                "you are writing down and who will read it. Tell them at the start, "
                "not when they ask.",
                "<b>Collect only what the visit needs.</b> Free-text notes are the "
                "easiest place to over-collect. Write what bears on the "
                "group’s governance and money, not what you heard about "
                "somebody’s family.",
                "<b>Photograph things, not people, unless you have asked.</b>",
                "<b>The phone is the record until it syncs.</b> Lock it. Do not lend "
                "it. Sync as soon as you have signal — which is also what "
                "protects the visit from a lost handset.",
                "<b>Never share one group’s figures with another group</b>, or "
                "with anyone outside the programme, however casually.",
                "<b>Sign out on a shared phone.</b> Your whole caseload is visible "
                "to whoever is signed in.",
            ]
        ),
        callout(
            "If a phone is lost or stolen",
            "Tell the office the same day. Everything already synced is safe on the "
            "server. Anything recorded and not yet sent is the only thing at risk, "
            "which is the whole argument for syncing after every visit rather than "
            "at the end of the week.",
            tone="stop",
        ),
        para(
            "A member may ask what is held about them, ask for it to be corrected, "
            "or complain to the Office of the Data Protection Commissioner. You do "
            "not have to answer that on the spot — pass it to the office. The "
            "full notice is at intellicash.co.ke/privacy.",
            "note",
        ),
    ]

    # ── Part 6 ──────────────────────────────────────────────────────────────
    f += part(
        "Part 6",
        "When things go wrong",
        "The handful of situations that come up often enough to be worth carrying "
        "an answer to.",
    )

    f += [
        table(
            [
                ("There is no network at the group", "Carry on. The whole visit is recorded on the phone and sent later. Only the very first sign-in needs a connection."),
                ("The scorecard says the form is not available", "The assessment form has never been downloaded on this phone. Connect once, open Score the group, and it works offline from then on."),
                ("A group is not in my caseload", "You cannot see it and cannot record against it. The office assigns caseloads — ask them rather than looking for another route."),
                ("I chose the wrong visit type", "Finish the visit and tell the office. A submitted visit is not edited on the phone; it is amended at the office, and the amendment is part of the record."),
                ("The group disputes its credit rating", "Open the group and read the factors aloud. Every line says what it came from — meetings sealed, loans repaid — and the list at the bottom says exactly what would raise it."),
                ("The group disputes your assessment", "That is a conversation, not a fault. Your score is recorded as your judgement. Note their objection in your visit notes."),
                ("Photos are not uploading", "Leave them. They retry on their own and never block the visit. If they are still queued after a day of good signal, tell the office."),
                ("I was signed out unexpectedly", "Sessions last 8 hours. Sign in again; anything already recorded on the phone is untouched."),
            ],
            widths=[CONTENT_W * 0.30, CONTENT_W * 0.70],
        )
    ]

    f += [
        Paragraph("A visit in one minute", ST["h1"]),
        para("Photograph this page, or tear it out.", "note"),
        Spacer(1, 3),
        table(
            [
                ("Before you go", "Open the group while on signal. Check the scorecard form downloads. Read last time’s open actions. Charge the phone."),
                ("On arrival", "Greet, explain what you will be recording and why. Open the visit. Let the location settle."),
                ("With the officials", "Work the scorecard section by section, out loud. Photograph from the question, never from a gallery."),
                ("Coaching", "Tick only what you covered. Write what you advised. Hand the phone over for the rating."),
                ("Before you leave", "Tell them the band and the reasons. Agree the actions and who owns each one."),
                ("Back in signal", "Open the app and let it sync. Check the visit has gone."),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
            head=["When", "What"],
        ),
        Spacer(1, 6),
        Rule(LINE, 0.6, space_after=8),
        para(
            "<b>Getting help.</b> The full guide to every screen in the app is at "
            "<b>intellicash.co.ke/docs</b>. For anything this does not answer, "
            "contact your supervisor or the office.",
            "note",
        ),
    ]

    return f
