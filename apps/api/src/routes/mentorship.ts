import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { appendAuditEvent } from "../services/audit-service";
import {
  MAX_MENTORSHIP_RATING,
  MIN_MENTORSHIP_RATING,
  actionItemState,
  actionPlanSummary,
  averageRating,
  byUrgency
} from "../domain/action-plan";

export const mentorshipRouter = Router();

/**
 * Coaching delivered at a visit, the group's verdict on it, and the work agreed
 * before the next one.
 *
 * Two different lifetimes, deliberately split:
 *
 *  - **Mentorship and ratings belong to the visit.** They describe one
 *    occasion, are written once, and are recorded atomically with it.
 *  - **Action items outlive the visit.** They are the point of the whole
 *    exercise — an agent's follow-up queue, and the first thing shown at the
 *    start of the next visit. So they get their own endpoints and their own
 *    status, and are not buried inside a visit document nobody re-reads.
 *
 * That second half is what makes the loop real rather than a form that is
 * filled in and filed.
 */

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
// Reference data
// ---------------------------------------------------------------------------

/** What the phone renders. Read with `visits:read` — an agent needs the list. */
mentorshipRouter.get("/mentorship-topics", requireAuth("visits:read"), async (_req, res, next) => {
  try {
    const [topics, dimensions] = await Promise.all([
      prisma.mentorshipTopic.findMany({
        where: { isActive: true },
        orderBy: [{ position: "asc" }, { title: "asc" }]
      }),
      prisma.mentorshipRatingDimension.findMany({
        where: { isActive: true },
        orderBy: [{ position: "asc" }, { title: "asc" }]
      })
    ]);

    ok(res, {
      topics: topics.map((topic) => ({
        key: topic.key,
        title: topic.title,
        description: topic.description
      })),
      dimensions: dimensions.map((dimension) => ({
        key: dimension.key,
        title: dimension.title
      })),
      ratingScale: { min: MIN_MENTORSHIP_RATING, max: MAX_MENTORSHIP_RATING }
    });
  } catch (error) {
    next(error);
  }
});

// ---------------------------------------------------------------------------
// Mentorship, recorded with its visit
// ---------------------------------------------------------------------------

const mentorshipSchema = z.object({
  sessions: z
    .array(
      z.object({
        topicKey: z.string().min(1).max(64),
        notes: z.string().max(4000).optional(),
        durationMinutes: z.number().int().min(0).max(600).optional()
      })
    )
    .max(20)
    .default([]),
  ratings: z
    .array(
      z.object({
        dimensionKey: z.string().min(1).max(64),
        score: z.number().int().min(MIN_MENTORSHIP_RATING).max(MAX_MENTORSHIP_RATING),
        comment: z.string().max(1000).optional(),
        /**
         * Who actually answered. Defaults to the group's representative
         * because that is who should be asked — but it is recorded rather than
         * assumed, so an aggregate can exclude anything the agent scored
         * themselves.
         */
        ratedByRole: z.enum(["GROUP_REPRESENTATIVE", "AGENT"]).default("GROUP_REPRESENTATIVE")
      })
    )
    .max(20)
    .default([])
});

/**
 * Records the coaching and the group's verdict, atomically.
 *
 * Idempotent on the visit: a phone retrying its whole document re-writes the
 * same rows rather than appending a second set of sessions.
 */
mentorshipRouter.put(
  "/visits/:visitId/mentorship",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      const payload = mentorshipSchema.parse(req.body);

      const [topics, dimensions] = await Promise.all([
        prisma.mentorshipTopic.findMany(),
        prisma.mentorshipRatingDimension.findMany()
      ]);
      const topicByKey = new Map(topics.map((topic) => [topic.key, topic]));
      const dimensionByKey = new Map(dimensions.map((d) => [d.key, d]));

      await prisma.$transaction(async (tx) => {
        // Replaced wholesale: a resubmission is the authoritative statement of
        // what happened at the visit, and a leftover session from an earlier
        // attempt would read as coaching that was never delivered.
        await tx.visitMentorshipSession.deleteMany({ where: { visitId: visit.id } });
        await tx.visitMentorshipRating.deleteMany({ where: { visitId: visit.id } });

        for (const session of dedupeBy(payload.sessions, (s) => s.topicKey)) {
          const topic = topicByKey.get(session.topicKey);
          await tx.visitMentorshipSession.create({
            data: {
              visitId: visit.id,
              topicId: topic?.id ?? null,
              topicKeySnapshot: session.topicKey,
              // Snapshotted so the record still reads after a topic is renamed
              // or retired. Falling back to the key keeps an unknown topic
              // legible rather than blank.
              topicTitleSnapshot: topic?.title ?? session.topicKey,
              notes: session.notes ?? null,
              durationMinutes: session.durationMinutes ?? null
            }
          });
        }

        for (const rating of dedupeBy(payload.ratings, (r) => r.dimensionKey)) {
          const dimension = dimensionByKey.get(rating.dimensionKey);
          await tx.visitMentorshipRating.create({
            data: {
              visitId: visit.id,
              dimensionId: dimension?.id ?? null,
              dimensionKeySnapshot: rating.dimensionKey,
              score: rating.score,
              ratedByRole: rating.ratedByRole,
              comment: rating.comment ?? null
            }
          });
        }
      });

      await appendAuditEvent({
        actorUserId: req.user?.id ?? null,
        entityType: "GROUP_VISIT",
        entityId: visit.id,
        type: "VISIT_MENTORSHIP_RECORDED",
        payload: {
          groupId: visit.groupId,
          sessions: payload.sessions.length,
          ratings: payload.ratings.length
        }
      });

      ok(res, await readMentorship(visit.id));
    } catch (error) {
      next(error);
    }
  }
);

mentorshipRouter.get(
  "/visits/:visitId/mentorship",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      ok(res, await readMentorship(visit.id));
    } catch (error) {
      next(error);
    }
  }
);

async function readMentorship(visitId: string) {
  const [sessions, ratings] = await Promise.all([
    prisma.visitMentorshipSession.findMany({ where: { visitId }, orderBy: { createdAt: "asc" } }),
    prisma.visitMentorshipRating.findMany({ where: { visitId } })
  ]);

  // Only what the GROUP said counts towards the headline number. An agent's
  // own score of their own session is recorded but never averaged in.
  const groupScores = ratings
    .filter((rating) => rating.ratedByRole === "GROUP_REPRESENTATIVE")
    .map((rating) => rating.score);

  return {
    sessions: sessions.map((session) => ({
      topicKey: session.topicKeySnapshot,
      topicTitle: session.topicTitleSnapshot,
      notes: session.notes,
      durationMinutes: session.durationMinutes
    })),
    ratings: ratings.map((rating) => ({
      dimensionKey: rating.dimensionKeySnapshot,
      score: rating.score,
      ratedByRole: rating.ratedByRole,
      comment: rating.comment
    })),
    averageGroupRating: averageRating(groupScores),
    ratedByGroup: groupScores.length > 0
  };
}

// ---------------------------------------------------------------------------
// Action items, which outlive the visit
// ---------------------------------------------------------------------------

const actionItemSchema = z.object({
  title: z.string().min(1).max(300),
  detail: z.string().max(2000).optional(),
  owner: z.string().max(120).optional(),
  dueDate: z.coerce.date().optional()
});

mentorshipRouter.post(
  "/visits/:visitId/action-items",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      const payload = actionItemSchema.parse(req.body);

      const created = await prisma.visitActionItem.create({
        data: {
          visitId: visit.id,
          groupId: visit.groupId,
          title: payload.title,
          detail: payload.detail ?? null,
          owner: payload.owner ?? null,
          dueDate: payload.dueDate ?? null
        }
      });

      ok(res.status(201), serializeActionItem(created));
    } catch (error) {
      next(error);
    }
  }
);

/**
 * A group's outstanding work.
 *
 * This is what the phone shows at the START of the next visit. Surfacing last
 * time's commitments before the agent begins is the difference between a
 * follow-up and a fresh conversation that repeats it.
 */
mentorshipRouter.get(
  "/groups/:groupId/action-items",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const group = await prisma.group.findFirst({
        where: { AND: [{ id: req.params.groupId as string }, scopeGroupWhere(req.user)] },
        select: { id: true, name: true }
      });
      if (!group) {
        throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
      }

      const rows = await prisma.visitActionItem.findMany({ where: { groupId: group.id } });
      const items = rows.map(serializeActionItem).sort((a, b) => byUrgency(a.state, b.state));

      ok(res, {
        group,
        items,
        summary: actionPlanSummary(items.map((item) => item.state))
      });
    } catch (error) {
      next(error);
    }
  }
);

/** The agent's own follow-up queue, worst-first across their whole caseload. */
mentorshipRouter.get(
  "/agents/me/action-items",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const rows = await prisma.visitActionItem.findMany({
        where: { group: scopeGroupWhere(req.user) },
        include: { group: { select: { id: true, name: true, code: true } } }
      });

      const items = rows
        .map((row) => ({ ...serializeActionItem(row), group: row.group }))
        .sort((a, b) => byUrgency(a.state, b.state));

      ok(res, {
        items,
        summary: actionPlanSummary(items.map((item) => item.state))
      });
    } catch (error) {
      next(error);
    }
  }
);

const updateActionItemSchema = z.object({
  status: z.enum(["OPEN", "IN_PROGRESS", "DONE", "CANCELLED"]).optional(),
  closingNote: z.string().max(2000).optional(),
  /** The visit at which it was closed, so the loop is traceable both ways. */
  closedAtVisitId: z.string().optional(),
  dueDate: z.coerce.date().nullish(),
  owner: z.string().max(120).nullish()
});

mentorshipRouter.patch(
  "/action-items/:itemId",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const existing = await prisma.visitActionItem.findFirst({
        where: {
          AND: [{ id: req.params.itemId as string }, { group: scopeGroupWhere(req.user) }]
        }
      });
      if (!existing) {
        throw new ApiHttpError(404, "ACTION_ITEM_NOT_FOUND", "That action item does not exist.");
      }

      const payload = updateActionItemSchema.parse(req.body);
      const closing = payload.status === "DONE" || payload.status === "CANCELLED";

      const updated = await prisma.visitActionItem.update({
        where: { id: existing.id },
        data: {
          ...(payload.status ? { status: payload.status } : {}),
          ...(payload.closingNote !== undefined ? { closingNote: payload.closingNote } : {}),
          ...(payload.dueDate !== undefined ? { dueDate: payload.dueDate ?? null } : {}),
          ...(payload.owner !== undefined ? { owner: payload.owner ?? null } : {}),
          ...(payload.closedAtVisitId ? { closedAtVisitId: payload.closedAtVisitId } : {}),
          // Set when closing, cleared when reopened — so a reopened item does
          // not keep claiming it was finished on a date in the past.
          ...(payload.status ? { closedAt: closing ? new Date() : null } : {})
        }
      });

      await appendAuditEvent({
        actorUserId: req.user?.id ?? null,
        entityType: "GROUP",
        entityId: updated.groupId,
        type: "VISIT_ACTION_ITEM_UPDATED",
        payload: { actionItemId: updated.id, status: updated.status, title: updated.title }
      });

      ok(res, serializeActionItem(updated));
    } catch (error) {
      next(error);
    }
  }
);

function serializeActionItem(row: {
  id: string;
  visitId: string;
  groupId: string;
  title: string;
  detail: string | null;
  owner: string | null;
  dueDate: Date | null;
  status: string;
  closedAt: Date | null;
  closingNote: string | null;
  createdAt: Date;
}) {
  return {
    id: row.id,
    visitId: row.visitId,
    groupId: row.groupId,
    title: row.title,
    detail: row.detail,
    owner: row.owner,
    status: row.status,
    closedAt: row.closedAt,
    closingNote: row.closingNote,
    createdAt: row.createdAt,
    // Lateness is worked out here, on every read, rather than stored.
    state: actionItemState({ status: row.status, dueDate: row.dueDate })
  };
}

/** Last write wins, matching how the assessment handles repeated answers. */
function dedupeBy<T>(items: readonly T[], key: (item: T) => string) {
  const byKey = new Map<string, T>();
  for (const item of items) byKey.set(key(item), item);
  return [...byKey.values()];
}

// ---------------------------------------------------------------------------
// Authoring the topic list
// ---------------------------------------------------------------------------

const topicSchema = z.object({
  key: z
    .string()
    .regex(/^[a-z0-9][a-z0-9_]{0,62}$/, "Use lower-case letters, digits and underscores."),
  title: z.string().min(1).max(200),
  description: z.string().max(1000).optional(),
  position: z.number().int().min(0).optional(),
  isActive: z.boolean().optional()
});

/** Everything, including retired topics, for the settings screen. */
mentorshipRouter.get(
  "/mentorship-topics/all",
  requireAuth("assessment-templates:write"),
  async (_req, res, next) => {
    try {
      const [topics, dimensions] = await Promise.all([
        prisma.mentorshipTopic.findMany({ orderBy: [{ position: "asc" }, { title: "asc" }] }),
        prisma.mentorshipRatingDimension.findMany({
          orderBy: [{ position: "asc" }, { title: "asc" }]
        })
      ]);
      ok(res, { topics, dimensions });
    } catch (error) {
      next(error);
    }
  }
);

mentorshipRouter.post(
  "/mentorship-topics",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const payload = topicSchema.parse(req.body);
      const existing = await prisma.mentorshipTopic.findUnique({ where: { key: payload.key } });
      if (existing) {
        throw new ApiHttpError(
          409,
          "TOPIC_KEY_TAKEN",
          "A topic already uses that key. Keys are what past sessions and trends join on, so they cannot be reused."
        );
      }

      const created = await prisma.mentorshipTopic.create({
        data: {
          key: payload.key,
          title: payload.title,
          description: payload.description ?? null,
          position: payload.position ?? 0,
          isActive: payload.isActive ?? true
        }
      });
      ok(res.status(201), created);
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Renames or retires a topic. The KEY is never editable.
 *
 * A key is what completed sessions and cross-visit trends join on; changing it
 * would orphan every session recorded under the old one. Titles are free to
 * change because sessions snapshot the title at the time they were recorded.
 */
mentorshipRouter.patch(
  "/mentorship-topics/:topicKey",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const payload = topicSchema.partial().omit({ key: true }).parse(req.body);
      const topic = await prisma.mentorshipTopic.findUnique({
        where: { key: req.params.topicKey as string }
      });
      if (!topic) {
        throw new ApiHttpError(404, "TOPIC_NOT_FOUND", "That topic does not exist.");
      }

      const updated = await prisma.mentorshipTopic.update({
        where: { id: topic.id },
        data: {
          ...(payload.title !== undefined ? { title: payload.title } : {}),
          ...(payload.description !== undefined
            ? { description: payload.description ?? null }
            : {}),
          ...(payload.position !== undefined ? { position: payload.position } : {}),
          ...(payload.isActive !== undefined ? { isActive: payload.isActive } : {})
        }
      });
      ok(res, updated);
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Retires a topic rather than deleting it once it has been used.
 *
 * Deleting is allowed only while nothing references it. After that, retiring
 * keeps it out of the phone's list while leaving every recorded session intact
 * and readable — which they would be anyway, since the title is snapshotted,
 * but the row is still worth keeping for the key.
 */
mentorshipRouter.delete(
  "/mentorship-topics/:topicKey",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const topic = await prisma.mentorshipTopic.findUnique({
        where: { key: req.params.topicKey as string }
      });
      if (!topic) {
        throw new ApiHttpError(404, "TOPIC_NOT_FOUND", "That topic does not exist.");
      }

      const used = await prisma.visitMentorshipSession.count({
        where: { topicKeySnapshot: topic.key }
      });
      if (used > 0) {
        const retired = await prisma.mentorshipTopic.update({
          where: { id: topic.id },
          data: { isActive: false }
        });
        ok(res, { retired: true, deleted: false, sessions: used, topic: retired });
        return;
      }

      await prisma.mentorshipTopic.delete({ where: { id: topic.id } });
      ok(res, { retired: false, deleted: true, sessions: 0 });
    } catch (error) {
      next(error);
    }
  }
);

const dimensionSchema = z.object({
  key: z.string().regex(/^[a-z0-9][a-z0-9_]{0,62}$/),
  title: z.string().min(1).max(200),
  position: z.number().int().min(0).optional(),
  isActive: z.boolean().optional()
});

mentorshipRouter.post(
  "/mentorship-dimensions",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const payload = dimensionSchema.parse(req.body);
      const existing = await prisma.mentorshipRatingDimension.findUnique({
        where: { key: payload.key }
      });
      if (existing) {
        throw new ApiHttpError(409, "DIMENSION_KEY_TAKEN", "A rating question already uses that key.");
      }
      const created = await prisma.mentorshipRatingDimension.create({
        data: {
          key: payload.key,
          title: payload.title,
          position: payload.position ?? 0,
          isActive: payload.isActive ?? true
        }
      });
      ok(res.status(201), created);
    } catch (error) {
      next(error);
    }
  }
);

mentorshipRouter.patch(
  "/mentorship-dimensions/:dimensionKey",
  requireAuth("assessment-templates:write"),
  async (req, res, next) => {
    try {
      const payload = dimensionSchema.partial().omit({ key: true }).parse(req.body);
      const dimension = await prisma.mentorshipRatingDimension.findUnique({
        where: { key: req.params.dimensionKey as string }
      });
      if (!dimension) {
        throw new ApiHttpError(404, "DIMENSION_NOT_FOUND", "That rating question does not exist.");
      }
      const updated = await prisma.mentorshipRatingDimension.update({
        where: { id: dimension.id },
        data: {
          ...(payload.title !== undefined ? { title: payload.title } : {}),
          ...(payload.position !== undefined ? { position: payload.position } : {}),
          ...(payload.isActive !== undefined ? { isActive: payload.isActive } : {})
        }
      });
      ok(res, updated);
    } catch (error) {
      next(error);
    }
  }
);
