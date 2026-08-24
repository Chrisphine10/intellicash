import React from "react";

/**
 * One phone screen in the guide.
 *
 * Degrades to the caption alone when the image is missing, rather than showing
 * a broken-image icon. A guide is written before every screen has been captured
 * — and a missing picture with its explanation still reads as documentation,
 * where a broken frame reads as a broken site.
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
