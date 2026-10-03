// Local QA screenshots of the group map and the Map provider setting.
// Usage: node qa/shoot-maps.mjs <outDir>
import { chromium } from "playwright";

const outDir = process.argv[2] ?? ".";
const base = "http://localhost:3300";
const browser = await chromium.launch({ channel: "chrome" });
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();
const problems = [];
page.on("console", (message) => {
  if (message.type() === "error") problems.push(message.text());
});

await page.goto(`${base}/login`, { waitUntil: "networkidle", timeout: 180000 });
await page.getByPlaceholder("0712 345 678").fill("0700000001");
await page.locator('input[type="password"]').fill("IntellicashDemo#2026");
await page.getByRole("button", { name: "Sign in", exact: true }).click();
await page.waitForURL(/dashboard/, { timeout: 180000 });

await page.goto(`${base}/dashboard/meetings`, { waitUntil: "networkidle", timeout: 180000 });
const map = page.locator(".meeting-map-card");
await map.waitFor({ timeout: 60000 });
await page.waitForTimeout(4000); // tiles
await map.scrollIntoViewIfNeeded();
await map.screenshot({ path: `${outDir}/map-meetings.png` });
console.log("leaflet map drawn:", await page.locator(".leaflet-container").count(), "tiles:", await page.locator(".leaflet-tile-loaded").count());

await page.goto(`${base}/dashboard/integrations`, { waitUntil: "networkidle", timeout: 180000 });
const card = page.getByRole("heading", { name: "Map provider" }).locator("xpath=ancestor::section[1]");
await card.waitFor({ timeout: 60000 });
await card.scrollIntoViewIfNeeded();
await card.screenshot({ path: `${outDir}/map-provider-card.png` });

const csp = problems.filter((text) => /Content Security Policy|CSP/i.test(text));
console.log("console errors:", problems.length, "| CSP violations:", csp.length);
for (const text of csp.slice(0, 5)) console.log("  ", text.slice(0, 200));
await browser.close();
