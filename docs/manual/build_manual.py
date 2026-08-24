"""
Builds the Intelli-Cash user manual as a PDF.

Why a script rather than a hand-made document: the manual has to be rebuilt
every time a screen changes, and a PDF nobody can regenerate goes stale the
first week. The screenshots come from `apps/web/public/docs/`, the same
captures the public guide uses, so the manual and the website can never drift
apart into two different pictures of the same screen.

Run from anywhere:

    python docs/manual/build_manual.py

Requires reportlab and Pillow (Pillow decodes the WebP captures).
"""

from __future__ import annotations

import io
import sys
from datetime import date
from pathlib import Path

from PIL import Image
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.lib.utils import ImageReader
from reportlab.platypus import (
    BaseDocTemplate,
    CondPageBreak,
    Flowable,
    Frame,
    Image as RLImage,
    KeepTogether,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents

# ── Where things are ────────────────────────────────────────────────────────
HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
SHOTS = REPO / "apps" / "web" / "public" / "docs"
LOGO = REPO / "apps" / "web" / "public" / "brand" / "intelli-cash-logo.png"
OUTPUT = HERE / "Intelli-Cash-User-Manual.pdf"

# ── Brand, taken from apps/web/src/app/globals.css ──────────────────────────
GREEN = colors.HexColor("#027a48")
GREEN_BRIGHT = colors.HexColor("#00c853")
GREEN_DEEP = colors.HexColor("#063f20")
TINT = colors.HexColor("#ecfdf3")
INK = colors.HexColor("#101828")
INK_SOFT = colors.HexColor("#344054")
MUTED = colors.HexColor("#667085")
LINE = colors.HexColor("#e4e7ec")
SURFACE = colors.HexColor("#f9fafb")
GOLD = colors.HexColor("#f2a81d")

PAGE_W, PAGE_H = A4
MARGIN = 20 * mm
CONTENT_W = PAGE_W - 2 * MARGIN

VERSION = "For app version 2.4.2"
BUILT = date.today().strftime("%d %B %Y")


# ── Styles ──────────────────────────────────────────────────────────────────
def build_styles():
    base = getSampleStyleSheet()
    s = {}

    s["body"] = ParagraphStyle(
        "body",
        parent=base["BodyText"],
        fontName="Helvetica",
        fontSize=9.6,
        leading=14.6,
        textColor=INK_SOFT,
        alignment=TA_JUSTIFY,
        spaceAfter=7,
    )
    s["lead"] = ParagraphStyle(
        "lead", parent=s["body"], fontSize=10.6, leading=16, textColor=INK, spaceAfter=9
    )
    s["part"] = ParagraphStyle(
        "part",
        parent=base["Heading1"],
        fontName="Helvetica-Bold",
        fontSize=26,
        leading=30,
        textColor=GREEN_DEEP,
        spaceBefore=0,
        spaceAfter=4,
    )
    s["part_kicker"] = ParagraphStyle(
        "part_kicker",
        parent=s["body"],
        fontName="Helvetica-Bold",
        fontSize=8.5,
        leading=11,
        textColor=GREEN,
        alignment=0,
        spaceAfter=3,
    )
    s["h1"] = ParagraphStyle(
        "h1",
        parent=base["Heading1"],
        fontName="Helvetica-Bold",
        fontSize=16,
        leading=20,
        textColor=GREEN_DEEP,
        spaceBefore=14,
        spaceAfter=6,
    )
    # Same look as h1, but afterFlowable ignores it — used for "Contents",
    # which must not list itself.
    s["h1_plain"] = ParagraphStyle("h1_plain", parent=s["h1"])
    s["h2"] = ParagraphStyle(
        "h2",
        parent=base["Heading2"],
        fontName="Helvetica-Bold",
        fontSize=11.5,
        leading=15,
        textColor=INK,
        spaceBefore=11,
        spaceAfter=4,
    )
    s["h3"] = ParagraphStyle(
        "h3",
        parent=base["Heading3"],
        fontName="Helvetica-Bold",
        fontSize=9.8,
        leading=13,
        textColor=GREEN,
        spaceBefore=8,
        spaceAfter=3,
    )
    s["bullet"] = ParagraphStyle(
        "bullet",
        parent=s["body"],
        leftIndent=11,
        bulletIndent=2,
        spaceAfter=3.5,
        alignment=0,
    )
    s["step"] = ParagraphStyle(
        "step", parent=s["bullet"], leftIndent=16, bulletIndent=2
    )
    s["caption"] = ParagraphStyle(
        "caption",
        parent=s["body"],
        fontSize=8.2,
        leading=11.4,
        textColor=MUTED,
        alignment=0,
        spaceBefore=4,
        spaceAfter=0,
    )
    s["cell"] = ParagraphStyle(
        "cell", parent=s["body"], fontSize=8.6, leading=12, spaceAfter=0, alignment=0
    )
    s["cell_head"] = ParagraphStyle(
        "cell_head",
        parent=s["cell"],
        fontName="Helvetica-Bold",
        textColor=colors.white,
    )
    s["cell_key"] = ParagraphStyle(
        "cell_key", parent=s["cell"], fontName="Helvetica-Bold", textColor=INK
    )
    s["note"] = ParagraphStyle(
        "note",
        parent=s["body"],
        fontSize=8.8,
        leading=12.8,
        textColor=INK_SOFT,
        alignment=0,
        spaceAfter=0,
    )
    # spaceAfter is reset to nearly nothing: it is inherited from `body`, where
    # 7pt between paragraphs is right and 7pt between contents lines is a blank
    # line that pushed the table onto a second page.
    s["toc1"] = ParagraphStyle(
        "toc1",
        parent=s["body"],
        fontName="Helvetica-Bold",
        fontSize=10.5,
        leading=15,
        textColor=GREEN_DEEP,
        spaceBefore=9,
        spaceAfter=1,
        alignment=0,
    )
    s["toc2"] = ParagraphStyle(
        "toc2",
        parent=s["body"],
        fontSize=9.2,
        leading=12.5,
        leftIndent=12,
        textColor=INK_SOFT,
        spaceAfter=1,
        alignment=0,
    )
    s["cover_title"] = ParagraphStyle(
        "cover_title",
        parent=base["Title"],
        fontName="Helvetica-Bold",
        fontSize=34,
        leading=39,
        textColor=colors.white,
        alignment=0,
    )
    s["cover_sub"] = ParagraphStyle(
        "cover_sub",
        parent=s["body"],
        fontSize=12.5,
        leading=18,
        textColor=colors.HexColor("#c9f2da"),
        alignment=0,
    )
    s["cover_meta"] = ParagraphStyle(
        "cover_meta",
        parent=s["body"],
        fontSize=9,
        leading=14,
        textColor=colors.HexColor("#9fd9b6"),
        alignment=0,
    )
    return s


ST = build_styles()


# ── Small flowables ─────────────────────────────────────────────────────────
class Rule(Flowable):
    """A hairline the width of the frame."""

    def __init__(self, colour=LINE, thickness=0.6, space_before=2, space_after=6):
        Flowable.__init__(self)
        self.colour = colour
        self.thickness = thickness
        self.space_before = space_before
        self.space_after = space_after
        self.width = 0

    def wrap(self, avail_w, avail_h):
        self.width = avail_w
        return avail_w, self.thickness + self.space_before + self.space_after

    def draw(self):
        self.canv.setStrokeColor(self.colour)
        self.canv.setLineWidth(self.thickness)
        y = self.space_after
        self.canv.line(0, y, self.width, y)


def para(text, style="body"):
    return Paragraph(text, ST[style])


def bullets(items, style="bullet", marker="•"):
    return [Paragraph(t, ST[style], bulletText=marker) for t in items]


def steps(items):
    return [
        Paragraph(t, ST["step"], bulletText=f"{i}.") for i, t in enumerate(items, start=1)
    ]


def table(rows, widths=None, head=None):
    """A two- or three-column reference table with the house look."""
    data = []
    style = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, LINE),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
    ]
    offset = 0
    if head:
        data.append([Paragraph(h, ST["cell_head"]) for h in head])
        style += [
            ("BACKGROUND", (0, 0), (-1, 0), GREEN),
            ("LINEBELOW", (0, 0), (-1, 0), 0.4, GREEN),
        ]
        offset = 1

    for r_i, row in enumerate(rows):
        cells = [Paragraph(row[0], ST["cell_key"])]
        cells += [Paragraph(c, ST["cell"]) for c in row[1:]]
        data.append(cells)
        if (r_i % 2) == 1:
            style.append(
                ("BACKGROUND", (0, r_i + offset), (-1, r_i + offset), SURFACE)
            )

    if widths is None:
        n = len(rows[0])
        widths = [CONTENT_W * 0.30] + [CONTENT_W * 0.70 / (n - 1)] * (n - 1)

    t = Table(data, colWidths=widths, hAlign="LEFT", repeatRows=1 if head else 0)
    t.setStyle(TableStyle(style))
    return t


def callout(title, text, tone="info"):
    """A tinted box for the things people get wrong."""
    fill, edge = (TINT, GREEN)
    if tone == "warn":
        fill, edge = (colors.HexColor("#fff8e6"), GOLD)

    inner = [
        Paragraph(f"<b>{title}</b>", ST["note"]),
        Spacer(1, 2.5),
        Paragraph(text, ST["note"]),
    ]
    t = Table([[inner]], colWidths=[CONTENT_W], hAlign="LEFT")
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), fill),
                ("LINEBEFORE", (0, 0), (0, -1), 2.4, edge),
                ("BOX", (0, 0), (-1, -1), 0.4, edge),
                ("LEFTPADDING", (0, 0), (-1, -1), 9),
                ("RIGHTPADDING", (0, 0), (-1, -1), 9),
                ("TOPPADDING", (0, 0), (-1, -1), 7),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
            ]
        )
    )
    return KeepTogether([Spacer(1, 3), t, Spacer(1, 7)])


_shot_cache: dict[str, io.BytesIO] = {}


def _png_of(name: str) -> io.BytesIO | None:
    """WebP capture -> PNG buffer reportlab can place."""
    if name in _shot_cache:
        buf = _shot_cache[name]
        buf.seek(0)
        return buf

    path = SHOTS / name
    if not path.exists():
        return None

    buf = io.BytesIO()
    with Image.open(path) as im:
        im.convert("RGB").save(buf, "PNG")
    buf.seek(0)
    _shot_cache[name] = buf
    return buf


def shots(entries, per_row=3, height=97 * mm):
    """
    A row of phone screens with captions underneath.

    Missing files are skipped rather than drawn as a gap: this manual is
    rebuilt as screens are recaptured, and half a row of real screenshots
    still teaches somebody something.
    """
    cells, captions = [], []
    col_w = (CONTENT_W - (per_row - 1) * 6 * mm) / per_row

    for name, caption in entries:
        buf = _png_of(name)
        if buf is None:
            print(f"  ! missing screenshot: {name}", file=sys.stderr)
            continue
        iw, ih = ImageReader(buf).getSize()
        w = height * iw / ih
        if w > col_w:
            w = col_w
            height_used = col_w * ih / iw
        else:
            height_used = height
        buf.seek(0)
        img = RLImage(buf, width=w, height=height_used)
        img.hAlign = "CENTER"
        cells.append(img)
        captions.append(Paragraph(caption, ST["caption"]))

    if not cells:
        return []

    out = []
    for i in range(0, len(cells), per_row):
        row_imgs = cells[i : i + per_row]
        row_caps = captions[i : i + per_row]
        pad = per_row - len(row_imgs)
        row_imgs += [""] * pad
        row_caps += [""] * pad

        t = Table(
            [row_imgs, row_caps],
            colWidths=[col_w] * per_row,
            hAlign="LEFT",
        )
        t.setStyle(
            TableStyle(
                [
                    ("VALIGN", (0, 0), (-1, 0), "BOTTOM"),
                    ("VALIGN", (0, 1), (-1, 1), "TOP"),
                    ("ALIGN", (0, 0), (-1, 0), "CENTER"),
                    ("LEFTPADDING", (0, 0), (-1, -1), 0),
                    ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
                    ("TOPPADDING", (0, 0), (-1, 0), 4),
                    ("BOTTOMPADDING", (0, 1), (-1, 1), 10),
                ]
            )
        )
        out.append(CondPageBreak(height + 24 * mm))
        out.append(KeepTogether(t))
    return out


# ── Document template: cover, then numbered body pages ──────────────────────
class Manual(BaseDocTemplate):
    def __init__(self, path):
        BaseDocTemplate.__init__(
            self,
            path,
            pagesize=A4,
            leftMargin=MARGIN,
            rightMargin=MARGIN,
            topMargin=MARGIN + 8 * mm,
            bottomMargin=MARGIN,
            title="Intelli-Cash User Manual",
            author="PhineTech Ltd",
            subject="How to run a savings group with Intelli-Cash",
        )
        frame = Frame(
            MARGIN,
            MARGIN,
            CONTENT_W,
            PAGE_H - MARGIN - (MARGIN + 8 * mm),
            id="body",
            leftPadding=0,
            rightPadding=0,
            topPadding=0,
            bottomPadding=0,
        )
        self.addPageTemplates(
            [
                PageTemplate(id="cover", frames=[frame], onPage=self.cover_page),
                PageTemplate(id="body", frames=[frame], onPage=self.body_page),
            ]
        )
        # Which part each page belongs to, so the running header can name it.
        #
        # It cannot be tracked live: `onPage` fires at the START of a page,
        # before any of that page's flowables have been seen, so a part's first
        # page would carry the previous part's name. The map is therefore built
        # during one pass and read during the next — which multiBuild gives
        # us for free, since the table of contents already needs two.
        self._part_pages: dict[int, str] = {}
        self._pending_parts: dict[int, str] = {}

    def beforeDocument(self):
        self._part_pages = dict(self._pending_parts)
        self._pending_parts = {}

    def part_for_page(self, page: int) -> str:
        starts = [p for p in self._part_pages if p <= page]
        return self._part_pages[max(starts)] if starts else ""

    # The heading levels the TOC listens to.
    def afterFlowable(self, flowable):
        if not isinstance(flowable, Paragraph):
            return
        style = flowable.style.name
        # `self.page - 1` throughout: the cover is unnumbered, so the printed
        # folio is always one behind the physical page.
        if style == "part":
            title = flowable.getPlainText()
            self._pending_parts[self.page] = title
            self.notify("TOCEntry", (0, title, self.page - 1))
        elif style == "h1":
            self.notify("TOCEntry", (1, flowable.getPlainText(), self.page - 1))

    def cover_page(self, canv, doc):
        canv.saveState()
        canv.setFillColor(GREEN_DEEP)
        canv.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)

        # The logo carries a dark wordmark, which disappears entirely on the
        # dark ground. It sits on a white chip instead of being recoloured.
        canv.setFillColor(colors.white)
        canv.roundRect(MARGIN, PAGE_H - 62 * mm, 78 * mm, 34 * mm, 4 * mm, stroke=0, fill=1)

        # A band of the brighter green low on the page, clear of the type.
        canv.setFillColor(GREEN)
        canv.rect(0, 74 * mm, PAGE_W, 5 * mm, stroke=0, fill=1)
        canv.setFillColor(GREEN_BRIGHT)
        canv.rect(0, 74 * mm, 58 * mm, 5 * mm, stroke=0, fill=1)
        canv.restoreState()

    def body_page(self, canv, doc):
        canv.saveState()
        canv.setFont("Helvetica", 7.6)
        canv.setFillColor(MUTED)
        canv.drawString(MARGIN, PAGE_H - MARGIN - 3 * mm, "Intelli-Cash User Manual")
        part_name = self.part_for_page(doc.page)
        if part_name:
            canv.drawRightString(
                PAGE_W - MARGIN, PAGE_H - MARGIN - 3 * mm, part_name
            )
        canv.setStrokeColor(LINE)
        canv.setLineWidth(0.5)
        canv.line(
            MARGIN,
            PAGE_H - MARGIN - 5 * mm,
            PAGE_W - MARGIN,
            PAGE_H - MARGIN - 5 * mm,
        )
        canv.line(MARGIN, MARGIN - 4 * mm, PAGE_W - MARGIN, MARGIN - 4 * mm)
        canv.setFont("Helvetica", 7.6)
        canv.drawString(MARGIN, MARGIN - 9 * mm, "intellicash.co.ke")
        canv.drawRightString(PAGE_W - MARGIN, MARGIN - 9 * mm, str(doc.page - 1))
        canv.restoreState()


def part(kicker, title, blurb):
    """A part divider: kicker, big title, one paragraph saying what it covers."""
    return [
        PageBreak(),
        Spacer(1, 14 * mm),
        para(kicker.upper(), "part_kicker"),
        Paragraph(title, ST["part"]),
        Rule(GREEN, 2.0, space_before=4, space_after=10),
        para(blurb, "lead"),
        Spacer(1, 3 * mm),
    ]


# ── The manual ──────────────────────────────────────────────────────────────
def story():
    f = []

    # Cover ------------------------------------------------------------------
    if LOGO.exists():
        with Image.open(LOGO) as im:
            lw, lh = im.size
        logo = RLImage(str(LOGO), width=64 * mm, height=64 * mm * lh / lw)
        logo.hAlign = "LEFT"
        f += [Spacer(1, 5 * mm), logo]
    f += [
        Spacer(1, 40 * mm),
        Paragraph("Intelli-Cash", ST["cover_title"]),
        Paragraph("User Manual", ST["cover_title"]),
        Spacer(1, 7 * mm),
        Paragraph(
            "Running a savings group, and keeping your own record, "
            "on a phone that works without internet.",
            ST["cover_sub"],
        ),
        Spacer(1, 8 * mm),
        Paragraph(
            "Every screen the group account and the member account hold, "
            "what each one is for, and the rules behind them.",
            ST["cover_meta"],
        ),
        Spacer(1, 62 * mm),
        Paragraph(f"{VERSION}  &nbsp;|&nbsp;  {BUILT}", ST["cover_meta"]),
        Paragraph("PhineTech Ltd &nbsp;|&nbsp; intellicash.co.ke", ST["cover_meta"]),
        NextPageTemplate("body"),
        PageBreak(),
    ]

    # Contents ---------------------------------------------------------------
    toc = TableOfContents()
    toc.levelStyles = [ST["toc1"], ST["toc2"]]
    # reportlab lays the contents out as a two-column table and pads every row,
    # which added ~17pt per line on top of the leading and pushed the list onto
    # a second page for the sake of five entries. The padding is the thing to
    # remove, not the type size.
    toc.tableStyle = TableStyle(
        [
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 0),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ]
    )
    f += [
        Paragraph("Contents", ST["h1_plain"]),
        Rule(GREEN, 1.4, space_after=8),
        toc,
    ]

    # ── Part 1 ─────────────────────────────────────────────────────────────
    f += part(
        "Part 1",
        "Before you start",
        "Intelli-Cash puts a savings group's record book on a phone. The book is "
        "written on the phone first and sent to the office when there is signal, "
        "so a meeting held where there is no network runs exactly as it would "
        "with one. This part covers the choice you make once — which kind of "
        "account you need — and the few things that do require internet.",
    )

    f += [Paragraph("Which account do you need?", ST["h1"])]
    f += [
        para(
            "The kind of account you choose decides what the app shows you from "
            "then on. You are never asked to set up a group in order to look at "
            "your own savings."
        ),
        table(
            [
                (
                    "Our Group",
                    "Keeps the group's record book on this phone: meetings, "
                    "attendance, savings, shares, loans, fines, welfare and votes.",
                    "One per group, usually held by the secretary or treasurer. "
                    "Registration asks for a group name.",
                ),
                (
                    "Just Me",
                    "Shows one person their own savings, shares and loans, across "
                    "every group they belong to.",
                    "Read-only. Joining a group needs a code from its officials, "
                    "and the group approves you before you appear in their book.",
                ),
                (
                    "Field Agent",
                    "A Village Agent or CBT supporting several groups: caseload, "
                    "visits, assessments and coaching.",
                    "Registration asks for the county covered. An agent records "
                    "what they see but never moves a group's money.",
                ),
            ],
            widths=[CONTENT_W * 0.16, CONTENT_W * 0.44, CONTENT_W * 0.40],
            head=["Account", "What it does", "Who holds it"],
        ),
        Spacer(1, 4),
    ]

    f += shots(
        [
            ("01-welcome.webp", "The first screen. Change the language here before anything else if you would rather not work in English."),
            ("02-account-types.webp", "The three account types, each with a line saying who it is for."),
            ("03-sign-in.webp", "Signing in asks the same question, so a shared phone can move between accounts without confusion."),
        ]
    )

    f += [Paragraph("Registering and signing in", ST["h1"])]
    f += [
        para(
            "Creating an account is the one step that cannot be done offline: the "
            "account has to exist on the server before this phone can hold "
            "anything against it. Everything after that works without signal."
        ),
        *bullets(
            [
                "<b>Your phone number is your sign-in.</b> A group registers with "
                "its name, a phone number and a password; that number becomes the "
                "group's sign-in.",
                "<b>A password is at least 6 characters</b> and is never shown "
                "back to anyone, including support staff.",
                "<b>A session lasts 8 hours.</b> When it ends the app takes you "
                "back to sign-in; the records already on the phone are untouched.",
                "<b>Five languages.</b> The ones still being checked by native "
                "speakers say so, rather than silently falling back to English.",
            ]
        ),
        Spacer(1, 3),
    ]

    f += shots(
        [
            ("05-signin-group.webp", "A group registers with its name, a phone number and a password."),
            ("07-create-agent.webp", "An agent registers with their own name and the county they cover."),
            ("04-language.webp", "Five languages, set per phone, changeable at any time."),
        ]
    )

    f += [Paragraph("What needs internet, and what does not", ST["h1"])]
    f += [
        table(
            [
                (
                    "Works offline",
                    "Meetings, attendance, share purchases, loan repayments and "
                    "applications, fines, welfare payments, votes and the whole "
                    "record book. Every figure is written to the phone as it "
                    "happens.",
                ),
                (
                    "Needs internet",
                    "Creating an account, signing in for the first time, joining a "
                    "group, and anything that reads live figures from the office.",
                ),
                (
                    "How it catches up",
                    "Records queue on the phone and are sent when signal returns. "
                    "A record is removed from the phone only once the office has "
                    "confirmed it, so an interrupted upload is retried, never "
                    "dropped.",
                ),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
        ),
        callout(
            "You cannot lose a meeting to a bad network",
            "Nothing in a meeting waits for signal. If the app says there is no "
            "connection, keep working — the record book behaves identically. "
            "The only visible difference is the sync mark in the corner of the "
            "dashboard, which tells you whether the office has today's figures yet.",
        ),
    ]

    # ── Part 2 ─────────────────────────────────────────────────────────────
    f += part(
        "Part 2",
        "For the group",
        "The group account is the record book. This part covers every screen it "
        "holds — the dashboard, the eight steps of a meeting, members and "
        "their roles, loans, fines, welfare, voting, the group's rules, saving "
        "cycles and the share-out, reports, and the settings that only an "
        "official touches.",
    )

    f += [Paragraph("The dashboard", ST["h1"])]
    f += [
        para(
            "The first screen after signing in as a group. Six figures, each one "
            "derived from the record book rather than typed in by anyone:"
        ),
        table(
            [
                ("Total Savings", "Everything members have paid in — shares plus social fund."),
                ("Active Loans", "What is currently lent out and not yet repaid."),
                ("Members", "Active members in the group's book."),
                ("Meetings", "Meetings held in the current cycle."),
                ("Fines Collected", "Fines recorded and received during the cycle."),
                ("Social Fund", "What the welfare pot currently holds."),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
        ),
        Spacer(1, 4),
    ]

    f += shots(
        [
            ("10-group-home.webp", "The dashboard, with the sync state in the corner so you always know whether the office has today's figures."),
            ("11-meetings.webp", "The meetings list: what has been held, what is open, and what is sealed."),
            ("13-members.webp", "The member list, showing each member's savings and loan standing."),
        ]
    )

    f += [Paragraph("A meeting, in order", ST["h1"])]
    f += [
        para(
            "The app walks a meeting through the same eight steps every time, so "
            "nothing is skipped and the minutes always read the same way:"
        ),
        *steps(
            [
                "<b>Opening &amp; 3-Key Security</b> — the meeting is unlocked.",
                "<b>Minutes Review</b> — last meeting's record is confirmed.",
                "<b>Social Fund Round</b> — the welfare contribution is collected.",
                "<b>Loan Repayments</b> — money coming back in.",
                "<b>Share Purchase</b> — savings going in.",
                "<b>Loan Applications</b> — requests heard and decided.",
                "<b>Resolutions &amp; General Votes</b> — anything the group must agree on.",
                "<b>Closing</b> — the meeting is sealed.",
            ]
        ),
        Spacer(1, 4),
    ]

    f += [Paragraph("Opening a meeting: the three-key unlock", ST["h1"])]
    f += [
        para(
            "A meeting opens only when <b>three officials</b> each enter their own "
            "4-digit PIN — or <b>five members</b> do, whichever the group "
            "reaches first. A group with fewer than five active members needs all "
            "of them. No single person can open the book alone, which is the "
            "point: the phone should be no easier to misuse than a cash box with "
            "three padlocks."
        ),
        *bullets(
            [
                "The screen counts as it goes — <i>0 of 3 officials, 0 of 5 "
                "members</i> — so the room can see how close the meeting is "
                "to opening and who still has to confirm.",
                "Each person sets their own PIN the first time they turn a key. "
                "Nobody else, including the phone holder, ever sees it.",
                "The same unlock is required again to close and seal the meeting.",
            ]
        ),
        Spacer(1, 3),
    ]

    f += shots(
        [
            ("12-unlock.webp", "Turning the keys. The count is visible to the whole room."),
            ("11-meeting-hub.webp", "Inside an open meeting: every action the group takes that evening, in one place."),
            ("16-attendance.webp", "Attendance, taken once and editable while the meeting is still open."),
        ]
    )

    f += [Paragraph("What happens inside a meeting", ST["h1"])]
    f += [
        para(
            "Once a meeting is open, everything the group does that evening is "
            "recorded from one screen:"
        ),
        table(
            [
                (
                    "Attendance",
                    "Who is present, absent or late. Editable while the meeting is "
                    "open; sealed with the meeting at closing.",
                ),
                (
                    "Social Fund",
                    "The welfare contribution, member by member. This money goes "
                    "into the social pot, not into savings, and is never lent out.",
                ),
                (
                    "Buy Shares",
                    "Savings. A member buys a number of shares at the value the "
                    "group has set. The group's rules cap how many may be bought "
                    "at one meeting.",
                ),
                (
                    "Record Fine",
                    "A fine agreed by the group — lateness, absence, a broken "
                    "rule. Unpaid fines are taken off the member's share-out payout "
                    "and never block them from sharing out.",
                ),
                (
                    "Disburse Loan",
                    "Money going out to a member whose application the group "
                    "approved. The loan fund has to hold enough for it.",
                ),
                (
                    "Repayment",
                    "Money coming back in against a member's loan, principal and "
                    "interest together.",
                ),
                (
                    "Share Records",
                    "The share ledger: who bought what, at which meeting.",
                ),
                (
                    "Voting",
                    "Resolutions and elections — see the next section.",
                ),
                (
                    "Welfare",
                    "A payment out of the social fund to a member in need, agreed "
                    "by the group.",
                ),
                (
                    "Intelli-Stores",
                    "Goods bought on credit through the platform's store, recorded "
                    "against the group.",
                ),
                (
                    "External Loans",
                    "Money the group has borrowed from outside — a bank, a "
                    "programme, a federation — kept separate from members' "
                    "own savings.",
                ),
                (
                    "Close &amp; Lock",
                    "Seals the meeting. Requires the three-key unlock again. A "
                    "sealed meeting is part of the permanent record.",
                ),
            ],
            widths=[CONTENT_W * 0.22, CONTENT_W * 0.78],
        ),
        callout(
            "Sealing is what makes the record trustworthy",
            "A sealed meeting cannot be quietly edited afterwards. Everything "
            "financial is written once and never overwritten — a correction "
            "is a new entry that explains itself, not a change to the old one. "
            "This is why the group's credit rating can be trusted by a lender who "
            "was not in the room.",
        ),
    ]

    f += [Paragraph("Voting and elections", ST["h1"])]
    f += [
        para(
            "Decisions the group must agree on are recorded as votes rather than "
            "as a line in someone's notes. One member, one vote. A decision "
            "records the tally; an election can be secret."
        ),
        para("The kinds of resolution the app knows about:"),
        table(
            [
                ("Loan approval", "An internal loan to a member, before it is disbursed."),
                ("External loan approval", "Borrowing from outside the group."),
                ("Grant application", "Applying for a grant."),
                ("Social fund grant", "Paying welfare out to a member."),
                ("Officer election", "Choosing chairperson, secretary, treasurer and the rest."),
                ("Constitution amendment", "Changing the group's own rules."),
                ("Member expulsion", "Removing a member."),
                ("Federation membership", "Joining a VSLF federation."),
                ("Fine waiver", "Forgiving a fine."),
                ("Minutes approval", "Confirming the previous meeting's record."),
            ],
            widths=[CONTENT_W * 0.28, CONTENT_W * 0.72],
        ),
        Spacer(1, 4),
    ]

    f += shots(
        [
            ("18-voting.webp", "A vote records the tally. An election can be secret."),
            ("17-welfare.webp", "The welfare fund: what is left, and what has been paid out this cycle."),
            ("14-loans.webp", "The loan portfolio, empty until the group disburses one. Loans appear here once approved at a meeting and paid out."),
        ]
    )

    f += [Paragraph("Members, roles and join requests", ST["h1"])]
    f += [
        para(
            "The member list is the group's roll. From it an official can add a "
            "member, correct a name or phone number, set roles, and approve people "
            "asking to join."
        ),
        Paragraph("Roles", ST["h2"]),
        table(
            [
                ("Chairperson", "Chairs the meeting. An official, so their PIN counts as one of the three keys."),
                ("Secretary", "Keeps the record. Usually holds the group phone. An official."),
                ("Treasurer", "Responsible for the money. An official."),
                ("Money counter", "Counts cash at the meeting."),
                ("Key holder", "Holds one of the box keys."),
                ("Member", "An ordinary member — saves, borrows, attends and votes."),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
        ),
        Paragraph("Editing a member", ST["h2"]),
        *bullets(
            [
                "Open a member and tap <b>Edit</b> to correct a misspelled name or "
                "change a phone number. The member keeps their savings, shares and "
                "loan history.",
                "Two members can never end up sharing one phone number — the "
                "number is how a person is identified across the whole system.",
            ]
        ),
        Paragraph("People asking to join", ST["h2"]),
        *bullets(
            [
                "A person with a personal account can send a request using the "
                "group's code. The request appears under <b>Join Requests</b>, with "
                "a badge on the member list showing how many are waiting.",
                "Sending a request opens nothing. Until an official approves it, "
                "the person sees none of the group's figures.",
                "Approving is a deliberate act: check that the person asking is "
                "the person you think it is before you accept.",
            ]
        ),
        Paragraph("Member accounts", ST["h2"]),
        para(
            "An official can let members get their own sign-in so each person can "
            "see their own savings without asking to look at the group phone. What "
            "a member sees is their own record only — never another member's."
        ),
    ]

    f += [Paragraph("Group rules", ST["h1"])]
    f += [
        para("Two things the group sets for itself, and two that are fixed."),
        table(
            [
                ("How long a loan runs", "The loan term, in months."),
                ("Interest charged", "A monthly rate, set by the group."),
                ("Expenses are paid from", "Which fund covers the group's costs."),
            ],
            widths=[CONTENT_W * 0.30, CONTENT_W * 0.70],
        ),
        callout(
            "Rules that are fixed",
            "Unpaid fines and welfare are taken off a member's share-out payout "
            "— they never stop a member from sharing out. Outstanding loans "
            "are taken off at share-out and are never carried into the next cycle.",
            tone="warn",
        ),
    ]

    f += [Paragraph("Saving cycles and the share-out", ST["h1"])]
    f += [
        para(
            "A cycle is one saving period, usually a year. At the end the group "
            "shares out what it holds and starts again."
        ),
        *bullets(
            [
                "<b>Saving Cycles</b> opens and closes cycles and shows which one "
                "the group is currently in.",
                "<b>Share-Out</b> distributes the fund back to members: each "
                "member receives their savings plus their portion of what the group "
                "earned.",
                "Outstanding loans, unpaid fines and unpaid welfare are deducted "
                "from a member's payout at this point.",
                "What is left of the welfare fund is handed back separately, and "
                "can be split equally among members. It comes out of the social "
                "pot, not the loan fund — taking the right total from the "
                "wrong money is a mistake the app will not make.",
            ]
        ),
        Spacer(1, 3),
    ]

    f += [Paragraph("Reports", ST["h1"])]
    f += [
        table(
            [
                (
                    "Group Report",
                    "Money, members and meetings for the whole group. Shareable as "
                    "text or as a PDF.",
                ),
                (
                    "Member Reports",
                    "A statement for each member — what they have saved, "
                    "borrowed and repaid. Shareable as text or PDF.",
                ),
                (
                    "Credit rating",
                    "A score worked out from the group's own record: meetings held "
                    "and sealed, repayments made, savings kept up, leadership "
                    "filled, the 3-key unlock actually used. It is calculated, "
                    "never entered by hand, and the app lists what would raise it.",
                ),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
        ),
    ]

    f += [Paragraph("Intelli-Store and outside finance", ST["h1"])]
    f += [
        para(
            "Two features let a group deal with money and goods from outside "
            "itself. Both need the group to be connected to a Cloud Account, "
            "because both involve someone other than the group; until it is "
            "connected they appear locked, with a line saying what to do, rather "
            "than disappearing so that the feature looks missing."
        ),
        table(
            [
                (
                    "Intelli-Stores",
                    "Shop on credit. The group browses products offered through "
                    "the platform and buys against its own standing. The purchase "
                    "is recorded against the group, not hidden in a member's "
                    "personal dealings.",
                ),
                (
                    "External Loans",
                    "Credit ventures: money the group has borrowed from outside "
                    "— a bank, a programme, a federation. It is held in its "
                    "own fund, so members' savings and borrowed money are never "
                    "added together into one misleading total.",
                ),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
        ),
        callout(
            "Both live inside the meeting",
            "Intelli-Stores and External Loans are reached from an open meeting, "
            "not from the settings list. Taking on credit is a decision the group "
            "makes together, in the room, with the meeting record open — so "
            "that is where the app puts it.",
        ),
    ]

    f += [Paragraph("The office, syncing and a new phone", ST["h1"])]
    f += [
        para(
            "A group can run entirely on one phone. Connecting it to a Cloud "
            "Account adds a copy at the office, which is what makes reports, "
            "member sign-ins, the store and external loans possible — and "
            "what saves the group if the phone is lost."
        ),
        table(
            [
                (
                    "Cloud Account",
                    "Whether this phone is linked to the office, and which account "
                    "it is linked to.",
                ),
                (
                    "Back Up to Cloud",
                    "Links this phone's group to the group the office already "
                    "holds, and shows when records last went up. A group can also "
                    "be unlinked.",
                ),
                (
                    "Loading a group onto a new phone",
                    "If your group already exists on the system, sign in and load "
                    "it rather than setting it up again. Setting it up twice splits "
                    "one savings history into two records that never agree.",
                ),
            ],
            widths=[CONTENT_W * 0.26, CONTENT_W * 0.74],
        ),
        callout(
            "Sync after every meeting, while you still have signal",
            "Anything recorded on the phone but never sent is the only thing a "
            "lost or broken handset can take with it. Everything already at the "
            "office survives. One check of the sync mark on the dashboard before "
            "you leave the meeting place is the whole precaution.",
            tone="warn",
        ),
    ]

    f += [Paragraph("Meeting security", ST["h1"])]
    f += [
        para(
            "The screen that governs the three-key unlock. From it an official can "
            "see who currently holds a key and reset a member's PIN."
        ),
        *bullets(
            [
                "<b>Resetting a PIN does not reveal it.</b> A PIN cannot be looked "
                "up by anybody, including support. Resetting clears the old one so "
                "the member can choose 4 new digits the next time they turn a key.",
                "<b>Reset only in front of the member.</b> Whoever sets the next "
                "PIN holds that key, so a reset done quietly by one person is "
                "exactly the thing the three-key rule exists to prevent.",
            ]
        ),
    ]

    f += [Paragraph("The Account screen", ST["h1"])]
    f += [
        para(
            "One place for everything about the sign-in itself, rather than an "
            "unnamed icon in a corner:"
        ),
        table(
            [
                ("Who is signed in", "The account this phone is currently using."),
                ("Language", "The language for this phone."),
                ("Appearance", "Light or dark."),
                ("Server", "Which server the phone is talking to. It is shown, not editable — a group's records must never be pointed somewhere else by accident."),
                ("App version", "Useful when reporting a problem."),
                ("Sign out", "Clearly labelled. The group's savings, loans and meetings stay saved on the phone, but nobody can open them until someone signs in again. The phone number is remembered."),
            ],
            widths=[CONTENT_W * 0.24, CONTENT_W * 0.76],
        ),
    ]

    f += [Paragraph("Settings only an official touches", ST["h1"])]
    f += [
        table(
            [
                ("Group Settings", "Savings, loans and meeting days."),
                ("Meeting Security", "Who holds a key, and resetting a forgotten PIN."),
                ("Group Rules", "Loan term, interest and where expenses are paid from."),
                ("Welfare Fund", "The social pot: what it holds and what it has paid out."),
                ("Saving Cycles", "Opening and closing a cycle."),
                (
                    "Payment Providers",
                    "M-Pesa (Daraja) or Paystack, so money members pay comes into "
                    "the group's own account rather than the platform account. "
                    "Saved secrets are never shown back to you — you replace "
                    "them, you do not read them.",
                ),
                (
                    "Cloud Account &amp; Sync",
                    "Whether this phone is linked to the office, and when it last "
                    "sent its records.",
                ),
                ("Member Accounts", "Letting members get their own sign-in."),
                ("Language and appearance", "Language, and light or dark."),
            ],
            widths=[CONTENT_W * 0.26, CONTENT_W * 0.74],
        ),
        Spacer(1, 4),
    ]

    f += shots(
        [
            ("15-more.webp", "Everything set up once rather than every meeting: rules, cycles, reports, security and the account screen."),
        ],
        per_row=3,
    )

    # ── Part 3 ─────────────────────────────────────────────────────────────
    f += part(
        "Part 3",
        "For the member",
        "A personal account shows one person their own record — the same "
        "figures the group's book holds, not a separate copy that can drift out "
        "of step. If you save with more than one group, all of them appear under "
        "the one account.",
    )

    f += [Paragraph("What a member account shows", ST["h1"])]
    f += [
        table(
            [
                (
                    "My Passbook",
                    "The home screen. Shares bought, social fund, loans received, "
                    "repaid and still owing, then every transaction underneath, "
                    "newest first, with the date each belongs to.",
                ),
                (
                    "My Savings",
                    "Everything added up across every group you belong to, then "
                    "broken down group by group. A member in three groups sees one "
                    "figure, not three books.",
                ),
                (
                    "My Report",
                    "A dated statement you can share or download — useful when "
                    "a lender, a chief or a family member asks what you have saved.",
                ),
                (
                    "Join a group",
                    "Send a request using the code the group's officials give you.",
                ),
                (
                    "Language and appearance",
                    "Your own language and light or dark, set on your own phone.",
                ),
            ],
            widths=[CONTENT_W * 0.22, CONTENT_W * 0.78],
        ),
        Spacer(1, 4),
    ]

    f += shots(
        [
            ("21-member-passbook.webp", "My Passbook: what you hold, what you owe, and every transaction underneath."),
            ("20-member-home.webp", "My Savings adds up every group you belong to, then breaks the same total down group by group."),
            ("23-member-report.webp", "My Report is a dated statement you can share or download."),
        ]
    )

    f += [Paragraph("Joining a group", ST["h1"])]
    f += [
        *steps(
            [
                "Ask the group's secretary for the group code — it is on the "
                "group's records.",
                "Open <b>Join a group</b>, enter the code, and tap <b>Send request</b>.",
                "An official approves you. Only then do you appear in the group's "
                "book, and only then do you see its figures.",
            ]
        ),
        callout(
            "Sending a request does not open the group's books to you",
            "The code alone gives you nothing. An official has to accept you first, "
            "and they should check that the person asking is who they think it is "
            "before doing so.",
        ),
        Spacer(1, 2),
    ]

    f += shots(
        [
            ("22-member-join.webp", "Join a group with the code its officials give you."),
        ],
        per_row=3,
    )

    f += [Paragraph("Your account screen", ST["h1"])]
    f += [
        para(
            "Tap <b>Account</b> to see who is signed in, change the language, "
            "switch between light and dark, and check which server the phone is "
            "talking to. Sign out lives there too, clearly labelled."
        ),
        para(
            "Signing out does not delete anything. You will need to sign in again "
            "to see your savings, and your phone number is remembered so you only "
            "have to enter your password."
        ),
    ]

    f += [Paragraph("What a member can and cannot do", ST["h1"])]
    f += [
        table(
            [
                ("See your own record", "Yes — savings, shares, loans and every transaction, in every group you belong to."),
                ("Vote", "Yes. One member, one vote, on resolutions the group puts to a vote."),
                ("Turn a key to open a meeting", "Yes — five members can open a meeting between them if three officials are not present."),
                ("Share your own statement", "Yes. My Report can be shared or downloaded."),
                ("See another member's record", "No. A personal account shows your record only."),
                ("Change any figure", "No. A member account is read-only; only the group's record book writes."),
                ("Join a group without approval", "No. An official must accept the request."),
            ],
            widths=[CONTENT_W * 0.32, CONTENT_W * 0.68],
            head=["", "Can a member do this?"],
        ),
    ]

    # ── Part 4 ─────────────────────────────────────────────────────────────
    f += part(
        "Part 4",
        "Keeping the book safe",
        "Passwords, PINs, and who is able to see what. These are the parts people "
        "most often get wrong, and the parts that matter most if a phone is lost "
        "or a member leaves under a cloud.",
    )

    f += [Paragraph("Passwords and PINs", ST["h1"])]
    f += [
        table(
            [
                (
                    "Your password",
                    "Signs you in to your account. At least 6 characters, and never "
                    "shown back to anyone — not even to support staff.",
                ),
                (
                    "Your meeting PIN",
                    "4 digits, chosen by you, used to turn your key when a meeting "
                    "opens and again when it closes. Obvious values like 1234 or "
                    "four of the same digit are refused.",
                ),
                (
                    "If a PIN is forgotten",
                    "It cannot be looked up — it is stored in a form nobody "
                    "can read back. An official sets a new one, and the member "
                    "chooses 4 digits of their own.",
                ),
                (
                    "If a phone is lost",
                    "Sign in on a new phone and load the group again. The record "
                    "the office holds is intact. Anything recorded on the lost "
                    "phone but never synced is the only thing at risk, which is why "
                    "syncing after a meeting matters.",
                ),
            ],
            widths=[CONTENT_W * 0.26, CONTENT_W * 0.74],
        ),
    ]

    f += [Paragraph("Who sees what", ST["h1"])]
    f += [
        table(
            [
                ("A member", "Their own record, in every group they belong to. Nothing else."),
                ("A group account", "Its own group's whole book — members, money, meetings."),
                ("A field agent", "The groups on their caseload: standing, ratings and visits. Never able to move a group's money."),
                ("A platform administrator", "Cross-programme data, for support and reporting."),
                ("Everyone else", "A phone number masked to its last three digits, and nothing more."),
            ],
            widths=[CONTENT_W * 0.28, CONTENT_W * 0.72],
        ),
        callout(
            "Your rights over your own data",
            "Under the Kenya Data Protection Act, 2019 you can ask what personal "
            "data is held about you and receive a copy, ask for it to be corrected, "
            "ask for deletion of data no longer needed, withdraw consent for a "
            "request you submitted through a public form, and complain to the "
            "Office of the Data Protection Commissioner. The full notice is at "
            "intellicash.co.ke/privacy.",
        ),
    ]

    # ── Part 5 ─────────────────────────────────────────────────────────────
    f += part(
        "Part 5",
        "Words, and what to do when something is wrong",
        "The terms that appear on screen, and the handful of problems that come "
        "up often enough to be worth writing down.",
    )

    f += [Paragraph("Terms you will see", ST["h1"])]
    f += [
        table(
            [
                ("Share", "One unit of savings. The group sets what a share is worth and how many a member may buy at a meeting."),
                ("Social fund", "A small separate pot for emergencies, kept apart from savings and paid out by agreement rather than lent."),
                ("Welfare payment", "Money paid out of the social fund to a member in need."),
                ("Cycle", "One saving period, usually a year."),
                ("Share-out", "Closing a cycle: every member receives their savings plus their portion of what the group earned."),
                ("Fine", "A penalty the group agreed. Deducted at share-out; never a bar to sharing out."),
                ("Internal loan", "Money lent from the group's own fund to one of its members."),
                ("External loan", "Money the group has borrowed from outside, kept separate from members' savings."),
                ("Sealed meeting", "A meeting that has been closed and locked. Part of the permanent record."),
                ("Credit rating", "A score derived from the group's own record. Calculated, never entered by hand."),
                (
                    "Assessment band",
                    "A different thing from the credit rating, and the two can "
                    "disagree. This one comes from the scorecard a field agent "
                    "fills in with the officials: Weak below 40%, Fair from 40%, "
                    "Good from 60%, Excellent from 80%. The rating is derived from "
                    "the ledger; the band is what a person saw on the day.",
                ),
            ],
            widths=[CONTENT_W * 0.22, CONTENT_W * 0.78],
        ),
    ]

    f += [Paragraph("If something is wrong", ST["h1"])]
    f += [
        table(
            [
                (
                    "The app says there is no connection",
                    "Keep working. Meetings, savings and loans are recorded on the "
                    "phone and sent when there is internet. Nothing is lost.",
                ),
                (
                    "A member forgot their PIN",
                    "It cannot be looked up. An official sets a new one for them, "
                    "and the member chooses 4 digits of their own.",
                ),
                (
                    "You were signed out unexpectedly",
                    "Sessions last 8 hours. Sign in again; the records already on "
                    "the phone are untouched.",
                ),
                (
                    "A meeting will not open",
                    "Three officials must each turn their key, or five members "
                    "(all of them, in a group with fewer than five). Check that the "
                    "people present are recorded as active members.",
                ),
                (
                    "A screen will not load",
                    "It will say so and offer <b>Try again</b> rather than showing "
                    "an empty page. If it keeps failing, check the sync mark on the "
                    "dashboard.",
                ),
                (
                    "The figures look wrong",
                    "Nothing financial is ever overwritten, so the history is "
                    "always there to check. Open the member's report and read the "
                    "transactions in order; a correction appears as its own entry.",
                ),
            ],
            widths=[CONTENT_W * 0.30, CONTENT_W * 0.70],
        ),
        Spacer(1, 6),
        Rule(LINE, 0.6, space_after=8),
        para(
            "<b>Getting help.</b> The public guide, with the same screens as this "
            "manual, is at <b>intellicash.co.ke/docs</b>. For anything this manual "
            "does not answer, contact your field agent or the office.",
            "note",
        ),
    ]

    return f


def main():
    if not SHOTS.exists():
        print(f"screenshot folder not found: {SHOTS}", file=sys.stderr)
        return 1

    doc = Manual(str(OUTPUT))
    # multiBuild: the table of contents needs a second pass to learn the page
    # numbers the first pass produced.
    doc.multiBuild(story())
    size_kb = OUTPUT.stat().st_size // 1024
    print(f"wrote {OUTPUT} ({size_kb} KB, {doc.page} pages)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
