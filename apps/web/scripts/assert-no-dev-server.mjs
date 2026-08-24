import net from "node:net";

/**
 * Refuses to start a production build while a dev server is running.
 *
 * `next dev` and `next build` both write to `.next`. Run them together and the
 * dev server ends up reading chunks the build has already replaced, which
 * surfaces later as `Cannot find module './1234.js'` on a page that has not
 * been touched in weeks. The stack trace points at Next internals and says
 * nothing about the real cause, so the time goes on hunting an imaginary bug in
 * the page.
 *
 * Failing here costs a second and names the actual problem.
 *
 * Skipped in CI, where nothing else is running and a false positive would block
 * a deploy for no reason.
 */
const PORT = Number(process.env.PORT ?? process.env.NEXT_DEV_PORT ?? 3000);

if (process.env.CI) {
  process.exit(0);
}

const inUse = await new Promise((resolve) => {
  const socket = net.connect({ port: PORT, host: "127.0.0.1" });
  // Short: this runs before every build, and a slow check would be worse than
  // the problem it prevents.
  socket.setTimeout(400);
  socket.once("connect", () => {
    socket.destroy();
    resolve(true);
  });
  socket.once("timeout", () => {
    socket.destroy();
    resolve(false);
  });
  socket.once("error", () => resolve(false));
});

if (inUse) {
  console.error(
    [
      "",
      `Something is already listening on port ${PORT} — almost certainly \`next dev\`.`,
      "",
      "Building now would overwrite the .next directory that dev server is reading",
      "from, and it would start failing with errors like:",
      "",
      "    Cannot find module './9225.js'",
      "",
      "on pages that are perfectly fine. Stop the dev server first, then build.",
      "(If the port is genuinely something else, set PORT to the dev server's port.)",
      ""
    ].join("\n")
  );
  process.exit(1);
}
