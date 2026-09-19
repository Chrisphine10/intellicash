// QA harness: a tiny check registry, an API client and direct database access.
//
// Every check records what was EXPECTED (computed independently, from the
// transactions the script itself posted — never read back from the code under
// test) and what the system ACTUALLY returned, so a run is an audit trail
// rather than a green tick.
//
// Usage: the scenario files import { api, db, check, section, world } from here.
// Environment: QA_API (default http://localhost:4100/api/v1) and the QA
// database, which must be the one that API is running against.

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { PrismaClient } from "@prisma/client";

const here = path.dirname(fileURLToPath(import.meta.url));
export const API = process.env.QA_API ?? "http://localhost:4100/api/v1";
const DB_FILE = process.env.QA_DB ?? path.resolve(here, "../apps/api/prisma/qa.db").replace(/\\/g, "/");

export const db = new PrismaClient({ datasources: { db: { url: `file:${DB_FILE}` } } });

// ---- results ---------------------------------------------------------------
const RESULTS = path.join(here, "results.json");
const state = fs.existsSync(RESULTS) ? JSON.parse(fs.readFileSync(RESULTS, "utf8")) : { checks: [] };
let currentSection = "(none)";
export function section(name) {
  currentSection = name;
  console.log(`\n== ${name}`);
}
export function check(id, description, expected, actual, note = "") {
  const pass = JSON.stringify(expected) === JSON.stringify(actual);
  state.checks = state.checks.filter((c) => c.id !== id);
  state.checks.push({ id, section: currentSection, description, expected, actual, pass, note });
  console.log(`${pass ? "PASS" : "FAIL"}  ${id}  ${description}${pass ? "" : `\n        expected ${JSON.stringify(expected)}\n        actual   ${JSON.stringify(actual)}`}${note ? `\n        ${note}` : ""}`);
  return pass;
}
export function save() {
  fs.writeFileSync(RESULTS, JSON.stringify(state, null, 2));
  const failed = state.checks.filter((c) => !c.pass);
  console.log(`\n${state.checks.length} checks recorded, ${failed.length} failing`);
}

// ---- world (entities other scenario files reuse) ------------------------------
const WORLD = path.join(here, "world.json");
export const world = fs.existsSync(WORLD) ? JSON.parse(fs.readFileSync(WORLD, "utf8")) : {};
export function saveWorld() {
  fs.writeFileSync(WORLD, JSON.stringify(world, null, 2));
}

// ---- API client --------------------------------------------------------------
export async function api(cookie, method, url, body) {
  const res = await fetch(`${API}${url}`, {
    method,
    headers: { "content-type": "application/json", ...(cookie ? { cookie } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body)
  });
  const text = await res.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch {
    json = { raw: text };
  }
  const setCookie = res.headers.get("set-cookie");
  return {
    status: res.status,
    data: json?.data,
    error: json?.error,
    body: json,
    cookie: setCookie ? setCookie.split(";")[0] : undefined
  };
}

export async function login(identifier, password) {
  const body = identifier.includes("@") ? { email: identifier, password } : { phone: identifier, password };
  const r = await api(undefined, "POST", "/auth/login", body);
  if (r.status !== 200) throw new Error(`login ${identifier} -> ${r.status} ${JSON.stringify(r.error)}`);
  return { cookie: r.cookie, user: r.data };
}

export const DEMO_PASSWORD = "IntellicashDemo#2026";
export const kes = (cents) => `KES ${(cents / 100).toLocaleString("en-KE", { minimumFractionDigits: 2 })}`;
export const MONTH_MS = 30 * 24 * 60 * 60 * 1000;
export const daysAgo = (n) => new Date(Date.now() - n * 24 * 60 * 60 * 1000);
let seq = Number(process.env.QA_SEQ ?? Date.now() % 100000);
export const uniq = () => String(seq++);
export const requestId = (label) => `qa-${label}-${Date.now().toString(36)}-${seq++}`;
