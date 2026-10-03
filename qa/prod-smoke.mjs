// Read-only production smoke check: status codes for key routes, and the public
// pages rendered in a real browser with no console or CSP errors.
// Usage: node qa/prod-smoke.mjs [baseUrl]
import { chromium } from "playwright";

const base = process.argv[2] ?? "https://intellicash.co.ke";
const expectations = [
  ["GET", "/health", 200],
  ["GET", "/", 200],
  ["GET", "/login", 200],
  ["GET", "/pay/complete", 200],
  ["GET", "/brand/payments/mpesa.png", 200],
  ["GET", "/api/v1/integrations/map-config", 401],
  ["GET", "/api/v1/group-duplicates", 401],
  ["GET", "/api/v1/groups/x/needs-assessments", 401],
  ["GET", "/api/v1/groups/x/payment-settings", 401],
  ["GET", "/api/v1/payment-admin/fee-rules", 401],
  ["POST", "/api/v1/groups/x/payments/quote", 401],
  ["GET", "/api/v1/no-such-route", 404]
];

let failures = 0;
for (const [method, path, expected] of expectations) {
  const response = await fetch(`${base}${path}`, {
    method,
    headers: method === "POST" ? { "content-type": "application/json" } : {},
    body: method === "POST" ? "{}" : undefined,
    redirect: "manual"
  });
  const ok = response.status === expected;
  if (!ok) failures += 1;
  console.log(`${ok ? "OK  " : "FAIL"} ${method} ${path} -> ${response.status} (expected ${expected})`);
}

const browser = await chromium.launch({ channel: "chrome" });
for (const path of ["/", "/login"]) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const errors = [];
  page.on("console", (message) => message.type() === "error" && errors.push(message.text()));
  page.on("pageerror", (error) => errors.push(error.message));
  await page.goto(`${base}${path}`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(1500);
  const text = (await page.innerText("body")).length;
  const rendered = text > 200;
  if (!rendered || errors.length) failures += 1;
  console.log(`${rendered && !errors.length ? "OK  " : "FAIL"} render ${path}: ${text} chars, ${errors.length} console errors`);
  for (const error of errors.slice(0, 3)) console.log(`       ${error.slice(0, 160)}`);
  await page.close();
}
await browser.close();
console.log(failures ? `\n${failures} problem(s)` : "\nAll checks passed.");
process.exitCode = failures ? 1 : 0;
