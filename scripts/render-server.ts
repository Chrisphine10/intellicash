import { createServer } from "node:http";
import { resolve } from "node:path";
import next from "next";
import { createApp } from "../apps/api/src/app";
import { prisma } from "../apps/api/src/lib/prisma";
import { assertDurableDatabase } from "../apps/api/src/lib/storage-safety";

// This process serves the API as well as the web app, so the same rule
// applies: do not take a group's money into storage that gets wiped.
assertDurableDatabase();

const port = Number(process.env.PORT ?? process.env.API_PORT ?? 3000);
// Render routes to the container's public interface, so 0.0.0.0 stays the
// default. Behind a reverse proxy (nginx on the VPS) bind HOST=127.0.0.1
// instead, or the app is reachable on its raw port over plain http and
// sidesteps TLS entirely.
const host = process.env.HOST ?? "0.0.0.0";
const webDir = resolve(process.cwd(), "apps/web");
const nextApp = next({ dev: false, dir: webDir });
const nextHandler = nextApp.getRequestHandler();
let server: ReturnType<typeof createServer> | null = null;

async function start() {
  await nextApp.prepare();

  // servesWebApp: this process serves Next.js HTML as well as the API, so the
  // CSP must permit the App Router's inline bootstrap scripts.
  const app = createApp({ includeNotFoundHandler: false, servesWebApp: true });

  app.all("*", (req, res) => {
    nextHandler(req, res);
  });

  server = createServer(app);

  server.listen(port, host, () => {
    console.log(`Intelli-Cash web and API listening on ${host}:${port}`);
  });
}

async function shutdown() {
  if (!server) {
    await prisma.$disconnect();
    process.exit(0);
  }

  server.close(async () => {
    await prisma.$disconnect();
    process.exit(0);
  });
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);

start().catch(async (error) => {
  console.error(error);
  await prisma.$disconnect();
  process.exit(1);
});
