import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

import { getNavigationItemsForRole, navigationItems, navigationSections } from "@/lib/navigation";

/**
 * Every link in the console sidebar has to open a real page.
 *
 * Six links (Brands, Content Studio, Catalogue, Campaigns, Channels, Team) were
 * once pasted in from a different product. None had a page, so every admin saw
 * six 404s at the top of the menu, and the menu tests had been edited to expect
 * them. Checking the file system is what catches that: a label list only ever
 * agrees with whoever edited it last.
 */
const APP_DIR = path.resolve(__dirname, "../src/app");

function pageFileFor(href: string) {
  const route = href.split(/[?#]/)[0]!.replace(/^\/+|\/+$/g, "");
  return ["page.tsx", "page.ts", "page.jsx", "page.js"]
    .map((name) => path.join(APP_DIR, route, name))
    .find((file) => fs.existsSync(file));
}

describe("console navigation", () => {
  it.each(navigationItems.map((item) => [item.label, item.href] as const))(
    "%s (%s) opens a page that exists",
    (_label, href) => {
      expect(pageFileFor(href), `no page file under src/app for ${href}`).toBeTruthy();
    }
  );

  it("puts every item in a section the sidebar renders", () => {
    const sections = new Set<string>(navigationSections.map((section) => section.key));
    for (const item of navigationItems) {
      expect(sections.has(item.section), `${item.label} is in unknown section ${item.section}`).toBe(true);
    }
  });

  it("carries none of the marketing-agency workspaces", () => {
    const labels = navigationItems.map((item) => item.label.toLowerCase());
    for (const foreign of ["brands", "content studio", "campaigns", "channels"]) {
      expect(labels).not.toContain(foreign);
    }
    const sectionKeys = navigationSections.map((section) => section.key as string);
    expect(sectionKeys).not.toContain("marketing");
    expect(sectionKeys).not.toContain("content");
  });

  describe("switchable modules", () => {
    const hasStore = (role: string, modules?: { store?: boolean; voting?: boolean }) =>
      getNavigationItemsForRole(role, modules).some((item) => item.href === "/dashboard/intelli-store");

    it("drops Intelli-Store when it is off for every programme in scope", () => {
      expect(hasStore("GROUP_ACCOUNT", { store: false, voting: false })).toBe(false);
      expect(hasStore("MEMBER", { store: false, voting: true })).toBe(false);
    });

    it("shows Intelli-Store once a programme in scope has it on", () => {
      expect(hasStore("GROUP_ACCOUNT", { store: true, voting: false })).toBe(true);
    });

    it("does not hide anything before the account's modules are known", () => {
      expect(hasStore("GROUP_ACCOUNT")).toBe(true);
    });
  });
});
