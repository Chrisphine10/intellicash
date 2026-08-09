import { createHash } from "node:crypto";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { appendAuditEvent } from "./audit-service";
import {
  VISIT_ASSESSMENT_CONTRACT_VERSION,
  buildAssessmentSnapshot,
  canonicalizeSnapshot,
  scoreAssessment,
  validateAssessmentTemplate,
  type AssessmentAnswerInput,
  type AssessmentBandSnapshot,
  type AssessmentTemplateDraft,
  type AssessmentTemplateSnapshot
} from "../domain/visit-assessment-contract";

/**
 * Persistence and lifecycle for assessment templates, and the scoring of a
 * visit's answers against a frozen snapshot.
 *
 * The rules live in `domain/visit-assessment-contract.ts` and nothing here
 * re-implements them — this module's job is to decide *which* snapshot applies,
 * to keep a published template immutable, and to store enough alongside each
 * result that it can be re-derived.
 */

export const TEMPLATE_STATUS = {
  draft: "DRAFT",
  published: "PUBLISHED",
  archived: "ARCHIVED"
} as const;

/** The scorecard IWL field agents use. Stable across every version of it. */
export const DEFAULT_TEMPLATE_FAMILY = "vsla_field_assessment";

/**
 * sha256 over the canonical form.
 *
 * The phone sends the checksum of the snapshot it rendered. A mismatch means it
 * scored against a copy that has since been superseded — the server re-scores
 * regardless, and the mismatch is recorded rather than rejected, because a
 * completed field visit must never be lost to a caching disagreement.
 */
export function checksumSnapshot(snapshot: AssessmentTemplateSnapshot) {
  return createHash("sha256").update(canonicalizeSnapshot(snapshot)).digest("hex");
}

function parseBands(bandsJson: string): AssessmentBandSnapshot[] {
  try {
    const parsed = JSON.parse(bandsJson);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

type TemplateWithContent = {
  id: string;
  familyKey: string;
  version: number;
  status: string;
  title: string;
  description: string | null;
  bandsJson: string;
  sections: {
    key: string;
    title: string;
    description: string | null;
    position: number;
    questions: {
      key: string;
      prompt: string;
      guidance: string | null;
      weight: number;
      position: number;
      requiresNote: boolean;
    }[];
  }[];
};

const templateInclude = {
  sections: {
    orderBy: { position: "asc" },
    include: { questions: { orderBy: { position: "asc" } } }
  }
} as const;

/** The live rows, in the shape the contract validates and snapshots. */
export function draftFromTemplate(template: TemplateWithContent): AssessmentTemplateDraft {
  return {
    title: template.title,
    ...(template.description ? { description: template.description } : {}),
    sections: template.sections.map((section) => ({
      key: section.key,
      title: section.title,
      ...(section.description ? { description: section.description } : {}),
      position: section.position,
      questions: section.questions.map((question) => ({
        key: question.key,
        prompt: question.prompt,
        weight: question.weight,
        ...(question.guidance ? { guidance: question.guidance } : {}),
        position: question.position,
        ...(question.requiresNote ? { requiresNote: true } : {})
      }))
    })),
    bands: parseBands(template.bandsJson)
  };
}

/**
 * Validates a draft without publishing it — what the authoring screen calls on
 * every edit so the author sees the live `maxPoints` and any band gap before
 * committing to a version.
 */
export async function previewTemplate(templateId: string) {
  const template = await loadTemplate(templateId);
  const draft = draftFromTemplate(template);
  const validation = validateAssessmentTemplate(draft);

  return {
    templateId: template.id,
    version: template.version,
    status: template.status,
    validation,
    // Shown while authoring even when invalid, so the author can watch the
    // total move as they add questions.
    computedMaxPoints: draft.sections
      .flatMap((section) => section.questions)
      .reduce((sum, question) => sum + (Number.isFinite(question.weight) ? question.weight : 0), 0)
  };
}

async function loadTemplate(templateId: string): Promise<TemplateWithContent> {
  const template = await prisma.assessmentTemplate.findUnique({
    where: { id: templateId },
    include: templateInclude
  });
  if (!template) {
    throw new ApiHttpError(404, "TEMPLATE_NOT_FOUND", "That assessment template does not exist.");
  }
  return template as TemplateWithContent;
}

/**
 * Publishes a template: validates it, freezes a snapshot, and closes it to
 * further editing.
 *
 * Publishing is the moment the rules stop being editable, so validation is
 * strict here and permissive while drafting. Only one version of a family is
 * PUBLISHED at a time — publishing v4 archives v3, so "the current scorecard"
 * is never ambiguous.
 */
export async function publishTemplate(options: {
  templateId: string;
  actorUserId?: string | null;
}) {
  const template = await loadTemplate(options.templateId);

  if (template.status === TEMPLATE_STATUS.published) {
    throw new ApiHttpError(
      409,
      "TEMPLATE_ALREADY_PUBLISHED",
      "That version is already published. Clone it to make changes."
    );
  }
  if (template.status === TEMPLATE_STATUS.archived) {
    throw new ApiHttpError(
      409,
      "TEMPLATE_ARCHIVED",
      "That version is archived. Clone it to make changes."
    );
  }

  const draft = draftFromTemplate(template);
  const validation = validateAssessmentTemplate(draft);
  if (!validation.ok) {
    throw new ApiHttpError(
      400,
      "TEMPLATE_INVALID",
      "This template cannot be published yet.",
      validation.issues
    );
  }

  const snapshot = buildAssessmentSnapshot(draft, {
    templateId: template.id,
    version: template.version,
    maxPoints: validation.maxPoints
  });
  const checksum = checksumSnapshot(snapshot);

  const published = await prisma.$transaction(async (tx) => {
    // Demote the previous published version first: two PUBLISHED rows in one
    // family would make "the current template" a coin toss.
    await tx.assessmentTemplate.updateMany({
      where: {
        familyKey: template.familyKey,
        status: TEMPLATE_STATUS.published,
        id: { not: template.id }
      },
      data: { status: TEMPLATE_STATUS.archived }
    });

    await tx.assessmentTemplateSnapshot.create({
      data: {
        templateId: template.id,
        version: template.version,
        snapshotJson: JSON.stringify(snapshot),
        checksum,
        maxPoints: validation.maxPoints,
        scoringContractVersion: VISIT_ASSESSMENT_CONTRACT_VERSION
      }
    });

    return tx.assessmentTemplate.update({
      where: { id: template.id },
      data: {
        status: TEMPLATE_STATUS.published,
        maxPoints: validation.maxPoints,
        publishedAt: new Date(),
        publishedByUserId: options.actorUserId ?? null
      }
    });
  });

  // After the commit, not inside it: on SQLite an audit write inside the
  // interactive transaction waits on the write lock that transaction already
  // holds, and times out. That exact bug took down the amend endpoint.
  await appendAuditEvent({
    actorUserId: options.actorUserId ?? null,
    entityType: "ASSESSMENT_TEMPLATE",
    entityId: template.id,
    type: "ASSESSMENT_TEMPLATE_PUBLISHED",
    payload: {
      familyKey: template.familyKey,
      version: template.version,
      maxPoints: validation.maxPoints,
      checksum,
      scoringContractVersion: VISIT_ASSESSMENT_CONTRACT_VERSION
    }
  });

  return { template: published, snapshot, checksum };
}

/**
 * Copies a template into a new DRAFT at the next version.
 *
 * This is the only way to change a published scorecard. The old version stays
 * exactly as it was, which is what keeps every assessment already scored under
 * it reproducible.
 */
export async function cloneTemplate(options: {
  templateId: string;
  actorUserId?: string | null;
}) {
  const source = await loadTemplate(options.templateId);

  const existingDraft = await prisma.assessmentTemplate.findFirst({
    where: { familyKey: source.familyKey, status: TEMPLATE_STATUS.draft }
  });
  if (existingDraft) {
    throw new ApiHttpError(
      409,
      "TEMPLATE_DRAFT_EXISTS",
      "There is already an unpublished draft for this scorecard. Publish or discard it first.",
      { draftId: existingDraft.id, version: existingDraft.version }
    );
  }

  const latest = await prisma.assessmentTemplate.findFirst({
    where: { familyKey: source.familyKey },
    orderBy: { version: "desc" },
    select: { version: true }
  });
  const nextVersion = (latest?.version ?? source.version) + 1;

  return prisma.assessmentTemplate.create({
    data: {
      familyKey: source.familyKey,
      version: nextVersion,
      status: TEMPLATE_STATUS.draft,
      title: source.title,
      description: source.description,
      bandsJson: source.bandsJson,
      clonedFromId: source.id,
      createdByUserId: options.actorUserId ?? null,
      sections: {
        create: source.sections.map((section) => ({
          key: section.key,
          title: section.title,
          description: section.description,
          position: section.position,
          questions: {
            create: section.questions.map((question) => ({
              key: question.key,
              prompt: question.prompt,
              guidance: question.guidance,
              weight: question.weight,
              position: question.position,
              requiresNote: question.requiresNote
            }))
          }
        }))
      }
    },
    include: templateInclude
  });
}

/**
 * The snapshot a phone should download and score against — the current
 * published version of a family.
 */
export async function currentSnapshot(familyKey = DEFAULT_TEMPLATE_FAMILY) {
  const template = await prisma.assessmentTemplate.findFirst({
    where: { familyKey, status: TEMPLATE_STATUS.published },
    orderBy: { version: "desc" },
    include: { snapshot: true }
  });

  if (!template?.snapshot) return null;

  return {
    templateId: template.id,
    version: template.version,
    checksum: template.snapshot.checksum,
    maxPoints: template.snapshot.maxPoints,
    scoringContractVersion: template.snapshot.scoringContractVersion,
    snapshot: JSON.parse(template.snapshot.snapshotJson) as AssessmentTemplateSnapshot
  };
}

export type SubmitAssessmentInput = {
  visitId: string;
  /**
   * Which snapshot the phone rendered. Optional: a phone that lost its cache
   * still gets scored, against whatever is current.
   */
  templateSnapshotId?: string | null;
  /** What the phone believes it rendered. Recorded, never trusted. */
  expectedChecksum?: string | null;
  answers: AssessmentAnswerInput[];
  actorUserId?: string | null;
};

/**
 * Scores a visit's answers and stores the result.
 *
 * Idempotent on the visit: one visit has at most one assessment, and a resent
 * payload re-scores and overwrites rather than creating a second. That matters
 * because the phone retries the whole visit document on every reconnect.
 *
 * The server's score is authoritative. The phone computes one locally so the
 * agent sees a band before sync, but it is provisional until this runs.
 */
export async function submitVisitAssessment(input: SubmitAssessmentInput) {
  const visit = await prisma.groupVisit.findUnique({
    where: { id: input.visitId },
    select: { id: true, groupId: true }
  });
  if (!visit) {
    throw new ApiHttpError(404, "VISIT_NOT_FOUND", "That visit does not exist.");
  }

  const resolved = await resolveSnapshot(input.templateSnapshotId);
  const score = scoreAssessment(resolved.snapshot, input.answers);

  const checksumMismatch =
    !!input.expectedChecksum && input.expectedChecksum !== resolved.checksum;

  const answersBySection = new Map(
    score.sections.flatMap((section) =>
      section.questions.map((question) => [question.questionKey, section.sectionKey])
    )
  );

  const assessment = await prisma.$transaction(async (tx) => {
    const saved = await tx.groupVisitAssessment.upsert({
      where: { visitId: visit.id },
      create: {
        visitId: visit.id,
        templateSnapshotId: resolved.snapshotId,
        templateId: resolved.templateId,
        templateVersion: resolved.version,
        scoringContractVersion: score.scoringContractVersion,
        earnedPoints: score.earnedPoints,
        applicablePoints: score.applicablePoints,
        maxPoints: score.maxPoints,
        scaledPoints: score.scaledPoints,
        percentage: score.percentage,
        bandKey: score.bandKey,
        bandLabel: score.bandLabel,
        complete: score.complete,
        breakdownJson: JSON.stringify(score)
      },
      update: {
        templateSnapshotId: resolved.snapshotId,
        templateId: resolved.templateId,
        templateVersion: resolved.version,
        scoringContractVersion: score.scoringContractVersion,
        earnedPoints: score.earnedPoints,
        applicablePoints: score.applicablePoints,
        maxPoints: score.maxPoints,
        scaledPoints: score.scaledPoints,
        percentage: score.percentage,
        bandKey: score.bandKey,
        bandLabel: score.bandLabel,
        complete: score.complete,
        breakdownJson: JSON.stringify(score)
      }
    });

    // Answers are replaced wholesale rather than diffed: a resubmission is the
    // authoritative statement of what the agent recorded, and a leftover answer
    // to a question since removed would score nothing but confuse every reader.
    await tx.groupVisitAnswer.deleteMany({ where: { assessmentId: saved.id } });

    if (input.answers.length) {
      // Deduplicated on the way in — the contract takes last-write-wins, and
      // the unique index would otherwise reject the whole batch.
      const deduped = new Map(input.answers.map((answer) => [answer.questionKey, answer]));
      await tx.groupVisitAnswer.createMany({
        data: [...deduped.values()].map((answer) => ({
          assessmentId: saved.id,
          sectionKey: answersBySection.get(answer.questionKey) ?? "",
          questionKey: answer.questionKey,
          choice: answer.choice,
          note: answer.note ?? null
        }))
      });
    }

    return saved;
  });

  await appendAuditEvent({
    actorUserId: input.actorUserId ?? null,
    entityType: "GROUP_VISIT",
    entityId: visit.id,
    type: "GROUP_VISIT_ASSESSED",
    payload: {
      groupId: visit.groupId,
      templateId: resolved.templateId,
      templateVersion: resolved.version,
      scoringContractVersion: score.scoringContractVersion,
      percentage: score.percentage,
      bandKey: score.bandKey,
      complete: score.complete,
      ...(checksumMismatch
        ? { checksumMismatch: true, phoneChecksum: input.expectedChecksum }
        : {})
    }
  });

  return { assessment, score, checksumMismatch, snapshotChecksum: resolved.checksum };
}

/**
 * Picks the snapshot to score against.
 *
 * A phone that names a snapshot gets that one, even if it has since been
 * superseded — the agent answered *those* questions, and re-pointing the
 * answers at a newer form would silently mis-score them.
 */
async function resolveSnapshot(templateSnapshotId?: string | null) {
  if (templateSnapshotId) {
    const row = await prisma.assessmentTemplateSnapshot.findUnique({
      where: { id: templateSnapshotId }
    });
    if (!row) {
      throw new ApiHttpError(
        404,
        "TEMPLATE_SNAPSHOT_NOT_FOUND",
        "The assessment form this visit used no longer exists."
      );
    }
    return {
      snapshotId: row.id,
      templateId: row.templateId,
      version: row.version,
      checksum: row.checksum,
      snapshot: JSON.parse(row.snapshotJson) as AssessmentTemplateSnapshot
    };
  }

  const current = await prisma.assessmentTemplate.findFirst({
    where: { status: TEMPLATE_STATUS.published },
    orderBy: { publishedAt: "desc" },
    include: { snapshot: true }
  });
  if (!current?.snapshot) {
    throw new ApiHttpError(
      409,
      "NO_PUBLISHED_TEMPLATE",
      "No assessment form has been published yet."
    );
  }

  return {
    snapshotId: current.snapshot.id,
    templateId: current.id,
    version: current.version,
    checksum: current.snapshot.checksum,
    snapshot: JSON.parse(current.snapshot.snapshotJson) as AssessmentTemplateSnapshot
  };
}

/**
 * A stored assessment, re-rendered from its own snapshot.
 *
 * The stored breakdown is returned as-is rather than recomputed: it is the
 * record of what was reported. `snapshot` accompanies it so a reader sees the
 * questions as they stood, not as they are now.
 */
export async function readVisitAssessment(visitId: string) {
  const assessment = await prisma.groupVisitAssessment.findUnique({
    where: { visitId },
    include: { snapshot: true, answers: true }
  });
  if (!assessment) return null;

  return {
    id: assessment.id,
    visitId: assessment.visitId,
    templateId: assessment.templateId,
    templateVersion: assessment.templateVersion,
    scoringContractVersion: assessment.scoringContractVersion,
    earnedPoints: assessment.earnedPoints,
    applicablePoints: assessment.applicablePoints,
    maxPoints: assessment.maxPoints,
    scaledPoints: assessment.scaledPoints,
    percentage: assessment.percentage,
    bandKey: assessment.bandKey,
    bandLabel: assessment.bandLabel,
    complete: assessment.complete,
    createdAt: assessment.createdAt,
    breakdown: JSON.parse(assessment.breakdownJson),
    snapshot: JSON.parse(assessment.snapshot.snapshotJson) as AssessmentTemplateSnapshot,
    answers: assessment.answers.map((answer) => ({
      sectionKey: answer.sectionKey,
      questionKey: answer.questionKey,
      choice: answer.choice,
      note: answer.note
    }))
  };
}
