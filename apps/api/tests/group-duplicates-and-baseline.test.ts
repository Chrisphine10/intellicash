import request from "supertest";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { groupNameKey, groupNameSimilarity } from "../src/services/group-login-link";
import { mergeDuplicateGroups } from "../prisma/merge-duplicate-groups";
import { importNeedsAssessment } from "../prisma/import-needs-assessment";
import { NOT_ASKED_NOTE, parseKoboCsv, parseSubmission, scorecardAnswers } from "../src/domain/needs-assessment";

const app = createApp();
const RUN = Date.now().toString(36).toUpperCase();
const code = (suffix: string) => `IWL-TST-${RUN}-${suffix}`;

async function signIn(role: string) {
  const account = demoAccounts.find((candidate) => candidate.role === role)!;
  const response = await request(app).post("/api/v1/auth/login").send({ phone: account.phone, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

const createdGroupCodes: string[] = [];
async function group(suffix: string, data: Record<string, unknown> = {}) {
  const created = await prisma.group.create({
    data: { name: `Test ${suffix} ${RUN}`, code: code(suffix), phase: "MOBILISATION", county: "Embu", ...data }
  });
  createdGroupCodes.push(created.code);
  return created;
}

afterAll(async () => {
  const groups = await prisma.group.findMany({
    where: { OR: [{ code: { startsWith: `IWL-TST-${RUN}` } }, { name: { contains: RUN } }] },
    select: { id: true }
  });
  const ids = groups.map((g) => g.id);
  await prisma.user.deleteMany({ where: { OR: [{ groupId: { in: ids } }, { email: { contains: RUN.toLowerCase() } }] } });
  await prisma.systemIssue.deleteMany({ where: { groupId: { in: ids } } });
  await prisma.groupVisit.deleteMany({ where: { groupId: { in: ids } } });
  await prisma.group.deleteMany({ where: { id: { in: ids } } });
  await prisma.programme.deleteMany({ where: { name: { contains: RUN } } });
});

describe("telling one group from another by name", () => {
  it("ignores the generic words people add or drop, and keeps the ones that name a different group", () => {
    expect(groupNameKey("Bethsaida Women SHG")).toBe(groupNameKey("Bethsaida Women Group"));
    expect(groupNameKey("Mutira Unique Self Help Group")).toBe(groupNameKey("Mutira Unique SHG"));
    expect(groupNameKey("Mwikuria Self Help Group (II)")).not.toBe(groupNameKey("Mwikuria Self Help Group"));
    expect(groupNameKey("Gichugu Men")).not.toBe(groupNameKey("Gichugu Women"));
    expect(groupNameSimilarity("Karumandi Icon Youth SHG", "Icon Youth")).toBe(1);
  });

  it("refuses a phone sign-up that repeats a group registered in another county", async () => {
    await group("SIGNUPEXISTING", { name: `Mutira Unique ${RUN} SHG`, county: "Kirinyaga" });
    const response = await request(app)
      .post("/api/v1/auth/register")
      .send({
        accountType: "GROUP",
        name: `Mutira Unique ${RUN} Self Help Group`,
        phone: "0799 113 401",
        password: "harvest-2026",
        county: "Embu"
      })
      .expect(409);
    expect(response.body.error.code).toBe("GROUP_EXISTS");

    // A different group still signs up.
    const fresh = await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "GROUP", name: `Kiambaa Sunrise ${RUN} Youth`, phone: "0799 113 402", password: "harvest-2026", county: "Embu" })
      .expect(201);
    expect(fresh.body.data.groupId).toBeTruthy();
    await prisma.user.deleteMany({ where: { phone: { in: ["254799113401", "254799113402"] } } });
  });

  it("lists likely duplicates for an admin only", async () => {
    await group("DUPA", { name: `Kathangari ${RUN} Wendani Women Group` });
    await group("DUPB", { name: `Kathangari ${RUN} Wendani Women SHG` });
    const admin = await signIn("IWL_ADMIN");
    const response = await request(app).get("/api/v1/group-duplicates").set("Cookie", admin).expect(200);
    const pair = response.body.data.pairs.find((p: { groups: Array<{ code: string }> }) =>
      p.groups.some((g) => g.code === code("DUPA"))
    );
    expect(pair?.sameKey).toBe(true);
    await request(app).get("/api/v1/group-duplicates").set("Cookie", await signIn("PARTNER_OFFICER")).expect(403);
  });
});

describe("folding a duplicate into the group in use", () => {
  it("moves the register's history and login to the group in use, then removes the empty record", async () => {
    const keep = await group("KEEP", { sourceSystem: "MOBILE_SELF_SIGNUP", county: "Not set" });
    await prisma.member.create({ data: { groupId: keep.id, fullName: "Member One", phone: "254700113501" } });
    const fold = await group("FOLD", {
      sourceSystem: "FLOURISH_ONBOARDING_2026",
      sourceReference: `test-${RUN}`,
      county: "Kirinyaga",
      subCounty: "Kirinyaga Central",
      location: "Kerugoya",
      phase: "INTENSIVE"
    });
    const visit = await prisma.groupVisit.create({
      data: { groupId: fold.id, clientRequestId: `flourish-test-${RUN}`, startedAt: new Date() }
    });
    await prisma.visitActionItem.create({ data: { visitId: visit.id, groupId: fold.id, title: "Open a bank account" } });
    await prisma.groupEnterprise.create({ data: { groupId: fold.id, name: "Dairy" } });
    const login = await prisma.user.create({
      data: {
        name: "Register login",
        email: `register-${RUN.toLowerCase()}@groups.example`,
        passwordHash: "x",
        role: "GROUP_ACCOUNT",
        groupId: fold.id
      }
    });

    const dry = await mergeDuplicateGroups({ pairs: [{ keep: keep.code, fold: fold.code }] });
    expect(dry[0]?.status).toBe("WOULD_MERGE");
    expect(await prisma.group.count({ where: { id: fold.id } })).toBe(1);

    const [outcome] = await mergeDuplicateGroups({ pairs: [{ keep: keep.code, fold: fold.code }], commit: true });
    expect(outcome?.status).toBe("MERGED");

    expect(await prisma.group.count({ where: { id: fold.id } })).toBe(0);
    const after = await prisma.group.findUniqueOrThrow({ where: { id: keep.id } });
    expect(after.code).toBe(fold.code);
    expect(after).toMatchObject({
      sourceSystem: "FLOURISH_ONBOARDING_2026",
      sourceReference: `test-${RUN}`,
      county: "Kirinyaga",
      subCounty: "Kirinyaga Central",
      location: "Kerugoya",
      phase: "INTENSIVE"
    });
    expect(await prisma.groupVisit.count({ where: { groupId: keep.id } })).toBe(1);
    expect(await prisma.visitActionItem.count({ where: { groupId: keep.id } })).toBe(1);
    expect(await prisma.groupEnterprise.count({ where: { groupId: keep.id } })).toBe(1);
    expect((await prisma.user.findUniqueOrThrow({ where: { id: login.id } })).groupId).toBe(keep.id);
    expect(await prisma.member.count({ where: { groupId: keep.id } })).toBe(1);
    expect(await prisma.auditEvent.count({ where: { type: "GROUP_MERGED", entityId: keep.id } })).toBe(1);

    // Running it again changes nothing.
    const [again] = await mergeDuplicateGroups({ pairs: [{ keep: keep.code, fold: fold.code }], commit: true });
    expect(again?.status).toBe("ALREADY_MERGED");
  });

  it("refuses to fold away a group that holds members", async () => {
    const keep = await group("KEEP2");
    const fold = await group("FOLD2");
    await prisma.member.create({ data: { groupId: fold.id, fullName: "Someone", phone: "254700113502" } });
    const [outcome] = await mergeDuplicateGroups({ pairs: [{ keep: keep.code, fold: fold.code }], commit: true });
    expect(outcome?.status).toBe("REFUSED");
    expect(await prisma.group.count({ where: { id: fold.id } })).toBe(1);
  });
});

// A made-up submission in the export's own shape (`;`, quotes, Kobo's U+FFFD
// for a dash). No real person's details.
const HEADER = [
  "Group Name", "County", "Sub-County", "Ward / Village", "Visit Date", "Field Officer",
  "_GPS Location_latitude", "_GPS Location_longitude", "_GPS Location_precision",
  "Total Members", "Women Members", "Leadership Officers", "Chairperson \uFFFD Full Name", "Chairperson \uFFFD Phone Number",
  "Governance Checklist", "Written Constitution / By-Laws Exists", "Leadership Formally Elected", "Quorum Requirement Defined",
  "Meeting Minutes Recorded", "Loan Approval Criteria", "Bank Account", "Three-Key System in Use",
  "Share Value per Member (KES)", "Minimum Shares per Meeting", "Total Savings (KES)", "Active Loan Portfolio (KES)",
  "Welfare Fund Balance (KES)", "Record-Keeping Inspection", "Member Passbook Up to Date", "Savings Register Up to Date",
  "Attendance Register Available", "Welfare Fund Register Available", "Training Priority Rating", "Financial Literacy",
  "Members with Smartphones", "Current Use of Digital Tools", "Key Strengths Observed", "Safeguarding or Protection Concerns",
  "Enumerator Full Name", "_uuid", "__version__", "_index"
];

function csv(rows: string[][]) {
  const line = (cells: string[]) => cells.map((cell) => (/[;"\n]/.test(cell) ? `"${cell.replace(/"/g, '""')}"` : cell)).join(";");
  return "\uFEFF" + [line(HEADER), ...rows.map(line)].join("\r\n") + "\r\n";
}

function submission(uuid: string, overrides: Partial<Record<string, string>> = {}) {
  const base: Record<string, string> = {
    "Group Name": "Test Group", County: "Kirinyaga", "Sub-County": "Kirinyaga Central", "Ward / Village": "Kerugoya",
    "Visit Date": "2026-05-07", "Field Officer": "Field Officer Test",
    "_GPS Location_latitude": "-0.4946", "_GPS Location_longitude": "37.2741", "_GPS Location_precision": "8",
    "Total Members": "20", "Women Members": "18", "Leadership Officers": "", "Chairperson \uFFFD Full Name": "Chair Person",
    "Chairperson \uFFFD Phone Number": "0712 000 111", "Governance Checklist": "", "Written Constitution / By-Laws Exists": "Yes",
    "Leadership Formally Elected": "yes", "Quorum Requirement Defined": "Yes", "Meeting Minutes Recorded": "No",
    "Loan Approval Criteria": "Yes", "Bank Account": "No", "Three-Key System in Use": "No",
    "Share Value per Member (KES)": "200", "Minimum Shares per Meeting": "1", "Total Savings (KES)": "20,000",
    "Active Loan Portfolio (KES)": "6000", "Welfare Fund Balance (KES)": "0", "Record-Keeping Inspection": "",
    "Member Passbook Up to Date": "No", "Savings Register Up to Date": "Yes", "Attendance Register Available": "Yes",
    "Welfare Fund Register Available": "No", "Training Priority Rating": "", "Financial Literacy": "H",
    "Members with Smartphones": "5", "Current Use of Digital Tools": "Mpesa", "Key Strengths Observed": "Regular; \"active\" meetings\nand trust",
    "Safeguarding or Protection Concerns": "A private concern", "Enumerator Full Name": "Enumerator Test",
    _uuid: uuid, __version__: "vTest", _index: "1"
  };
  const row = { ...base, ...overrides };
  return HEADER.map((label) => row[label] ?? "");
}

describe("reading the Kobo needs-assessment export", () => {
  it("parses quoted cells, Kobo's dashes, money and dates, and checks the figures", () => {
    const rows = parseKoboCsv(csv([submission("u-1", { "Minimum Shares per Meeting": "260000", "_GPS Location_latitude": "-1.2841", "_GPS Location_longitude": "36.8155" })]));
    expect(rows).toHaveLength(1);
    const parsed = parseSubmission(rows[0]!);
    expect(parsed.chairperson.name).toBe("Chair Person");
    expect(parsed.fields.totalSavingsCents).toBe(2_000_000);
    expect(parsed.assessedOn?.toISOString()).toBe("2026-05-07T12:00:00.000Z");
    expect(parsed.answers.observations?.["Key Strengths Observed"]).toBe('Regular; "active" meetings\nand trust');
    expect(JSON.stringify(parsed.answers)).not.toMatch(/0712 000 111|Phone Number/);
    expect(parsed.qualityFlags.join(" ")).toMatch(/default location/);
    expect(parsed.qualityFlags.join(" ")).toMatch(/money figure typed into the wrong box/);
  });

  it("answers only the scorecard questions the form asked, and marks the rest not asked", () => {
    const row = parseKoboCsv(csv([submission("u-2")]))[0]!;
    const answers = scorecardAnswers(row, ["constitution_written", "quorum_respected", "cash_reconciles"]);
    const byKey = Object.fromEntries(answers.map((answer) => [answer.questionKey, answer]));
    expect(byKey.constitution_written?.choice).toBe("YES");
    expect(byKey.quorum_respected?.choice).toBe("PARTIAL");
    expect(byKey.bank_or_mobile_account).toBeUndefined(); // "No bank account" is not "no account at all"
    expect(byKey.cash_reconciles).toMatchObject({ choice: "NOT_APPLICABLE", note: NOT_ASKED_NOTE });
  });
});

describe("importing the needs assessment as the baseline", () => {
  let target: { id: string; code: string };

  beforeAll(async () => {
    // Filed under the wrong county by the register; the form and its GPS say Kirinyaga.
    target = await group("BASELINE", { county: "Embu", subCounty: "Manyatta", location: "Kithimu" });
  });

  it("records the visit, profile and partial scorecard once, corrects the county, and invents no money", async () => {
    const uuid = `uuid-${RUN}`;
    // Loans of KSh 250,000 against savings of KSh 20,000: a check that must fail.
    const text = csv([submission(uuid, { "Active Loan Portfolio (KES)": "250000" }), submission(`dup-${RUN}`)]);
    const matches = {
      submissions: {
        [uuid]: { index: 1, code: target.code },
        [`dup-${RUN}`]: { index: 2, rejected: "duplicate submission" }
      }
    };
    const ledgerBefore = await prisma.ledgerEntry.count({ where: { groupId: target.id } });

    const dry = await importNeedsAssessment({ csvText: text, matches });
    expect(dry.imported).toHaveLength(1);
    expect(await prisma.groupNeedsAssessment.count({ where: { groupId: target.id } })).toBe(0);

    const report = await importNeedsAssessment({ csvText: text, matches, commit: true });
    expect(report.imported).toHaveLength(1);
    expect(report.rejected).toHaveLength(1);
    expect(report.locationCorrections).toHaveLength(1);

    const again = await importNeedsAssessment({ csvText: text, matches, commit: true });
    expect(again.imported).toHaveLength(0);
    expect(again.alreadyImported).toHaveLength(1);

    const visits = await prisma.groupVisit.findMany({ where: { groupId: target.id }, include: { assessment: true } });
    expect(visits).toHaveLength(1);
    expect(visits[0]?.visitType).toBe("INITIAL");
    expect(visits[0]?.startedAt.toISOString()).toBe("2026-05-07T12:00:00.000Z");
    expect(visits[0]?.assessment).toBeTruthy();

    const after = await prisma.group.findUniqueOrThrow({ where: { id: target.id } });
    expect(after).toMatchObject({ county: "Kirinyaga", subCounty: "Kirinyaga Central", location: "Kerugoya" });
    expect(after.gpsLatitude).toBeCloseTo(-0.4946);
    expect(after.contactPersonName).toBe("Chair Person");
    expect(await prisma.ledgerEntry.count({ where: { groupId: target.id } })).toBe(ledgerBefore);

    // The group page reads it, with the scorecard's coverage.
    const admin = await signIn("IWL_ADMIN");
    const read = await request(app).get(`/api/v1/groups/${target.id}/needs-assessments`).set("Cookie", admin).expect(200);
    const baseline = read.body.data[0];
    expect(baseline.baseline).toBe(true);
    expect(baseline.totalSavingsCents).toBe(2_000_000);
    expect(baseline.scorecard.asked).toBeGreaterThan(0);
    expect(baseline.scorecard.asked).toBeLessThan(baseline.scorecard.total);
    expect(baseline.answers.leadership).toBeTruthy();
    expect(baseline.answers.observations).toBeTruthy();
    expect(baseline.qualityFlags.join(" ")).toMatch(/more than ten times/);

    // The failed check is in the developers' issue log, once, however often the import runs.
    const issues = await prisma.systemIssue.findMany({ where: { groupId: target.id, source: "DATA_QUALITY" } });
    expect(issues.map((issue) => issue.title).join(" ")).toMatch(/loan portfolio is more than ten times total savings/);
    expect(issues.every((issue) => issue.occurrences === 1)).toBe(true);
  });

  it("shows a partner the group's profile but not the people in it", async () => {
    const partnerAccount = demoAccounts.find((account) => account.role === "PARTNER_OFFICER")!;
    const partnerUser = await prisma.user.findFirstOrThrow({ where: { phone: partnerAccount.phone } });
    const programme = await prisma.programme.create({ data: { partnerId: partnerUser.partnerId!, name: `Baseline test ${RUN}` } });
    await prisma.group.update({ where: { id: target.id }, data: { programmeId: programme.id } });

    const partner = await signIn("PARTNER_OFFICER");
    const read = await request(app).get(`/api/v1/groups/${target.id}/needs-assessments`).set("Cookie", partner).expect(200);
    const baseline = read.body.data[0];
    expect(baseline.totalMembers).toBe(20);
    expect(baseline.answers.leadership).toBeUndefined();
    expect(baseline.answers.signOff).toBeUndefined();
    expect(JSON.stringify(baseline.answers)).not.toMatch(/A private concern/);
    expect(baseline.fieldOfficer).toBeNull();
    // The field team's comments and the quality checks are not for partners.
    expect(baseline.answers.observations).toBeUndefined();
    expect(JSON.stringify(baseline)).not.toMatch(/Regular; "active" meetings/);
    expect(baseline.qualityFlags).toEqual([]);
  });
});
