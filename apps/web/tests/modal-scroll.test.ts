import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

/**
 * Every dialog in the console must be able to scroll to its Save button.
 *
 * This is a cascade-order test, which is unusual, but it is the only kind that
 * would have caught the bug it guards. Every dialog is
 * `class="data-card credential-modal"`, and `.app-shell .data-card` clips —
 * correctly, for cards in a page. That rule outranks `.credential-modal` on
 * specificity, so for months every dialog taller than the viewport was cut off
 * at the fold and Save could not be reached at all. Creating a group was
 * impossible. jsdom applies no CSS, so no rendering test can see this; reading
 * the source order is what is left.
 */
const css = readFileSync(join(__dirname, "..", "src", "app", "globals.css"), "utf8");

function indexOfRule(selector: string, declaration: string) {
  // Rules are matched as `selector { ... declaration ... }` with the selector
  // anchored to a line start, so a longer selector that merely ends with the
  // same text cannot satisfy the search.
  const pattern = new RegExp(
    `^${selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\s*\\{[^}]*${declaration.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`,
    "m"
  );
  const match = pattern.exec(css);
  return match ? match.index : -1;
}

describe("dialogs can scroll to their Save button", () => {
  it("re-asserts overflow on dialogs after the rule that clips cards", () => {
    const cardClips = indexOfRule(".app-shell .data-card", "overflow: hidden");
    const dialogScrolls = indexOfRule(".app-shell .credential-modal", "overflow: auto");

    expect(cardClips).toBeGreaterThan(-1);
    expect(dialogScrolls).toBeGreaterThan(-1);
    // Equal specificity, so source order decides. If a later edit moves the
    // card rule below this one, every dialog silently loses its scrollbar.
    expect(dialogScrolls).toBeGreaterThan(cardClips);
  });

  it("keeps a height cap on dialogs, or overflow has nothing to act on", () => {
    // `overflow: auto` on an element with no maximum height never scrolls — it
    // just grows past the bottom of the screen, which is the same bug wearing
    // a different hat.
    expect(css).toMatch(/\.credential-modal\s*\{[^}]*max-height:[^}]*dvh/);
  });

  it("still lets the three dialogs that scroll a child keep clipping", () => {
    // These lay out as header / scrolling body / action bar. If the outer
    // element scrolled too, the header would scroll away and there would be two
    // scrollbars.
    const exception = css.indexOf(".app-shell .group-editor-modal,");
    const dialogScrolls = indexOfRule(".app-shell .credential-modal", "overflow: auto");

    expect(exception).toBeGreaterThan(dialogScrolls);
    for (const selector of [
      ".app-shell .group-editor-modal",
      ".app-shell .meeting-entry-modal",
      ".app-shell .meeting-detail-modal"
    ]) {
      expect(css.slice(exception)).toContain(selector);
    }
  });
});
