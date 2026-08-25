"""
Builds the Intelli-Cash field agent guide as a PDF.

A separate document from the user manual on purpose. A Village Agent or CBT
does a different job from a group secretary: they carry a caseload, they visit,
they score, they coach, and — critically — they collect other people's personal
data on someone else's behalf. That last part is a duty, not a feature, and it
needs saying in a document an agent actually carries.

Screenshots come from `docs/manual/va-shots/`, captured off a real handset
signed in as a Village Agent.

    python docs/manual/build_field_agent_guide.py

Requires reportlab and Pillow.
"""

from __future__ import annotations

import io
import sys
from datetime import date
from pathlib import Path

from PIL import Image
from reportlab.lib import colors
from reportlab.lib.enums import TA_JUSTIFY
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

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
SHOTS = HERE / "va-shots"
LOGO = REPO / "apps" / "web" / "public" / "brand" / "intelli-cash-logo.png"
OUTPUT = HERE / "Intelli-Cash-Field-Agent-Guide.pdf"

# Brand, from apps/web/src/app/globals.css
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
RED = colors.HexColor("#b43b3b")
BLUE = colors.HexColor("#2c5f8a")

PAGE_W, PAGE_H = A4
MARGIN = 18 * mm
CONTENT_W = PAGE_W - 2 * MARGIN

VERSION = "App version 2.4.2"
BUILT = date.today().strftime("%d %B %Y")


def build_styles():
    base = getSampleStyleSheet()
    s = {}
    s["body"] = ParagraphStyle(
        "body", parent=base["BodyText"], fontName="Helvetica", fontSize=9.4,
        leading=14.2, textColor=INK_SOFT, alignment=TA_JUSTIFY, spaceAfter=7)
    s["lead"] = ParagraphStyle(
        "lead", parent=s["body"], fontSize=10.4, leading=15.6, textColor=INK,
        spaceAfter=9)
    s["part"] = ParagraphStyle(
        "part", parent=base["Heading1"], fontName="Helvetica-Bold", fontSize=24,
        leading=28, textColor=GREEN_DEEP, spaceBefore=0, spaceAfter=4)
    s["kicker"] = ParagraphStyle(
        "kicker", parent=s["body"], fontName="Helvetica-Bold", fontSize=8.4,
        leading=11, textColor=GREEN, alignment=0, spaceAfter=3)
    s["h1"] = ParagraphStyle(
        "h1", parent=base["Heading1"], fontName="Helvetica-Bold", fontSize=15,
        leading=19, textColor=GREEN_DEEP, spaceBefore=13, spaceAfter=5)
    s["h1_plain"] = ParagraphStyle("h1_plain", parent=s["h1"])
    s["h2"] = ParagraphStyle(
        "h2", parent=base["Heading2"], fontName="Helvetica-Bold", fontSize=11,
        leading=14.5, textColor=INK, spaceBefore=10, spaceAfter=4)
    s["bullet"] = ParagraphStyle(
        "bullet", parent=s["body"], leftIndent=11, bulletIndent=2,
        spaceAfter=3.5, alignment=0)
    s["step"] = ParagraphStyle("step", parent=s["bullet"], leftIndent=17, bulletIndent=2)
    s["caption"] = ParagraphStyle(
        "caption", parent=s["body"], fontSize=8, leading=11, textColor=MUTED,
        alignment=0, spaceBefore=4, spaceAfter=0)
    s["cell"] = ParagraphStyle(
        "cell", parent=s["body"], fontSize=8.5, leading=11.8, spaceAfter=0, alignment=0)
    s["cell_head"] = ParagraphStyle(
        "cell_head", parent=s["cell"], fontName="Helvetica-Bold", textColor=colors.white)
    s["cell_key"] = ParagraphStyle(
        "cell_key", parent=s["cell"], fontName="Helvetica-Bold", textColor=INK)
    s["note"] = ParagraphStyle(
        "note", parent=s["body"], fontSize=8.7, leading=12.6, textColor=INK_SOFT,
        alignment=0, spaceAfter=0)
    s["toc1"] = ParagraphStyle(
        "toc1", parent=s["body"], fontName="Helvetica-Bold", fontSize=10.4,
        leading=15, textColor=GREEN_DEEP, spaceBefore=9, spaceAfter=1, alignment=0)
    s["toc2"] = ParagraphStyle(
        "toc2", parent=s["body"], fontSize=9.2, leading=12.5, leftIndent=12,
        textColor=INK_SOFT, spaceAfter=1, alignment=0)
    s["cover_title"] = ParagraphStyle(
        "cover_title", parent=base["Title"], fontName="Helvetica-Bold", fontSize=32,
        leading=37, textColor=colors.white, alignment=0)
    s["cover_sub"] = ParagraphStyle(
        "cover_sub", parent=s["body"], fontSize=12, leading=17.5,
        textColor=colors.HexColor("#c9f2da"), alignment=0)
    s["cover_meta"] = ParagraphStyle(
        "cover_meta", parent=s["body"], fontSize=8.8, leading=13.5,
        textColor=colors.HexColor("#9fd9b6"), alignment=0)
    s["band_label"] = ParagraphStyle(
        "band_label", parent=s["cell"], fontName="Helvetica-Bold",
        textColor=colors.white, alignment=1)
    return s


ST = build_styles()


class Rule(Flowable):
    def __init__(self, colour=LINE, thickness=0.6, space_before=2, space_after=6):
        Flowable.__init__(self)
        self.colour, self.thickness = colour, thickness
        self.space_before, self.space_after = space_before, space_after
        self.width = 0

    def wrap(self, avail_w, avail_h):
        self.width = avail_w
        return avail_w, self.thickness + self.space_before + self.space_after

    def draw(self):
        self.canv.setStrokeColor(self.colour)
        self.canv.setLineWidth(self.thickness)
        self.canv.line(0, self.space_after, self.width, self.space_after)


class BandStrip(Flowable):
    """
    The four assessment bands drawn to scale across the page.

    A table of percentages tells an agent the thresholds; a strip shows them
    that Weak is nearly half the range and Excellent is a fifth of it, which is
    the thing that actually changes how a score feels when you read it out to a
    group.
    """

    HEIGHT = 17 * mm

    BANDS = [
        ("Weak", 0, 40, colors.HexColor("#b43b3b")),
        ("Fair", 40, 60, colors.HexColor("#f2a81d")),
        ("Good", 60, 80, colors.HexColor("#5aa469")),
        ("Excellent", 80, 100, GREEN),
    ]

    def wrap(self, avail_w, avail_h):
        self.width = avail_w
        return avail_w, self.HEIGHT + 10

    def draw(self):
        c = self.canv
        bar_h = 9 * mm
        y = 10
        for label, lo, hi, colour in self.BANDS:
            x = self.width * lo / 100
            w = self.width * (hi - lo) / 100
            c.setFillColor(colour)
            c.rect(x, y, w, bar_h, stroke=0, fill=1)
            c.setFillColor(colors.white)
            c.setFont("Helvetica-Bold", 8)
            c.drawCentredString(x + w / 2, y + bar_h / 2 - 3, label)
            # The threshold sits under the left edge of its band. The first one
            # is left-aligned rather than centred, or half of "0%" falls off
            # the page.
            c.setFillColor(MUTED)
            c.setFont("Helvetica", 7.2)
            if lo == 0:
                c.drawString(x, y - 8, f"{lo}%")
            else:
                c.drawCentredString(x, y - 8, f"{lo}%")
        c.setFillColor(MUTED)
        c.setFont("Helvetica", 7.2)
        c.drawRightString(self.width, y - 8, "100%")


class VisitFlow(Flowable):
    """The seven steps of a visit as a chevron strip."""

    STEPS = ["Open", "Where", "Last time", "Score", "Coach", "Business", "Finish"]
    HEIGHT = 15 * mm

    def wrap(self, avail_w, avail_h):
        self.width = avail_w
        return avail_w, self.HEIGHT + 6

    def draw(self):
        c = self.canv
        n = len(self.STEPS)
        gap = 2.0 * mm
        w = (self.width - gap * (n - 1)) / n
        h = 10 * mm
        notch = 2.6 * mm
        y = 4
        for i, label in enumerate(self.STEPS):
            x = i * (w + gap)
            # Chevron: a rectangle with a point on the right and a notch left.
            p = c.beginPath()
            p.moveTo(x, y)
            p.lineTo(x + w - notch, y)
            p.lineTo(x + w, y + h / 2)
            p.lineTo(x + w - notch, y + h)
            p.lineTo(x, y + h)
            if i:
                p.lineTo(x + notch, y + h / 2)
            p.close()
            c.setFillColor(GREEN_DEEP if i == 0 else GREEN)
            c.setStrokeColor(colors.white)
            c.setLineWidth(0.8)
            c.drawPath(p, stroke=1, fill=1)
            c.setFillColor(colors.white)
            c.setFont("Helvetica-Bold", 7.2)
            c.drawCentredString(x + w / 2 + notch / 2, y + h / 2 - 2.6, label)
            c.setFillColor(MUTED)
            c.setFont("Helvetica", 6.6)
            c.drawCentredString(x + w / 2, y - 6, str(i + 1))


def para(text, style="body"):
    return Paragraph(text, ST[style])


def bullets(items, marker="•"):
    return [Paragraph(t, ST["bullet"], bulletText=marker) for t in items]


def steps(items):
    return [Paragraph(t, ST["step"], bulletText=f"{i}.") for i, t in enumerate(items, 1)]


def table(rows, widths=None, head=None):
    data, style = [], [
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
        style += [("BACKGROUND", (0, 0), (-1, 0), GREEN),
                  ("LINEBELOW", (0, 0), (-1, 0), 0.4, GREEN)]
        offset = 1
    for i, row in enumerate(rows):
        data.append([Paragraph(row[0], ST["cell_key"])] +
                    [Paragraph(c, ST["cell"]) for c in row[1:]])
        if i % 2:
            style.append(("BACKGROUND", (0, i + offset), (-1, i + offset), SURFACE))
    if widths is None:
        n = len(rows[0])
        widths = [CONTENT_W * 0.28] + [CONTENT_W * 0.72 / (n - 1)] * (n - 1)
    t = Table(data, colWidths=widths, hAlign="LEFT", repeatRows=1 if head else 0)
    t.setStyle(TableStyle(style))
    return t


def callout(title, text, tone="info"):
    fill, edge = TINT, GREEN
    if tone == "warn":
        fill, edge = colors.HexColor("#fff8e6"), GOLD
    elif tone == "stop":
        fill, edge = colors.HexColor("#fdf0f0"), RED

    inner = [Paragraph(f"<b>{title}</b>", ST["note"]), Spacer(1, 2.5),
             Paragraph(text, ST["note"])]
    t = Table([[inner]], colWidths=[CONTENT_W], hAlign="LEFT")
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), fill),
        ("LINEBEFORE", (0, 0), (0, -1), 2.6, edge),
        ("BOX", (0, 0), (-1, -1), 0.4, edge),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    return KeepTogether([Spacer(1, 3), t, Spacer(1, 7)])


def shots(entries, per_row=3, height=90 * mm):
    cells, captions = [], []
    col_w = (CONTENT_W - (per_row - 1) * 6 * mm) / per_row
    for name, caption in entries:
        path = SHOTS / name
        if not path.exists():
            print(f"  ! missing {name}", file=sys.stderr)
            continue
        iw, ih = ImageReader(str(path)).getSize()
        w, h = height * iw / ih, height
        if w > col_w:
            w, h = col_w, col_w * ih / iw
        img = RLImage(str(path), width=w, height=h)
        img.hAlign = "CENTER"
        cells.append(img)
        captions.append(Paragraph(caption, ST["caption"]))
    if not cells:
        return []
    out = []
    for i in range(0, len(cells), per_row):
        row, caps = cells[i:i + per_row], captions[i:i + per_row]
        pad = per_row - len(row)
        row, caps = row + [""] * pad, caps + [""] * pad
        t = Table([row, caps], colWidths=[col_w] * per_row, hAlign="LEFT")
        t.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, 0), "BOTTOM"),
            ("VALIGN", (0, 1), (-1, 1), "TOP"),
            ("ALIGN", (0, 0), (-1, 0), "CENTER"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
            ("TOPPADDING", (0, 0), (-1, 0), 4),
            ("BOTTOMPADDING", (0, 1), (-1, 1), 10),
        ]))
        out.append(CondPageBreak(height + 22 * mm))
        out.append(KeepTogether(t))
    return out


class Guide(BaseDocTemplate):
    def __init__(self, path):
        BaseDocTemplate.__init__(
            self, path, pagesize=A4, leftMargin=MARGIN, rightMargin=MARGIN,
            topMargin=MARGIN + 8 * mm, bottomMargin=MARGIN,
            title="Intelli-Cash Field Agent Guide", author="PhineTech Ltd",
            subject="Running a field visit with Intelli-Cash")
        frame = Frame(MARGIN, MARGIN, CONTENT_W,
                      PAGE_H - MARGIN - (MARGIN + 8 * mm), id="body",
                      leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
        self.addPageTemplates([
            PageTemplate(id="cover", frames=[frame], onPage=self.cover_page),
            PageTemplate(id="body", frames=[frame], onPage=self.body_page),
        ])
        self._part_pages: dict[int, str] = {}
        self._pending: dict[int, str] = {}

    def beforeDocument(self):
        self._part_pages = dict(self._pending)
        self._pending = {}

    def part_for_page(self, page: int) -> str:
        seen = [p for p in self._part_pages if p <= page]
        return self._part_pages[max(seen)] if seen else ""

    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and flowable.style.name == "part":
            title = flowable.getPlainText()
            self._pending[self.page] = title
            self.notify("TOCEntry", (0, title, self.page - 1))
        elif isinstance(flowable, Paragraph) and flowable.style.name == "h1":
            self.notify("TOCEntry", (1, flowable.getPlainText(), self.page - 1))

    def cover_page(self, canv, doc):
        canv.saveState()
        canv.setFillColor(GREEN_DEEP)
        canv.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canv.setFillColor(colors.white)
        canv.roundRect(MARGIN, PAGE_H - 60 * mm, 76 * mm, 33 * mm, 4 * mm, stroke=0, fill=1)
        # Low enough to clear the imprint lines. At 70mm it struck through
        # "PhineTech Ltd", which is the one thing on a cover that must not be
        # crossed out.
        canv.setFillColor(GREEN)
        canv.rect(0, 46 * mm, PAGE_W, 5 * mm, stroke=0, fill=1)
        canv.setFillColor(GREEN_BRIGHT)
        canv.rect(0, 46 * mm, 56 * mm, 5 * mm, stroke=0, fill=1)
        canv.restoreState()

    def body_page(self, canv, doc):
        canv.saveState()
        canv.setFont("Helvetica", 7.4)
        canv.setFillColor(MUTED)
        canv.drawString(MARGIN, PAGE_H - MARGIN - 3 * mm, "Intelli-Cash Field Agent Guide")
        part = self.part_for_page(doc.page)
        if part:
            canv.drawRightString(PAGE_W - MARGIN, PAGE_H - MARGIN - 3 * mm, part)
        canv.setStrokeColor(LINE)
        canv.setLineWidth(0.5)
        canv.line(MARGIN, PAGE_H - MARGIN - 5 * mm, PAGE_W - MARGIN, PAGE_H - MARGIN - 5 * mm)
        canv.line(MARGIN, MARGIN - 4 * mm, PAGE_W - MARGIN, MARGIN - 4 * mm)
        canv.drawString(MARGIN, MARGIN - 9 * mm, "intellicash.co.ke")
        canv.drawRightString(PAGE_W - MARGIN, MARGIN - 9 * mm, str(doc.page - 1))
        canv.restoreState()


def part(kicker, title, blurb):
    return [
        PageBreak(), Spacer(1, 12 * mm),
        para(kicker.upper(), "kicker"),
        Paragraph(title, ST["part"]),
        Rule(GREEN, 2.0, space_before=4, space_after=10),
        para(blurb, "lead"), Spacer(1, 2 * mm),
    ]


def main() -> int:
    if not SHOTS.exists():
        print(f"screenshot folder not found: {SHOTS}", file=sys.stderr)
        return 1

    # Imported here, not at module scope: the content module imports the
    # styles and helpers from this one, so a top-level import would be circular.
    sys.path.insert(0, str(HERE))
    from field_agent_content import story

    doc = Guide(str(OUTPUT))
    doc.multiBuild(story())
    print(f"wrote {OUTPUT} ({OUTPUT.stat().st_size // 1024} KB, {doc.page} pages)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
