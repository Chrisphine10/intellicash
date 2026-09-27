// Screenshots of the group Payments page for visual review (local dev only).
// Usage: node qa/shoot-payments.mjs <phone> <outDir> [groupId] [extraPath...]
import { chromium } from "playwright";

const [phone = "0700000005", outDir = ".", groupId = "cmujc53nk001dv0pcgzy2h9ej", ...extra] = process.argv.slice(2);
const base = "http://localhost:3300";
const password = "IntellicashDemo#2026";

const browser = await chromium.launch({ channel: "chrome" });
for (const scheme of ["light", "dark"]) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, colorScheme: scheme });
  const page = await context.newPage();
  await page.goto(`${base}/login`, { waitUntil: "networkidle", timeout: 120000 });
  await page.getByPlaceholder("0712 345 678").fill(phone);
  await page.locator('input[type="password"]').fill(password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await page.waitForURL(/dashboard/, { timeout: 120000 });

  await page.goto(`${base}/dashboard/groups/${groupId}/payment-providers`, { waitUntil: "networkidle", timeout: 120000 });
  await page.getByRole("tab", { name: "Payment options" }).waitFor({ timeout: 60000 });
  const tabs = ["Payment options", "Request a payment", "Settlement account", "Own provider accounts"];
  for (const [index, name] of tabs.entries()) {
    await page.getByRole("tab", { name }).click();
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${outDir}/${scheme}-${phone}-${index + 1}.png`, fullPage: true });
  }
  if (scheme === "light") {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole("tab", { name: "Payment options" }).click();
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${outDir}/mobile-${phone}.png`, fullPage: true });
    await page.setViewportSize({ width: 1280, height: 900 });
    for (const [index, path] of extra.entries()) {
      try {
        await page.goto(`${base}${path}`, { waitUntil: "load", timeout: 120000 });
        await page.waitForTimeout(5000);
        await page.screenshot({ path: `${outDir}/page-${phone}-${index + 1}.png`, fullPage: true });
      } catch (error) {
        console.log(`skipped ${path}: ${String(error.message).slice(0, 120)}`);
      }
    }
  }
  await context.close();
}
await browser.close();
console.log("done");
