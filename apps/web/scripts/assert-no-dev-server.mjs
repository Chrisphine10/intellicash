/**
 * Refuses to start a production build while a Next DEV server is running.
 *
 * `next dev` and `next build` both write to `.next`. Run them together and the
 * dev server ends up reading chunks the build has already replaced, which
 * surfaces later as `Cannot find module './1234.js'` on a page that has not
 * been touched in weeks. The stack trace points at Next internals and says
 * nothing about the real cause.
 *
 * The check identifies a dev server POSITIVELY rather than treating an occupied
 * port as proof of one. The first version did the latter and broke the
 * production deploy: the live app listens on 3000, so the guard refused to
 * build on the very machine that had no dev server at all. An occupied port
 * means something is listening — nothing more.
 *
 * The marker is an UNHASHED chunk. `next dev` serves
 * `/_next/static/chunks/webpack.js` under exactly that name; a production build
 * hashes every chunk filename, so the same path 404s. Verified against both a
 * real dev server (200) and the live site (404) rather than reasoned about —
 * two earlier guesses, React Refresh in the HTML and the HMR endpoint, both
 * looked plausible and matched nothing.
 */
const PORT = Number(process.env.PORT ?? process.env.NEXT_DEV_PORT ?? 3000);

if (process.env.CI) {
  process.exit(0);
}

async function looksLikeDevServer() {
  const controller = new AbortController();
  // Short: this runs before every build, and a slow check would be worse than
  // the problem it prevents.
  const timer = setTimeout(() => controller.abort(), 800);
  try {
    const response = await fetch(`http://127.0.0.1:${PORT}/_next/static/chunks/webpack.js`, {
      signal: controller.signal
    });
    return response.status === 200;
  } catch {
    // Nothing listening, not HTTP, or too slow to be a local dev server.
    return false;
  } finally {
    clearTimeout(timer);
  }
}

if (await looksLikeDevServer()) {
  console.error(
    [
      "",
      `A Next dev server is running on port ${PORT}.`,
      "",
      "Building now would overwrite the .next directory it is reading from, and it",
      "would start failing with errors like:",
      "",
      "    Cannot find module './9225.js'",
      "",
      "on pages that are perfectly fine. Stop the dev server first, then build.",
      ""
    ].join("\n")
  );
  process.exit(1);
}
