import { Router } from "express";
import { z } from "zod";
import { documentPresence, groupDocumentTypes } from "@intellicash/shared";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { appendAuditEvent } from "../services/audit-service";
import { documentStatus, registerSummary } from "../domain/group-document-state";

export const documentsRouter = Router();

/**
 * A group's document register — registration certificate, constitution, bank
 * mandate, and the rest.
 *
 * The split that runs through this file: **an agent records what they can see,
 * a reviewer decides what it means.** An agent standing in front of a
 * certificate can say it exists and photograph it. Whether the copy on file is
 * accepted as genuine is a back-office judgement, and letting the person who
 * collected the evidence also bless it removes the only check on it — the same
 * separation the whole register depends on.
 */

async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true }
  });
  if (!group) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  }
  return group;
}

/**
 * Verifying is refused for VILLAGE_AGENT on the ROLE, not merely on the
 * permission — the same shape as `assertMaySetPin`. A deployment that grants
 * an agent `documents:write` (which it should, so they can record presence)
 * must not thereby let them sign off their own evidence.
 */
function assertMayVerify(user: AuthenticatedUser | undefined) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");
  if (user.role === "VILLAGE_AGENT") {
    throw new ApiHttpError(
      403,
      "AGENT_CANNOT_VERIFY_DOCUMENT",
      "A field agent records what a group holds but cannot mark it verified. That check belongs to the reviewing officer."
    );
  }
}

function documentType(value: string) {
  if ((groupDocumentTypes as readonly string[]).includes(value)) return value;
  throw new ApiHttpError(404, "DOCUMENT_TYPE_NOT_FOUND", "That document type does not exist.");
}

const recordSchema = z.object({
  presence: z.enum(documentPresence),
  /** Null clears it — a document that no longer expires. */
  expiresOn: z.coerce.date().nullish(),
  attachmentId: z.string().nullish(),
  notes: z.string().max(2000).nullish()
});

const verifySchema = z.object({
  verification: z.enum(["VERIFIED", "REJECTED", "UNVERIFIED"]),
  notes: z.string().max(2000).nullish()
});

/**
 * The whole register, including the types the group has no row for yet.
 *
 * Absent rows are returned as MISSING rather than omitted: "we have never
 * recorded anything about the constitution" and "the group does not have one"
 * look identical to a reader, and a register that only lists what exists cannot
 * show a gap. The gap is the point.
 */
documentsRouter.get(
  "/groups/:groupId/documents",
  requireAuth("documents:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const rows = await prisma.groupDocument.findMany({ where: { groupId: group.id } });
      const byType = new Map(rows.map((row) => [row.documentType, row]));

      const documents = groupDocumentTypes.map((type) => {
        const row = byType.get(type);
        const status = documentStatus({
          presence: row?.presence ?? "MISSING",
          verification: row?.verification ?? "UNVERIFIED",
          expiresOn: row?.expiresOn ?? null
        });

        return {
          documentType: type,
          ...status,
          notes: row?.notes ?? null,
          attachmentId: row?.attachmentId ?? null,
          verifiedAt: row?.verifiedAt ?? null,
          updatedAt: row?.updatedAt ?? null
        };
      });

      ok(res, {
        group,
        documents,
        summary: registerSummary(documents)
      });
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Records whether the group holds a document. Presence only.
 *
 * Deliberately cannot set `verification`: an agent updating presence must not
 * be able to smuggle a verification through the same endpoint, and a reviewer
 * changing their mind should leave a distinct audit event.
 */
documentsRouter.put(
  "/groups/:groupId/documents/:documentType",
  requireAuth("documents:write"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const type = documentType(req.params.documentType as string);
      const payload = recordSchema.parse(req.body);

      const saved = await prisma.groupDocument.upsert({
        where: { groupId_documentType: { groupId: group.id, documentType: type } },
        create: {
          groupId: group.id,
          documentType: type,
          presence: payload.presence,
          expiresOn: payload.expiresOn ?? null,
          attachmentId: payload.attachmentId ?? null,
          notes: payload.notes ?? null
        },
        update: {
          presence: payload.presence,
          expiresOn: payload.expiresOn ?? null,
          attachmentId: payload.attachmentId ?? null,
          notes: payload.notes ?? null
        }
      });

      await appendAuditEvent({
        actorUserId: req.user?.id ?? null,
        entityType: "GROUP",
        entityId: group.id,
        type: "GROUP_DOCUMENT_UPDATED",
        payload: {
          documentType: type,
          presence: saved.presence,
          expiresOn: saved.expiresOn,
          // Named so the trail distinguishes an observation from a judgement.
          change: "PRESENCE"
        }
      });

      ok(res, {
        documentType: type,
        ...documentStatus(saved),
        notes: saved.notes,
        attachmentId: saved.attachmentId
      });
    } catch (error) {
      next(error);
    }
  }
);

/** The back-office judgement. Never an agent's to make. */
documentsRouter.post(
  "/groups/:groupId/documents/:documentType/verify",
  requireAuth("documents:write"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      assertMayVerify(req.user);
      const type = documentType(req.params.documentType as string);
      const payload = verifySchema.parse(req.body);

      const existing = await prisma.groupDocument.findUnique({
        where: { groupId_documentType: { groupId: group.id, documentType: type } }
      });
      if (!existing || existing.presence !== "PRESENT") {
        // Verifying something nobody has recorded as held would put a tick
        // against a document that does not exist.
        throw new ApiHttpError(
          400,
          "DOCUMENT_NOT_HELD",
          "This document is not recorded as held, so there is nothing to verify."
        );
      }

      const saved = await prisma.groupDocument.update({
        where: { id: existing.id },
        data: {
          verification: payload.verification,
          notes: payload.notes ?? existing.notes,
          verifiedByUserId: req.user?.id ?? null,
          verifiedAt: payload.verification === "UNVERIFIED" ? null : new Date()
        }
      });

      await appendAuditEvent({
        actorUserId: req.user?.id ?? null,
        entityType: "GROUP",
        entityId: group.id,
        type: "GROUP_DOCUMENT_UPDATED",
        payload: {
          documentType: type,
          verification: saved.verification,
          change: "VERIFICATION"
        }
      });

      ok(res, {
        documentType: type,
        ...documentStatus(saved),
        notes: saved.notes,
        verifiedAt: saved.verifiedAt
      });
    } catch (error) {
      next(error);
    }
  }
);
