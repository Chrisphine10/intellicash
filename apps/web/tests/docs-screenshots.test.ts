import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const WEB_ROOT = path.resolve(__dirname, "..");
const GUIDE = path.join(WEB_ROOT, "src/app/docs/page.tsx");
const SHOT_DIR = path.join(WEB_ROOT, "public/docs");

function referencedShots(): string[] {
  const source = fs.readFileSync(GUIDE, "utf8");
  return [...source.matchAll(/src="(\/docs\/[^"]+)"/g)].map((match) => match[1]);
}

/**
 * The guide's screenshots are captured by hand off a running phone, renamed,
 * and re-encoded. Every one of those steps can leave a `src` pointing at a
 * file that is no longer there, and nothing at build time notices: the page
 * still renders, the frame is just empty. This is the check that notices.
 */
describe("guide screenshots", () => {
  const shots = referencedShots();

  it("references at least one screenshot per role", () => {
    expect(shots.length).toBeGreaterThan(20);
  });

  it.each(shots)("%s exists in public/docs", (src) => {
    expect(fs.existsSync(path.join(WEB_ROOT, "public", src.slice(1)))).toBe(true);
  });

  /*
   * The guide is read on a phone on mobile data, by the same people the app is
   * for. Full-resolution 1080px captures made the page 4 MB. The cap is
   * generous enough not to nag and tight enough that dropping a raw screencap
   * straight in fails here rather than on someone's bundle.
   */
  it("keeps every screenshot small enough to load over mobile data", () => {
    const oversized = shots
      .map((src) => ({
        src,
        kb: Math.round(fs.statSync(path.join(WEB_ROOT, "public", src.slice(1))).size / 1024)
      }))
      .filter((shot) => shot.kb > 120);

    expect(oversized).toEqual([]);
  });

  it("ships no screenshot the guide never shows", () => {
    const used = new Set(shots.map((src) => path.basename(src)));
    const orphans = fs.readdirSync(SHOT_DIR).filter((file) => !used.has(file));

    expect(orphans).toEqual([]);
  });
});
