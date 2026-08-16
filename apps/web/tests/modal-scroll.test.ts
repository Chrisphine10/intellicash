import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

/**
 * Every dialog in the console must be able to scroll to its Save button.
 *
 * The history is worth keeping, because it is why these assertions look the way
 * they do. Every dialog is `class="data-card credential-modal"`, and
 * `.app-shell .data-card` clips at higher specificity than `.credential-modal`.
 * A dialog that capped its own height and relied on winning `overflow: auto`
 * therefore lost, was cut off at the fold, and could not be submitted at all.
 * Out-specifying the card rule fixed it but left the whole thing one stylesheet
 * edit from breaking again — so the mechanism changed instead: an ordinary
 * dialog has NO height cap, and the overlay scrolls it. Nothing can clip a box
 * that is exactly as tall as its content.
 *
 * These read source text rather than rendering, which is unusual, but jsdom
 * applies no CSS and this class of bug is invisible to every other kind of test
 * available here.
 */
/*
 * Comments are stripped first. These rules carry long explanations that name
 * the very declarations being asserted against — without this, a test looking
 * for the absence of `align-items: center` finds it in the comment explaining
 * why it must not be used, and fails on prose.
 */
const css = readFileSync(join(__dirname, "..", "src", "app", "globals.css"), "utf8").replace(
  /\/\*[\s\S]*?\*\//g,
  ""
);

/** The body of the first rule whose selector matches exactly. */
function ruleBody(selector: string) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = new RegExp(`^${escaped}\\s*\\{([^}]*)\\}`, "m").exec(css);
  return match?.[1] ?? null;
}

describe("dialogs can scroll to their Save button", () => {
  it("gives an ordinary dialog no height cap, so nothing can clip it", () => {
    const body = ruleBody(".credential-modal");
    expect(body).not.toBeNull();
    // A cap is what made `overflow` matter. Without one the dialog is as tall
    // as its content and `overflow: hidden` — which still wins here — hides
    // nothing.
    expect(body).not.toMatch(/max-height/);
  });

  it("makes the overlay the scroll container", () => {
    const body = ruleBody(".modal-overlay");
    expect(body).not.toBeNull();
    expect(body).toMatch(/overflow-y:\s*auto/);
    expect(body).toMatch(/display:\s*flex/);
  });

  it("centres with auto margins, never with an alignment keyword", () => {
    // Centring an over-tall child with `align-items: center` pushes its top
    // above the scrollable area, so the heading and first fields cannot be
    // reached. `safe` centring avoids that but is silently dropped by a browser
    // that does not know the keyword — a failure indistinguishable from the
    // original bug. Auto margins have neither problem.
    expect(ruleBody(".credential-modal")).toMatch(/margin:\s*auto/);
    const overlay = ruleBody(".modal-overlay") ?? "";
    expect(overlay).not.toMatch(/(align|place)-items:\s*(safe\s+)?center/);
  });

  it("keeps a cap on the three dialogs that scroll their own middle", () => {
    // These lay out as header / scrolling body / action bar, so they do need a
    // fixed frame. Each must declare its own cap rather than inheriting one
    // from `.credential-modal`, which no longer caps anything.
    for (const selector of [
      ".app-shell .group-editor-modal",
      ".meeting-entry-modal",
      ".meeting-detail-modal"
    ]) {
      expect(ruleBody(selector), `${selector} needs its own max-height`).toMatch(/max-height/);
    }
  });

  it("does not re-introduce a blanket cap in the mobile breakpoint", () => {
    // A cap applied to `.credential-modal` inside a media query would restore
    // the original bug on phones only, which is where it hurts most.
    expect(css).not.toMatch(/^\s{2}\.credential-modal\s*\{[^}]*max-height/m);
  });
});
