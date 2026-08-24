import React from "react";

/**
 * One phone screen in the guide.
 *
 * Deliberately has no runtime check that the file exists. An earlier version
 * called `fs.existsSync(process.cwd() + "/public" + src)` so a screen that had
 * not been captured yet would show its caption instead of a broken frame. This
 * page is server-rendered on demand, and on the VPS the process runs from the
 * repo root rather than `apps/web` — so the check resolved nothing, returned
 * false for all 29 screenshots, and replaced the entire guide with placeholders
 * in production while every local check passed.
 *
 * The guarantee now lives where it can actually be verified:
 * `tests/docs-screenshots.test.ts` fails CI if any `src` here points at a file
 * that is not in `public/docs`, or if a file is shipped that nothing shows.
 * A build-time assertion beats a request-time guess about the filesystem.
 */
export function DocsShot({
  src,
  alt,
  caption
}: {
  src: string;
  alt: string;
  caption: string;
}) {
  return (
    <figure className="docs-shot">
      {/*
       * A plain <img>, not next/image: these are fixed-size phone captures
       * served straight from /public, so the optimiser has nothing to add and
       * one more moving part could only break them.
       */}
      <img alt={alt} className="docs-shot-image" loading="lazy" src={src} />
      <figcaption>{caption}</figcaption>
    </figure>
  );
}
