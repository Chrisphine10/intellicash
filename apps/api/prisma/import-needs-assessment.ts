import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { PrismaClient } from "@prisma/client";
import { prisma as defaultClient } from "../src/lib/prisma";
import { normalisePhone } from "../src/lib/phone";
import { appendAuditEvent } from "../src/services/audit-service";
import { ensureAssessmentTemplate } from "../src/services/assessment-template-bootstrap";
import { currentSnapshot, submitVisitAssessment } from "../src/services/visit-assessment-service";
import {
  MAX_GPS_PRECISION_M,
  NEEDS_ASSESSMENT_SOURCE,
  countyFromGps,
  parseKoboCsv,
  parseSubmission,
  scorecardAnswers,
  type ParsedNeedsAssessment
} from "../src/domain/needs-assessment";

/**
 * Imports the Kobo needs assessment (IWL-VSLA-01) as each group's baseline.
 *
 *   npx tsx prisma/import-needs-assessment.ts <export.csv>            dry run
 *   npx tsx prisma/import-needs-assessment.ts <export.csv> --commit   apply
 *   bash prisma/run-with-service-env.sh prisma/import-needs-assessment.ts --csv=<path> [--commit]   on the server
 *
 * Per submission, keyed on its Kobo `_uuid` so a re-run is a no-op:
 * - an INITIAL visit on the visit date, with the GroupNeedsAssessment profile;
 * - a partial scorecard v1 baseline from the answers that mean the same thing
 *   (`SCORECARD_MAPPING`), scored by the same service the phone uses;
 * - the group's county/sub-county/ward corrected where the form and its own
 *   GPS agree against the register, missing location and GPS filled in, and
 *   the chairperson offered as contact when the group has none.
 *
 * Which group each submission belongs to is NOT guessed here: it is read from
 * `data/needs-assessment-2026-05-matches.json`, which was reviewed by hand.
 * The CSV holds people's names and phone numbers — keep it out of the repo and
 * out of CI, and delete it from the server once imported. The report printed
 * here names groups by code only.
 */

type MatchFile = {
  submissions: Record<string, { index: number; code?: string; rejected?: string; why?: string }>;
};

export interface ImportReport {
  imported: string[];
  alreadyImported: string[];
  scorecardsCompleted: string[];
  rejected: string[];
  unmatched: string[];
  missingGroups: string[];
  locationCorrections: string[];
  locationConflicts: string[];
  filled: string[];
  flags: string[];
}

const plausible = (parsed: ParsedNeedsAssessment) =>
  !!parsed.gps && !parsed.gpsProblem && (parsed.gps.precisionM === null || parsed.gps.precisionM <= MAX_GPS_PRECISION_M);

export async function importNeedsAssessment(options: {
  csvText: string;
  matches: MatchFile;
  commit?: boolean;
  client?: PrismaClient;
  actorUserId?: string | null;
}): Promise<ImportReport> {
  const client = options.client ?? defaultClient;
  const commit = options.commit ?? false;
  const report: ImportReport = {
    imported: [],
    alreadyImported: [],
    scorecardsCompleted: [],
    rejected: [],
    unmatched: [],
    missingGroups: [],
    locationCorrections: [],
    locationConflicts: [],
    filled: [],
    flags: []
  };

  if (commit) await ensureAssessmentTemplate();
  const scorecard = commit ? await currentSnapshot() : null;
  if (commit && !scorecard) throw new Error("No published scorecard to record the baseline against.");
  const questionKeys = scorecard
    ? scorecard.snapshot.sections.flatMap((section) => section.questions.map((question) => question.key))
    : [];

  for (const row of parseKoboCsv(options.csvText)) {
    const parsed = parseSubmission(row);
    const label = `#${row["_index"] ?? "?"}`;
    const match = options.matches.submissions[parsed.sourceReference];
    if (!match) {
      report.unmatched.push(`${label} (${parsed.sourceReference}) has no entry in the match file`);
      continue;
    }
    if (match.rejected) {
      report.rejected.push(`${label}: ${match.rejected}`);
      continue;
    }
    const group = await client.group.findUnique({ where: { code: match.code! } });
    if (!group) {
      report.missingGroups.push(`${label} -> ${match.code} does not exist`);
      continue;
    }
    const tag = `${label} ${group.code}`;
    for (const flag of parsed.qualityFlags) report.flags.push(`${tag}: ${flag}`);

    const existing = await client.groupNeedsAssessment.findUnique({
      where: { sourceReference: parsed.sourceReference },
      select: { visitId: true, visit: { select: { assessment: { select: { id: true } } } } }
    });
    if (existing) {
      report.alreadyImported.push(tag);
      // A run that stopped between the profile and the scorecard finishes here.
      if (commit && !existing.visit.assessment) {
        await submitVisitAssessment({
          visitId: existing.visitId,
          templateSnapshotId: scorecard!.snapshotId,
          answers: scorecardAnswers(row, questionKeys),
          actorUserId: options.actorUserId
        });
        report.scorecardsCompleted.push(tag);
      }
      continue;
    }
    if (!parsed.assessedOn) {
      report.unmatched.push(`${tag}: no readable visit date`);
      continue;
    }

    // --- where the group is ------------------------------------------------
    const groupData: Record<string, unknown> = {};
    const located = plausible(parsed);
    const formCounty = parsed.county;
    if (formCounty && group.county !== formCounty) {
      if (located && countyFromGps(parsed.gps!) === formCounty) {
        Object.assign(groupData, { county: formCounty, subCounty: parsed.subCounty, location: parsed.ward });
        report.locationCorrections.push(
          `${tag}: ${group.county}/${group.subCounty ?? "-"}/${group.location ?? "-"} -> ${formCounty}/${parsed.subCounty ?? "-"}/${parsed.ward ?? "-"} (form and GPS agree)`
        );
      } else {
        report.locationConflicts.push(
          `${tag}: register says ${group.county}, form says ${formCounty} — ${located ? "GPS disagrees with the form" : "no usable GPS"}; not changed`
        );
      }
    } else {
      if (!group.subCounty && parsed.subCounty) groupData.subCounty = parsed.subCounty;
      else if (group.subCounty && parsed.subCounty && group.subCounty.toLowerCase() !== parsed.subCounty.toLowerCase()) {
        report.locationConflicts.push(`${tag}: sub-county ${group.subCounty} vs form ${parsed.subCounty}; not changed`);
      }
      if (!group.location && parsed.ward) groupData.location = parsed.ward;
    }
    if (located && group.gpsLatitude === null && group.gpsLongitude === null) {
      groupData.gpsLatitude = parsed.gps!.latitude;
      groupData.gpsLongitude = parsed.gps!.longitude;
    }
    if (!group.contactPersonName && parsed.chairperson.name) groupData.contactPersonName = parsed.chairperson.name;
    if (!group.contactPhone && parsed.chairperson.phone) {
      const phone = normalisePhone(parsed.chairperson.phone);
      if (phone) groupData.contactPhone = phone;
    }
    const filledKeys = Object.keys(groupData).filter((key) => !["county", "subCounty", "location"].includes(key) || !("county" in groupData));
    if (filledKeys.length) report.filled.push(`${tag}: ${filledKeys.join(", ")}`);

    if (!commit) {
      report.imported.push(`${tag} (dry run)`);
      continue;
    }

    // --- the visit and the profile ----------------------------------------
    const agent = parsed.fieldOfficer
      ? await client.villageAgent.findFirst({ where: { name: parsed.fieldOfficer }, select: { id: true } })
      : null;
    const visit = await client.$transaction(async (tx) => {
      const created = await tx.groupVisit.create({
        data: {
          groupId: group.id,
          clientRequestId: `kobo-${parsed.sourceReference}`,
          villageAgentId: agent?.id ?? null,
          visitType: "INITIAL",
          status: "SUBMITTED",
          startedAt: parsed.assessedOn!,
          completedAt: parsed.assessedOn!,
          submittedAt: parsed.assessedOn!,
          // The form's own reading. It is also what places the group when the
          // group had no location, so no distance from the group is claimed.
          ...(located
            ? {
                deviceLatitude: parsed.gps!.latitude,
                deviceLongitude: parsed.gps!.longitude,
                locationAccuracyM: parsed.gps!.precisionM,
                locationOutcome: "NO_GROUP_LOCATION",
                locationNote: "GPS from the Kobo needs-assessment form."
              }
            : { locationOutcome: "NO_DEVICE_FIX", locationNote: parsed.gpsProblem ?? "GPS too coarse to use." }),
          withinGeofence: false,
          notes: [
            "Baseline needs assessment, imported from the Kobo form IWL-VSLA-01.",
            parsed.fieldOfficer ? `Field officer: ${parsed.fieldOfficer}.` : "",
            parsed.enumerator ? `Enumerator: ${parsed.enumerator}.` : "",
            "Figures are as the group reported them on the day, not verified."
          ]
            .filter(Boolean)
            .join(" ")
        },
        select: { id: true }
      });
      await tx.groupNeedsAssessment.create({
        data: {
          groupId: group.id,
          visitId: created.id,
          sourceSystem: NEEDS_ASSESSMENT_SOURCE,
          sourceReference: parsed.sourceReference,
          formVersion: parsed.formVersion,
          assessedOn: parsed.assessedOn!,
          fieldOfficer: parsed.fieldOfficer,
          enumerator: parsed.enumerator,
          ...parsed.fields,
          gpsLatitude: parsed.gps?.latitude ?? null,
          gpsLongitude: parsed.gps?.longitude ?? null,
          gpsPrecisionM: parsed.gps?.precisionM ?? null,
          answersJson: JSON.stringify(parsed.answers),
          qualityFlagsJson: JSON.stringify(parsed.qualityFlags)
        }
      });
      if (Object.keys(groupData).length) {
        await tx.group.update({ where: { id: group.id }, data: groupData });
      }
      return created;
    });

    if ("county" in groupData) {
      await appendAuditEvent({
        actorUserId: options.actorUserId ?? null,
        entityType: "GROUP",
        entityId: group.id,
        type: "GROUP_LOCATION_CORRECTED",
        payload: {
          before: { county: group.county, subCounty: group.subCounty, location: group.location },
          after: { county: groupData.county, subCounty: groupData.subCounty, location: groupData.location },
          evidence: { source: NEEDS_ASSESSMENT_SOURCE, sourceReference: parsed.sourceReference, gps: parsed.gps }
        }
      });
    }
    await appendAuditEvent({
      actorUserId: options.actorUserId ?? null,
      entityType: "GROUP",
      entityId: group.id,
      type: "NEEDS_ASSESSMENT_IMPORTED",
      payload: {
        visitId: visit.id,
        sourceSystem: NEEDS_ASSESSMENT_SOURCE,
        sourceReference: parsed.sourceReference,
        assessedOn: parsed.assessedOn,
        qualityFlags: parsed.qualityFlags,
        groupFieldsSet: Object.keys(groupData)
      }
    });

    // The partial scorecard, through the same scoring the phone's visits use.
    await submitVisitAssessment({
      visitId: visit.id,
      templateSnapshotId: scorecard!.snapshotId,
      answers: scorecardAnswers(row, questionKeys),
      actorUserId: options.actorUserId
    });
    report.imported.push(tag);
  }

  return report;
}

const isDirectRun = process.argv[1]?.replace(/\\/g, "/").endsWith("import-needs-assessment.ts") ?? false;

if (isDirectRun) {
  // `--csv=<path>` for the server's run-with-service-env.sh, which passes on
  // only --flags; a bare path for a local run.
  const csvPath =
    process.argv.find((arg) => arg.startsWith("--csv="))?.slice("--csv=".length) ??
    process.argv.slice(2).find((arg) => !arg.startsWith("--"));
  const commit = process.argv.includes("--commit");
  if (!csvPath) {
    console.error("Usage: npx tsx prisma/import-needs-assessment.ts <export.csv>|--csv=<path> [--commit]");
    process.exit(1);
  }
  const here = path.dirname(fileURLToPath(import.meta.url));
  const matches = JSON.parse(
    fs.readFileSync(path.resolve(here, "data/needs-assessment-2026-05-matches.json"), "utf8")
  ) as MatchFile;
  console.log(`Database: ${process.env.DATABASE_URL ?? "(unset)"}`);
  console.log(commit ? "Mode: COMMIT" : "Mode: dry run (pass --commit to apply)");
  importNeedsAssessment({ csvText: fs.readFileSync(csvPath, "utf8"), matches, commit })
    .then((report) => {
      for (const [key, lines] of Object.entries(report)) {
        console.log(`\n${key} (${lines.length})`);
        for (const line of lines) console.log(`  ${line}`);
      }
      if (report.unmatched.length || report.missingGroups.length) process.exitCode = 2;
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    })
    .finally(() => defaultClient.$disconnect());
}
