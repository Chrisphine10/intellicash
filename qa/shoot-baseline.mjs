// Local QA screenshots of the baseline needs-assessment card and the
// possible-duplicates card. Usage: node qa/shoot-baseline.mjs <outDir>
import { chromium } from "playwright";

const outDir = process.argv[2] ?? ".";
const base = "http://localhost:3300";
const groupId = "cmujc53nk001dv0pcgzy2h9ej";

const browser = await chromium.launch({ channel: "chrome" });
for (const [scheme, width] of [["light", 1280], ["dark", 1280], ["light", 390]]) {
  const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: scheme });
  const page = await context.newPage();
  await page.goto(`${base}/login`, { waitUntil: "networkidle", timeout: 180000 });
  await page.getByPlaceholder("0712 345 678").fill("0700000001");
  await page.locator('input[type="password"]').fill("IntellicashDemo#2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await page.waitForURL(/dashboard/, { timeout: 180000 });

  await page.goto(`${base}/dashboard/groups/${groupId}`, { waitUntil: "networkidle", timeout: 180000 });
  const card = page.locator("section.needs-assessment");
  await card.waitFor({ timeout: 60000 });
  await card.scrollIntoViewIfNeeded();
  await card.screenshot({ path: `${outDir}/baseline-${scheme}-${width}.png` });
  if (width === 1280 && scheme === "light") {
    await card.locator("summary").click();
    await page.waitForTimeout(400);
    await card.screenshot({ path: `${outDir}/baseline-expanded.png` });
    await page.goto(`${base}/dashboard/groups`, { waitUntil: "networkidle", timeout: 180000 });
    await page.waitForTimeout(2500);
    await page.screenshot({ path: `${outDir}/groups-duplicates.png`, fullPage: false });
  }
  await context.close();
}
await browser.close();
console.log("done");
