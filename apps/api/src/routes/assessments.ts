import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import {
  ASSESSMENT_CHOICES,
  validateAssessmentTemplate
} from "../domain/visit-assessment-contract";
import {
  DEFAULT_TEMPLATE_FAMILY,
  TEMPLATE_STATUS,
  cloneTemplate,
  currentSnapshot,
  draftFromTemplate,
  previewTemplate,
  publishTemplate,
  readVisitAssessment,
  submitVisitAssessment
} from "../services/visit-assessment-service";

export const assessmentsRouter = Router();

/**
 * The assessment scorecard: authoring it, and filling it in during a visit.
 *
 * The split that matters is between the two audiences. IWL staff **author** the
 * form under `assessment-templates:write`; agents **answer** it under
 * `visits:write` and can no more edit a question than a candidate can edit an
 * exam paper. Reading the published form rides on `visits:read`, since
 * rendering the questions is part of both conducting and reviewing a visit.
 */

const templateBodySchema = z.object({
  familyKey: z
    .string()
    .regex(/^[a-z0-9][a-z0-9_]{0,62}$/, "Use lower-case letters, digits and underscores.")
    .optional(),
  title: z.string().min(1),
  description: z.string().optional(),
  sections: z
    .array(
      z.object({
        key: z.string().min(1),
        title: z.string().min(1),
        description: z.string().optional(),
        position: z.number().int().min(0),
        questions: z.array(
          z.object({
            key: z.string().min(1),
            prompt: z.string().min(1),
            guidance: z.string().optional(),
            weight: z.number().positive(),
            position: z.number().int().min(0),
            requiresNote: z.boolean().optional()
          })
        )
      })
    )
    .default([]),
  bands: z
    .array(
      z.object({
        key: z.string().min(1),
        label: z.string().min(1),
        minPoints: z.number().min(0),
        maxPoints: z.number().min(0),
        guidance: z.string().optional()
      })
    )
    .default([])
});

/**
 * Answers as the phone sends them.
 *
 * `choice` is a plain string rather than an enum on purpose: an unrecognised
 * value is scored as unanswered by the contract, which loses one question. A
 * Zod enum would reject the request and lose the entire visit.
 */
const submitAssessmentSchema = z.object({
  templateSnapshotId: z.string().optional(),
  expectedChecksum: z.string().optional(),
  answers: z
    .array(
      z.object({
        questionKey: z.string().min(1),
        choice: z.string().min(1),
        note: z.string().max(2000).optional()
      })
    )
    .max(500)
});

/** A submitted visit the caller is allowed to see, or a 404. */
async function loadVisitInScope(user: AuthenticatedUser | undefined, visitId: string) {
  const visit = await prisma.groupVisit.findFirst({
    where: { AND: [{ id: visitId }, { group: scopeGroupWhere(user) }] },
    select: { id: true, groupId: true }
  });
  if (!visit) {
    throw new ApiHttpError(404, "VISIT_NOT_FOUND", "Visit does not exist or is outside your access.");
  }
  return visit;
}

// ---------------------------------------------------------------------------
// Filling the form in
// ---------------------------------------------------------------------------

/**
 * The current published form, ready to render offline.
 *
 * The phone caches this by checksum and re-downloads only when the checksum
 * moves, which on a 2G connection is the difference between a usable app and an
 * unusable one.
 */
assessmentsRouter.get(
  "/assessment-templates/current",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const familyKey = (req.query.familyKey as string) || DEFAULT_TEMPLATE_FAMILY;
      const snapshot = await currentSnapshot(familyKey);
      if (!snapshot) {
        throw new ApiHttpError(
          404,
          "NO_PUBLISHED_TEMPLATE",
          "No assessment form has been published yet."
        );
      }
      ok(res, { ...snapshot, choices: ASSESSMENT_CHOICES });
    } catch (error) {
      next(error);
    }
  }
);

assessmentsRouter.put(
  "/visits/:visitId/assessment",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      const payload = submitAssessmentSchema.parse(req.body);

      const result = await submitVisitAssessment({
        visitId: visit.id,
        templateSnapshotId: payload.templateSnapshotId ?? null,
        expectedChecksum: payload.expectedChecksum ?? null,
        answers: payload.answers,
        actorUserId: req.user?.id
      });

      ok(res, {
        assessmentId: result.assessment.id,
        score: result.score,
        snapshotChecksum: result.snapshotChecksum,
        // Told plainly rather than hidden: the phone should refresh its cached
        // form, and its own provisional figure was computed against a stale one.
        checksumMismatch: result.checksumMismatch
      });
    } catch (error) {
      next(error);
    }
  }
);

assessmentsRouter.get(
  "/visits/:visitId/assessment",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      const assessment = await readVisitAssessment(visit.id);
      if (!assessment) {
        throw new ApiHttpError(
          404,
          "ASSESSMENT_NOT_FOUND",
          "No assessment has been recorded for this visit."
        );
      }
      ok(res, assessment);
    } catch (error) {
      next(error);
    }
  }
);

// ---------------------------------------------------------------------------
// Authoring the form
// ---------------------------------------------------------------------------

assessmentsRouter.get(
  "/assessment-templates",
  requireAuth("assessment-templates:write"),
  async (_req, res, next) => {
    try {
      const templates = await prisma.assessmentTemplate.findMany({
        orderBy: [{ familyKey: "asc" }, { version: "desc" }],
        include: {
          snapshot: { select: { checksum: true, createdAt: true } },
          _count: { select: { assessments: true, sections: true } }
        }
      });

      ok(
        res,
        templates.map((template) => ({
          id: template.id,
          familyKey: template.familyKey,
          version: template.version,
          status: template.status,
          title: template.title,
          description: template.description,
          maxPoints: template.maxPoints,
          publishedAt: template.publishedAt,
          checksum: template.snapshot?.checksum ?? null,
          sectionCount: template._count.sections,
          // A published version with assessments against it can never be
          // edited or deleted — this is what the UI greys the buttons on.
          assessmentCount: template._count.assessments
        }))
      );
    } catch (error) {
      next(error);
    }
  }
);

assessmentsRouter.get(
  "/assessment-templates/:templateId",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const template = await prisma.assessmentTemplate.findUnique({
        where: { id: req.params.templateId as string },
        include: {
          snapshot: true,
          sections: {
            orderBy: { position: "asc" },
            include: { questions: { orderBy: { position: "asc" } } }
          }
        }
      });
      if (!template) {
        throw new ApiHttpError(404, "TEMPLATE_NOT_FOUND", "That assessment template does not exist.");
      }

      // A published version is served from its FROZEN SNAPSHOT, never from the
      // live rows. They should be identical — the API refuses to edit a
      // published template — but the snapshot is what agents actually score
      // against, so it is what a reviewer must be shown. Reading the live rows
      // here would mean the admin console could display a form that differs
      // from every assessment made under it, which is precisely the failure
      // the snapshot exists to prevent.
      if (template.status !== TEMPLATE_STATUS.draft && template.snapshot) {
        const frozen = JSON.parse(template.snapshot.snapshotJson);
        ok(res, {
          id: template.id,
          familyKey: template.familyKey,
          version: template.version,
          status: template.status,
          maxPoints: template.snapshot.maxPoints,
          publishedAt: template.publishedAt,
          checksum: template.snapshot.checksum,
          title: frozen.title,
          description: frozen.description,
          sections: frozen.sections,
          bands: frozen.bands,
          // Already published, so there is nothing to validate. Reporting
          // issues against a locked version reads as "this is broken" when it
          // is simply finished.
          validation: { ok: true, maxPoints: template.snapshot.maxPoints }
        });
        return;
      }

      const draft = draftFromTemplate(template as never);
      ok(res, {
        id: template.id,
        familyKey: template.familyKey,
        version: template.version,
        status: template.status,
        maxPoints: template.maxPoints,
        publishedAt: template.publishedAt,
        ...draft,
        validation: validateAssessmentTemplate(draft)
      });
    } catch (error) {
      next(error);
    }
  }
);

/** Validation feedback while authoring, without committing anything. */
assessmentsRouter.get(
  "/assessment-templates/:templateId/preview",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      ok(res, await previewTemplate(req.params.templateId as string));
    } catch (error) {
      next(error);
    }
  }
);

assessmentsRouter.post(
  "/assessment-templates",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const payload = templateBodySchema.parse(req.body);
      const familyKey = payload.familyKey ?? DEFAULT_TEMPLATE_FAMILY;

      const existingDraft = await prisma.assessmentTemplate.findFirst({
        where: { familyKey, status: TEMPLATE_STATUS.draft }
      });
      if (existingDraft) {
        throw new ApiHttpError(
          409,
          "TEMPLATE_DRAFT_EXISTS",
          "There is already an unpublished draft for this scorecard.",
          { draftId: existingDraft.id }
        );
      }

      const latest = await prisma.assessmentTemplate.findFirst({
        where: { familyKey },
        orderBy: { version: "desc" },
        select: { version: true }
      });

      const template = await prisma.assessmentTemplate.create({
        data: {
          familyKey,
          version: (latest?.version ?? 0) + 1,
          status: TEMPLATE_STATUS.draft,
          title: payload.title,
          description: payload.description ?? null,
          bandsJson: JSON.stringify(payload.bands),
          createdByUserId: req.user?.id ?? null,
          sections: {
            create: payload.sections.map((section) => ({
              key: section.key,
              title: section.title,
              description: section.description ?? null,
              position: section.position,
              questions: {
                create: section.questions.map((question) => ({
                  key: question.key,
                  prompt: question.prompt,
                  guidance: question.guidance ?? null,
                  weight: question.weight,
                  position: question.position,
                  requiresNote: question.requiresNote ?? false
                }))
              }
            }))
          }
        }
      });

      ok(res, { id: template.id, version: template.version, status: template.status });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Replaces a draft's content wholesale.
 *
 * Only a DRAFT can be written to. A published version is immutable — that is
 * the guarantee every historical assessment rests on — so this refuses rather
 * than silently cloning, and the caller decides to clone explicitly.
 */
assessmentsRouter.put(
  "/assessment-templates/:templateId",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const templateId = req.params.templateId as string;
      const payload = templateBodySchema.parse(req.body);

      const template = await prisma.assessmentTemplate.findUnique({
        where: { id: templateId },
        select: { id: true, status: true }
      });
      if (!template) {
        throw new ApiHttpError(404, "TEMPLATE_NOT_FOUND", "That assessment template does not exist.");
      }
      if (template.status !== TEMPLATE_STATUS.draft) {
        throw new ApiHttpError(
          409,
          "TEMPLATE_NOT_EDITABLE",
          "A published version cannot be edited. Clone it to a new draft instead."
        );
      }

      await prisma.$transaction(async (tx) => {
        // Sections cascade to questions, so this clears both.
        await tx.assessmentSection.deleteMany({ where: { templateId } });
        await tx.assessmentTemplate.update({
          where: { id: templateId },
          data: {
            title: payload.title,
            description: payload.description ?? null,
            bandsJson: JSON.stringify(payload.bands),
            sections: {
              create: payload.sections.map((section) => ({
                key: section.key,
                title: section.title,
                description: section.description ?? null,
                position: section.position,
                questions: {
                  create: section.questions.map((question) => ({
                    key: question.key,
                    prompt: question.prompt,
                    guidance: question.guidance ?? null,
                    weight: question.weight,
                    position: question.position,
                    requiresNote: question.requiresNote ?? false
                  }))
                }
              }))
            }
          }
        });
      });

      ok(res, await previewTemplate(templateId));
    } catch (error) {
      next(error);
    }
  }
);

assessmentsRouter.post(
  "/assessment-templates/:templateId/publish",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const result = await publishTemplate({
        templateId: req.params.templateId as string,
        actorUserId: req.user?.id
      });
      ok(res, {
        id: result.template.id,
        version: result.template.version,
        status: result.template.status,
        maxPoints: result.template.maxPoints,
        checksum: result.checksum
      });
    } catch (error) {
      next(error);
    }
  }
);

assessmentsRouter.post(
  "/assessment-templates/:templateId/clone",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const clone = await cloneTemplate({
        templateId: req.params.templateId as string,
        actorUserId: req.user?.id
      });
      ok(res, { id: clone.id, version: clone.version, status: clone.status });
    } catch (error) {
      next(error);
    }
  }
);
