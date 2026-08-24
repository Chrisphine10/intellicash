import fs from "node:fs";
import path from "node:path";
import React from "react";

/**
 * True when the file exists under `public/` at render time.
 *
 * The guide is written before every screen has been captured, and a role whose
 * screenshots are still outstanding must not render four broken-image icons —
 * a missing picture with its explanation still reads as documentation, a broken
 * frame reads as a broken site.
 *
 * This is a server component, so the check happens once at build time. If the
 * lookup itself fails we render the image: an unreadable filesystem is not
 * evidence that the screenshot is absent.
 */
function shotExists(src: string): boolean {
  try {
    return fs.existsSync(path.join(process.cwd(), "public", src.replace(/^\//, "")));
  } catch {
    return true;
  }
}

/** One phone screen in the guide. */
export function DocsShot({
  src,
  alt,
  caption
}: {
  src: string;
  alt: string;
  caption: string;
}) {
  const captured = shotExists(src);

  return (
    <figure className={captured ? "docs-shot" : "docs-shot docs-shot-pending"}>
      {captured ? (
        /*
         * A plain <img>, not next/image: these are fixed-size phone captures
         * served straight from /public, so the optimiser has nothing to add and
         * one more moving part could only break them.
         */
        <img alt={alt} className="docs-shot-image" loading="lazy" src={src} />
      ) : (
        <div aria-hidden="true" className="docs-shot-placeholder">
          <span>Screenshot coming</span>
        </div>
      )}
      <figcaption>{caption}</figcaption>
    </figure>
  );
}
