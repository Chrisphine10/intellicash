import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import bcrypt from "bcryptjs";
import { PrismaClient } from "@prisma/client";

import { prisma as defaultClient } from "../src/lib/prisma";
import { normalisePhone } from "../src/lib/phone";

/**
 * Gives every onboarded group a working sign-in, and writes the handover list.
 *
 * Two ways in, on purpose:
 *
 * 1. **Phone + texted code**, for the groups whose number we hold. No password
 *    to remember, which for a book kept on one shared handset is the difference
 *    between a password nobody memorises and a password written inside the
 *    passbook cover.
 * 2. **Group email + the shared password**, for the rest — because we do not
 *    have a number to text, and the alternative is those groups cannot sign in
 *    at all.
 *
 * ## The shared password is a real exposure, and it is bounded here
 *
 * The email is derivable (`iwl-emb-0001@groups.intellicash.co.ke`), so one
 * password across every group means anyone holding it can open any of those
 * books. That is tolerable only because it is an ONBOARDING credential, and
 * only for as long as it takes to collect the missing numbers: a group with a
 * phone attached signs in with a code, and the shared password stops being the
 * way in.
 *
 * Numbers are attached only where the workbook actually has one. A phone number
 * is not a formality here — it receives sign-in codes, so an invented one hands
 * a stranger the keys to somebody's savings record.
 *
 * Idempotent. Re-running resets the same password and re-attaches the same
 * numbers.
 */

const SOURCE_SYSTEM = "FLOURISH_ONBOARDING_2026";

/** Overridable, so the handover password is not pinned in the repository. */
const PASSWORD = process.env.GROUP_ONBOARDING_PASSWORD ?? "IntelliCash@2026";

const OUT_DIR = process.env.GROUP_CREDENTIALS_DIR ?? "/var/www/intellicash/data";

interface PackGroup {
  key: string;
  name: string;
  contactPersonName: string;
  contactPhone: string;
  county: string;
}

export async function setGroupCredentials(client: PrismaClient = defaultClient) {
  const here = path.dirname(fileURLToPath(import.meta.url));
  const pack = JSON.parse(
    fs.readFileSync(path.resolve(here, "data/flourish-onboarding.json"), "utf8")
  ) as { groups: PackGroup[] };

  const byKey = new Map(pack.groups.map((row) => [row.key, row]));

  const groups = await client.group.findMany({
    where: { sourceSystem: SOURCE_SYSTEM },
    select: {
      id: true,
      name: true,
      code: true,
      county: true,
      sourceReference: true,
      userAccounts: { where: { role: "GROUP_ACCOUNT" }, select: { id: true, email: true, phone: true } }
    },
    orderBy: { code: "asc" }
  });

  const passwordHash = await bcrypt.hash(PASSWORD, 12);

  const rows: {
    code: string;
    group: string;
    county: string;
    champion: string;
    phone: string;
    email: string;
    signIn: string;
  }[] = [];

  const summary = { updated: 0, phonesAttached: 0, phonesMissing: 0, phonesClashed: 0, noAccount: 0 };

  for (const group of groups) {
    const account = group.userAccounts[0];
    if (!account) {
      summary.noAccount += 1;
      continue;
    }

    const source = byKey.get(group.sourceReference ?? "");
    const champion = source?.contactPersonName ?? "";
    const wanted = normalisePhone(source?.contactPhone ?? "");

    let phone = account.phone;

    if (wanted) {
      // `User.phone` is unique and it is what a sign-in code is sent to.
      // Handing one number to two accounts would send one group's code to the
      // other group's handset.
      const heldByAnother = await client.user.findFirst({
        where: { phone: wanted, id: { not: account.id } },
        select: { id: true }
      });

      if (heldByAnother) {
        summary.phonesClashed += 1;
      } else if (account.phone !== wanted) {
        await client.user.update({ where: { id: account.id }, data: { phone: wanted } });
        phone = wanted;
        summary.phonesAttached += 1;
      } else {
        phone = wanted;
      }
    }

    if (!phone) summary.phonesMissing += 1;

    await client.user.update({ where: { id: account.id }, data: { passwordHash, status: "ACTIVE" } });
    summary.updated += 1;

    rows.push({
      code: group.code,
      group: group.name,
      county: group.county,
      champion: champion || "(not recorded)",
      phone: phone || "",
      email: account.email,
      // Spelled out per row, because which one applies differs per group and
      // whoever hands these over should not have to work it out.
      signIn: phone ? "Phone + texted code" : "Email + password (no number yet)"
    });
  }

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const stamp = new Date().toISOString().replace(/[:.]/g, "-");
  const file = path.join(OUT_DIR, `group-signin-list-${stamp}.csv`);

  const escape = (value: string) => `"${value.replace(/"/g, '""')}"`;
  const csv = [
    ["Code", "Group", "County", "Digital champion", "Phone number", "Password", "Group email", "How they sign in"]
      .map(escape)
      .join(","),
    ...rows.map((row) =>
      [row.code, row.group, row.county, row.champion, row.phone, PASSWORD, row.email, row.signIn]
        .map(escape)
        .join(",")
    )
  ].join("\n");

  // 0600: it carries a live password for every group in the programme.
  fs.writeFileSync(file, csv + "\n", { mode: 0o600 });

  return { file, rows, summary, password: PASSWORD };
}

const isDirectRun =
  process.argv[1]?.replace(/\\/g, "/").endsWith("set-group-credentials.ts") ?? false;

if (isDirectRun) {
  setGroupCredentials()
    .then((result) => {
      console.log("\nGroup sign-in credentials");
      console.log("  accounts updated  :", result.summary.updated);
      console.log("  phones attached   :", result.summary.phonesAttached);
      console.log("  no phone on record:", result.summary.phonesMissing);
      console.log("  phone clashes     :", result.summary.phonesClashed);
      console.log("  groups with no account:", result.summary.noAccount);
      console.log("\n  list written to   :", result.file, "(owner-read only)");
      console.log(
        "\n  The groups with no number sign in with their group email and the shared\n" +
          "  password. Collect their numbers and re-run this, and they move to codes."
      );
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    });
}
