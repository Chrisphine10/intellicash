import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

/**
 * The console sidebar is one viewport tall and holds 27 navigation items.
 *
 * It was `height: 100vh` with no `overflow`, so on a laptop the last two
 * sections — Setup and Resources — were below the fold with no scrollbar and
 * no way to reach them. The features were not hidden or permission-gated; they
 * were painted off the bottom of a box that refused to scroll.
 *
 * jsdom does not lay anything out, so this asserts the CSS contract that makes
 * scrolling possible rather than the scroll itself. Every one of these
 * declarations is load-bearing, and the failure mode when one goes missing is
 * silent.
 */
const CSS = fs.readFileSync(
  path.resolve(__dirname, "../src/app/globals.css"),
  "utf8"
);

/** The declarations of the first rule whose selector matches exactly. */
function ruleBody(selector: string): string {
  const pattern = new RegExp(
    `(^|\\n)\\s*${selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\s*\\{([^}]*)\\}`
  );
  const match = CSS.match(pattern);
  expect(match?.[2], `no rule found for ${selector}`).toBeTruthy();
  return match![2]!;
}

describe("the console sidebar can reach every navigation item", () => {
  it("is a column that does not itself scroll", () => {
    const sidebar = ruleBody(".sidebar");

    expect(sidebar).toMatch(/display:\s*flex/);
    expect(sidebar).toMatch(/flex-direction:\s*column/);
    // The aside clips; the list inside it is what moves. Scrolling the whole
    // aside would take the brand with it.
    expect(sidebar).toMatch(/overflow:\s*hidden/);
    // dvh, because mobile browser chrome makes 100vh taller than the screen.
    expect(sidebar).toMatch(/height:\s*100dvh/);
  });

  it("lets the navigation list scroll", () => {
    const nav = ruleBody(".nav-list");

    expect(nav).toMatch(/overflow-y:\s*auto/);
    expect(nav).toMatch(/flex:\s*1 1 auto/);
    // The one that is easy to lose and impossible to notice: a flex child
    // defaults to min-height:auto and refuses to shrink below its content, so
    // overflow-y never engages and the sidebar silently grows again.
    expect(nav).toMatch(/min-height:\s*0/);
    // A wheel over the nav must not scroll the page behind it once it ends.
    expect(nav).toMatch(/overscroll-behavior:\s*contain/);
  });

  it("keeps the brand fixed while the list moves", () => {
    expect(ruleBody(".sidebar-header")).toMatch(/flex:\s*0 0 auto/);
  });

  it("still scrolls in the mobile drawer", () => {
    // The drawer was always right — it slides in as a fixed panel and has
    // always had its own overflow. This guards against a "tidy-up" that
    // unifies the two rules and takes the working one down with it.
    //
    // Identified by `position: fixed`, not by being inside a media query:
    // the file has many `.sidebar` rules and only one of them is the drawer.
    const drawer = [...CSS.matchAll(/\.sidebar\s*\{([^}]*)\}/g)]
      .map((match) => match[1] ?? "")
      .find((body) => /position:\s*fixed/.test(body));

    expect(drawer, "no fixed-position .sidebar rule found").toBeTruthy();
    expect(drawer ?? "").toMatch(/overflow-y:\s*auto/);
  });
});
