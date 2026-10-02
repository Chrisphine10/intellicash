import { Prisma, PrismaClient } from "@prisma/client";
import { hashPayload } from "../src/lib/crypto";
import { prisma as defaultClient } from "../src/lib/prisma";

/**
 * Folds a duplicate group into the group that is actually in use.
 *
 * The duplicates found on production (2 Oct 2026) are all one shape: a group
 * imported from the FLOURISH register, holding the field team's visits, action
 * items and enterprise profile but no money, and the same group signed up
 * again from a phone, holding the members, meetings, ledger and loans. The
 * phone's book is linked to the second group's id, so that group KEEPS; the
 * register's record is FOLDED into it and then removed.
 *
 * Safety:
 * - Pairs are listed explicitly below. Nothing is matched by name here.
 * - A folded group must hold no money or member records at all. If it does,
 *   that pair is refused and nothing in it changes.
 * - Each pair is one transaction, with a GROUP_MERGED audit event carrying a
 *   full snapshot of the folded group before it went.
 * - Dry run unless `--commit` is passed. Run the server backup first.
 * - Re-running is a no-op: a pair whose folded code now sits on the keeper is
 *   reported as already merged.
 */

export interface MergePair {
  /** The group in use (members, ledger, the phone's link). */
  keep: string;
  /** The empty duplicate whose history moves across, then is removed. */
  fold: string;
}

/** Production pairs, 2 Oct 2026: phone sign-up (keep) ← FLOURISH register (fold). */
export const PRODUCTION_PAIRS: MergePair[] = [
  { keep: "IWL-REG-9CUFIV", fold: "IWL-KRG-0006" }, // Bethsaida Women
  { keep: "IWL-REG-I99AAO", fold: "IWL-EMB-0005" }, // Ngongi Wendani
  { keep: "IWL-REG-GUSK9F", fold: "IWL-KRG-0013" }, // (Karumandi) Icon Youth
  { keep: "IWL-REG-PMWG40", fold: "IWL-KRG-0009" }, // Kirimunge Business Community
  { keep: "IWL-REG-JK3KNH", fold: "IWL-KRG-0004" }, // Mutira Unique
  { keep: "IWL-REG-USRVMD", fold: "IWL-KRG-0001" } // Tupande Joy Farmers
];

type Tx = Prisma.TransactionClient;

export interface PairOutcome {
  pair: MergePair;
  status: "MERGED" | "WOULD_MERGE" | "ALREADY_MERGED" | "REFUSED" | "NOT_FOUND";
  reason?: string;
  moved?: Record<string, number>;
  fieldsTaken?: string[];
}

/** Records a group must not hold to be folded away: money and people. */
async function heldRecords(tx: Tx, groupId: string) {
  const where = { groupId };
  const counts = {
    members: await tx.member.count({ where }),
    meetings: await tx.meeting.count({ where }),
    ledgerEntries: await tx.ledgerEntry.count({ where }),
    loans: await tx.loan.count({ where }),
    payments: await tx.groupPayment.count({ where }),
    settlements: await tx.settlement.count({ where }),
    settlementDestinations: await tx.settlementDestination.count({ where }),
    welfareExpenses: await tx.welfareExpense.count({ where }),
    votes: await tx.vote.count({ where }),
    polls: await tx.poll.count({ where }),
    storeCreditRequests: await tx.storeCreditRequest.count({ where }),
    externalLoanApplications: await tx.externalLoanApplication.count({ where }),
    offlineDevices: await tx.offlineDevice.count({ where }),
    memberRoleAssignments: await tx.memberRoleAssignment.count({ where }),
    fundsWithMoney: await tx.fundAccount.count({ where: { groupId, balanceCents: { not: 0 } } })
  };
  return Object.fromEntries(Object.entries(counts).filter(([, value]) => value > 0));
}

const isBlank = (value: unknown) =>
  value === null || value === undefined || (typeof value === "string" && (value.trim() === "" || value === "Not set"));

async function mergePair(tx: Tx, pair: MergePair, commit: boolean, actorUserId: string | null): Promise<PairOutcome> {
  // A finished merge leaves the keeper carrying the folded code, so look for
  // the audit record first: that is what makes a re-run a no-op.
  const done = await tx.auditEvent.findFirst({
    where: { type: "GROUP_MERGED", payloadJson: { contains: `"foldCode":"${pair.fold}"` } },
    select: { id: true }
  });
  if (done) return { pair, status: "ALREADY_MERGED" };

  const keep = await tx.group.findUnique({ where: { code: pair.keep } });
  const fold = await tx.group.findUnique({ where: { code: pair.fold } });
  if (!fold) return { pair, status: "NOT_FOUND", reason: `No group with code ${pair.fold}.` };
  if (!keep) return { pair, status: "NOT_FOUND", reason: `No group with code ${pair.keep}.` };
  if (keep.id === fold.id) return { pair, status: "REFUSED", reason: "Keep and fold are the same group." };

  const held = await heldRecords(tx, fold.id);
  if (Object.keys(held).length > 0) {
    return {
      pair,
      status: "REFUSED",
      reason: `${pair.fold} holds records that must not be folded away: ${JSON.stringify(held)}. Merge by hand.`
    };
  }

  // A standing document of the same type on both sides would be lost on delete.
  const keepDocTypes = new Set(
    (await tx.groupDocument.findMany({ where: { groupId: keep.id }, select: { documentType: true } })).map((d) => d.documentType)
  );
  const clashingDocs = (
    await tx.groupDocument.findMany({ where: { groupId: fold.id }, select: { documentType: true } })
  ).filter((d) => keepDocTypes.has(d.documentType));
  if (clashingDocs.length > 0) {
    return {
      pair,
      status: "REFUSED",
      reason: `Both groups hold a ${clashingDocs.map((d) => d.documentType).join(", ")} document. Resolve by hand.`
    };
  }

  // What the keeper takes from the register record: its source key (so the
  // FLOURISH re-import and validator follow it), and any profile field the
  // keeper left empty. The register's location wins over a phone sign-up's.
  const fieldsTaken: string[] = [];
  const data: Prisma.GroupUpdateInput = {};
  if (fold.sourceSystem) {
    data.sourceSystem = fold.sourceSystem;
    data.sourceReference = fold.sourceReference;
    fieldsTaken.push("sourceSystem", "sourceReference");
  }
  if (!isBlank(fold.county) && (isBlank(keep.subCounty) || isBlank(keep.county))) {
    data.county = fold.county;
    data.subCounty = fold.subCounty;
    data.location = fold.location ?? keep.location;
    fieldsTaken.push("county", "subCounty", "location");
  }
  for (const field of [
    "contactPersonName",
    "contactPhone",
    "composition",
    "objective",
    "onboardingFeedback",
    "meetingDay",
    "meetingFrequency",
    "meetingDays",
    "meetingTime",
    "gpsLatitude",
    "gpsLongitude"
  ] as const) {
    if (isBlank(keep[field]) && !isBlank(fold[field])) {
      (data as Record<string, unknown>)[field] = fold[field];
      fieldsTaken.push(field);
    }
  }
  if (keep.phase === "MOBILISATION" && fold.phase !== "MOBILISATION") {
    data.phase = fold.phase;
    fieldsTaken.push("phase");
  }
  if (!keep.villageAgentId && fold.villageAgentId) {
    data.villageAgent = { connect: { id: fold.villageAgentId } };
    fieldsTaken.push("villageAgentId");
  }

  // Programme: only when the keeper has none. A phone group already placed in
  // a programme keeps it (Icon Youth's register entry sat in the demo one).
  const keepProgrammes = await tx.programmeGroup.findMany({ where: { groupId: keep.id } });
  const foldProgrammes = await tx.programmeGroup.findMany({ where: { groupId: fold.id } });
  const takeProgramme = keepProgrammes.length === 0 && !keep.programmeId;
  if (takeProgramme && fold.programmeId) {
    data.programme = { connect: { id: fold.programmeId } };
    fieldsTaken.push("programmeId");
  }

  const keepAgents = new Set(
    (await tx.groupAgent.findMany({ where: { groupId: keep.id }, select: { villageAgentId: true } })).map((a) => a.villageAgentId)
  );
  const foldAgents = await tx.groupAgent.findMany({ where: { groupId: fold.id } });
  const keepMembershipUsers = new Set(
    (await tx.userMembership.findMany({ where: { groupId: keep.id }, select: { userId: true } })).map((m) => m.userId)
  );
  const keepJoinUsers = new Set(
    (await tx.groupJoinRequest.findMany({ where: { groupId: keep.id }, select: { userId: true } })).map((r) => r.userId)
  );

  const moved: Record<string, number> = {
    visits: await tx.groupVisit.count({ where: { groupId: fold.id } }),
    actionItems: await tx.visitActionItem.count({ where: { groupId: fold.id } }),
    enterprises: await tx.groupEnterprise.count({ where: { groupId: fold.id } }),
    documents: await tx.groupDocument.count({ where: { groupId: fold.id } }),
    logins: await tx.user.count({ where: { groupId: fold.id } }),
    cbtLinks: foldAgents.filter((a) => !keepAgents.has(a.villageAgentId)).length,
    programmeLinks: takeProgramme ? foldProgrammes.length : 0
  };

  if (!commit) return { pair, status: "WOULD_MERGE", moved, fieldsTaken };

  const snapshot = {
    foldCode: fold.code,
    keepCode: keep.code,
    fold,
    foldProgrammes,
    foldAgents,
    keepSourceSystem: keep.sourceSystem,
    keepSourceReference: keep.sourceReference,
    keepCodeBefore: keep.code
  };

  const into = { groupId: keep.id };
  const from = { where: { groupId: fold.id }, data: into };
  await tx.groupVisit.updateMany(from);
  await tx.visitActionItem.updateMany(from);
  await tx.groupEnterprise.updateMany(from);
  await tx.groupEnterpriseVersion.updateMany(from);
  await tx.groupEnterpriseSupportNeed.updateMany(from);
  await tx.attachment.updateMany(from);
  await tx.groupDocument.updateMany(from);
  await tx.smsBroadcastRecipient.updateMany(from);
  await tx.creditScore.updateMany(from);
  // The register's group login(s) now open the book that is in use.
  await tx.user.updateMany(from);

  for (const membership of await tx.userMembership.findMany({ where: { groupId: fold.id } })) {
    if (!keepMembershipUsers.has(membership.userId)) {
      await tx.userMembership.update({ where: { id: membership.id }, data: into });
    }
  }
  for (const request of await tx.groupJoinRequest.findMany({ where: { groupId: fold.id } })) {
    if (!keepJoinUsers.has(request.userId)) {
      await tx.groupJoinRequest.update({ where: { id: request.id }, data: into });
    }
  }
  for (const agent of foldAgents) {
    if (!keepAgents.has(agent.villageAgentId)) {
      await tx.groupAgent.create({ data: { groupId: keep.id, villageAgentId: agent.villageAgentId, isLead: agent.isLead } });
    }
  }
  if (takeProgramme) {
    for (const link of foldProgrammes) {
      await tx.programmeGroup.create({ data: { groupId: keep.id, programmeId: link.programmeId, role: link.role } });
    }
  }

  // What is left on the folded group is empty scaffolding (an unused cycle,
  // zero funds, default settings, the moved-from links); the cascade takes it.
  await tx.group.delete({ where: { id: fold.id } });
  // Its register code is the one the programme knows the group by.
  await tx.group.update({ where: { id: keep.id }, data: { ...data, code: fold.code } });

  // Inside the transaction (appendAuditEvent uses the global client), hashed
  // the same way, so a merge and its record land together or not at all.
  const payload = { ...snapshot, moved, fieldsTaken };
  await tx.auditEvent.create({
    data: {
      actorUserId,
      entityType: "GROUP",
      entityId: keep.id,
      type: "GROUP_MERGED",
      payloadJson: JSON.stringify(payload),
      hash: hashPayload(payload)
    }
  });

  return { pair, status: "MERGED", moved, fieldsTaken };
}

export async function mergeDuplicateGroups(options: {
  pairs?: MergePair[];
  commit?: boolean;
  client?: PrismaClient;
  actorUserId?: string | null;
}) {
  const client = options.client ?? defaultClient;
  const pairs = options.pairs ?? PRODUCTION_PAIRS;
  const outcomes: PairOutcome[] = [];
  for (const pair of pairs) {
    outcomes.push(
      await client.$transaction((tx) => mergePair(tx, pair, options.commit ?? false, options.actorUserId ?? null), {
        timeout: 60_000
      })
    );
  }
  return outcomes;
}

const isDirectRun = process.argv[1]?.replace(/\\/g, "/").endsWith("merge-duplicate-groups.ts") ?? false;

if (isDirectRun) {
  const commit = process.argv.includes("--commit");
  console.log(`Database: ${process.env.DATABASE_URL ?? "(unset)"}`);
  console.log(commit ? "Mode: COMMIT" : "Mode: dry run (pass --commit to apply)");
  mergeDuplicateGroups({ commit })
    .then((outcomes) => {
      for (const outcome of outcomes) {
        console.log(
          `${outcome.pair.fold} -> ${outcome.pair.keep}: ${outcome.status}` +
            (outcome.reason ? ` — ${outcome.reason}` : "") +
            (outcome.moved ? ` moved=${JSON.stringify(outcome.moved)}` : "") +
            (outcome.fieldsTaken?.length ? ` took=${outcome.fieldsTaken.join(",")}` : "")
        );
      }
      if (outcomes.some((o) => o.status === "REFUSED" || o.status === "NOT_FOUND")) process.exitCode = 2;
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    })
    .finally(() => defaultClient.$disconnect());
}
