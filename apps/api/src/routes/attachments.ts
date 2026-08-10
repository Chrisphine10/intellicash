import { mkdirSync } from "node:fs";
import { extname, join } from "node:path";
import { randomUUID } from "node:crypto";
import { Router } from "express";
import multer from "multer";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { uploadRoot } from "../lib/uploads";
import { scopeGroupWhere } from "../services/account-scope";
import { appendAuditEvent } from "../services/audit-service";
import {
  attachmentDirectory,
  attachmentUrl,
  hashFile,
  removeAttachmentFile
} from "../services/attachment-storage";
import { checkUploadStorage } from "../services/storage-guard";

export const attachmentsRouter = Router();

/**
 * Field evidence: photographs taken during a visit.
 *
 * Uploading is deliberately **two steps**, not one:
 *
 *   1. `POST /uploads/visit-photo` — multipart. Writes the file, returns a
 *      storage path and its content hash. Creates no database row.
 *   2. `POST /visits/:visitId/attachments` — JSON. Binds that path to a visit,
 *      a section, a question and an agent.
 *
 * The reason is what happens when a phone drops between them. One combined
 * request that fails halfway can leave a row pointing at a file that was never
 * finished writing — a broken image in the record with no way to tell it from a
 * real one. Split, the worst case is an orphaned file on disk with no row,
 * which is invisible to every reader and reapable by a sweeper.
 *
 * It also means the expensive part (pushing several hundred KB over 2G) is
 * retried on its own, without re-sending the metadata each time.
 */

/** Photos are downscaled on the phone; this is a backstop, not a target. */
const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

/** Per visit. Enough for a thorough visit, few enough to bound the disk. */
export const MAX_ATTACHMENTS_PER_VISIT = 20;

const PHOTO_MIME_TYPES = ["image/jpeg", "image/png", "image/webp"];

function photoExtension(file: Express.Multer.File) {
  const original = extname(file.originalname).toLowerCase().replace(/[^a-z0-9.]/g, "");
  if (original) return original;
  if (file.mimetype === "image/png") return ".png";
  if (file.mimetype === "image/webp") return ".webp";
  return ".jpg";
}

const photoStorage = multer.diskStorage({
  destination(_req, _file, callback) {
    try {
      // Date-sharded: a single flat directory holding a year of field
      // photographs is slow to list and miserable to back up incrementally.
      const relative = attachmentDirectory("visit-photo");
      const destination = join(uploadRoot, relative);
      mkdirSync(destination, { recursive: true });
      callback(null, destination);
    } catch (error) {
      callback(error as Error, "");
    }
  },
  filename(_req, file, callback) {
    callback(null, `${Date.now()}-${randomUUID()}${photoExtension(file)}`);
  }
});

const uploadPhoto = multer({
  storage: photoStorage,
  limits: { fileSize: MAX_PHOTO_BYTES, files: 1 },
  fileFilter(_req, file, callback) {
    if (!PHOTO_MIME_TYPES.includes(file.mimetype)) {
      callback(
        new ApiHttpError(
          400,
          "UPLOAD_TYPE_NOT_ALLOWED",
          "Visit evidence must be a JPEG, PNG or WebP image."
        )
      );
      return;
    }
    callback(null, true);
  }
});

/**
 * Step one. Refuses before writing when the disk is nearly full.
 *
 * The 503 is deliberate and the phone treats it as retryable: the file stays on
 * the device, the visit itself still syncs, and the photograph arrives once
 * somebody clears space. Losing a visit because a disk filled would be far
 * worse than a late photo — and a full disk corrupting SQLite worse still.
 */
attachmentsRouter.post(
  "/uploads/visit-photo",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const storage = await checkUploadStorage();
      if (!storage.acceptsUploads) {
        throw new ApiHttpError(503, "UPLOAD_STORAGE_FULL", storage.message, {
          level: storage.level,
          retryable: true
        });
      }
    } catch (error) {
      next(error);
      return;
    }

    uploadPhoto.single("file")(req, res, (error) => {
      if (error) {
        next(error);
        return;
      }
      if (!req.file) {
        next(new ApiHttpError(400, "UPLOAD_FILE_REQUIRED", "Choose a photo to upload."));
        return;
      }

      const relative = `${attachmentDirectory("visit-photo")}/${req.file.filename}`;
      hashFile(req.file.path)
        .then((sha256) => {
          ok(res.status(201), {
            storagePath: relative,
            url: attachmentUrl(relative),
            fileName: req.file!.originalname,
            mimeType: req.file!.mimetype,
            size: req.file!.size,
            // Returned so the phone can send it back at binding time and the
            // server can confirm the bytes it hashed are the bytes being bound.
            sha256
          });
        })
        .catch(next);
    });
  }
);

const bindSchema = z.object({
  storagePath: z.string().min(1),
  fileName: z.string().min(1).max(255),
  mimeType: z.enum(["image/jpeg", "image/png", "image/webp"]),
  size: z.number().int().positive().max(MAX_PHOTO_BYTES),
  sha256: z.string().regex(/^[0-9a-f]{64}$/, "Expected a sha256 hex digest."),
  /**
   * Never an anonymous gallery. A visit photo must say what it is evidence
   * OF — enforced here rather than by column nullability, because the same
   * table also holds standing group documents that have no section at all.
   */
  sectionKey: z.string().min(1).max(64),
  questionKey: z.string().min(1).max(64).optional(),
  capturedAt: z.coerce.date(),
  caption: z.string().max(500).optional(),
  clientRequestId: z.string().min(1).max(120)
});

async function loadVisitInScope(user: AuthenticatedUser | undefined, visitId: string) {
  const visit = await prisma.groupVisit.findFirst({
    where: { AND: [{ id: visitId }, { group: scopeGroupWhere(user) }] },
    select: { id: true, groupId: true, villageAgentId: true }
  });
  if (!visit) {
    throw new ApiHttpError(404, "VISIT_NOT_FOUND", "Visit does not exist or is outside your access.");
  }
  return visit;
}

/**
 * Step two: bind an uploaded file to the visit it is evidence for.
 *
 * Idempotent on `clientRequestId`, and again on the content hash, because a
 * phone retrying a flaky push must not fill the record with duplicates of the
 * same photograph.
 */
attachmentsRouter.post(
  "/visits/:visitId/attachments",
  requireAuth("visits:write"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      const payload = bindSchema.parse(req.body);

      const existing = await prisma.attachment.findUnique({
        where: { clientRequestId: payload.clientRequestId }
      });
      if (existing) {
        // 200, not 409. A client that treats success as failure retries
        // forever; the same lesson as visit submission.
        ok(res, serializeAttachment(existing));
        return;
      }

      const count = await prisma.attachment.count({ where: { visitId: visit.id } });
      if (count >= MAX_ATTACHMENTS_PER_VISIT) {
        throw new ApiHttpError(
          400,
          "ATTACHMENT_LIMIT_REACHED",
          `A visit may carry at most ${MAX_ATTACHMENTS_PER_VISIT} photos.`
        );
      }

      let created;
      try {
        created = await prisma.attachment.create({
          data: {
            kind: "VISIT_PHOTO",
            groupId: visit.groupId,
            visitId: visit.id,
            sectionKey: payload.sectionKey,
            questionKey: payload.questionKey ?? null,
            villageAgentId: visit.villageAgentId,
            uploadedByUserId: req.user?.id ?? null,
            storagePath: payload.storagePath,
            fileName: payload.fileName,
            mimeType: payload.mimeType,
            sizeBytes: payload.size,
            sha256: payload.sha256,
            capturedAt: payload.capturedAt,
            caption: payload.caption ?? null,
            clientRequestId: payload.clientRequestId
          }
        });
      } catch (error) {
        // The same image already attached to this visit under a different
        // request id — a retry that lost its original id. Answer with the
        // attachment that already exists rather than a second copy.
        const duplicate = await prisma.attachment.findFirst({
          where: { sha256: payload.sha256, visitId: visit.id }
        });
        if (duplicate) {
          // The newly uploaded file is now redundant; leave no orphan behind.
          if (duplicate.storagePath !== payload.storagePath) {
            await removeAttachmentFile(payload.storagePath);
          }
          ok(res, serializeAttachment(duplicate));
          return;
        }
        throw error;
      }

      await appendAuditEvent({
        actorUserId: req.user?.id ?? null,
        entityType: "GROUP_VISIT",
        entityId: visit.id,
        type: "VISIT_ATTACHMENT_ADDED",
        payload: {
          groupId: visit.groupId,
          attachmentId: created.id,
          sectionKey: created.sectionKey,
          questionKey: created.questionKey,
          sha256: created.sha256,
          sizeBytes: created.sizeBytes
        }
      });

      ok(res.status(201), serializeAttachment(created));
    } catch (error) {
      next(error);
    }
  }
);

attachmentsRouter.get(
  "/visits/:visitId/attachments",
  requireAuth("visits:read"),
  async (req, res, next) => {
    try {
      const visit = await loadVisitInScope(req.user, req.params.visitId as string);
      const rows = await prisma.attachment.findMany({
        where: { visitId: visit.id },
        orderBy: [{ sectionKey: "asc" }, { createdAt: "asc" }]
      });
      ok(res, rows.map(serializeAttachment));
    } catch (error) {
      next(error);
    }
  }
);

/**
 * Removing evidence is admin-only and leaves an audit trail.
 *
 * `visits:amend` rather than `visits:write`: an agent who could delete their
 * own photographs could remove the ones that contradict their report.
 */
attachmentsRouter.delete(
  "/attachments/:attachmentId",
  requireAuth("visits:amend"),
  async (req, res, next) => {
    try {
      const attachment = await prisma.attachment.findFirst({
        where: {
          AND: [{ id: req.params.attachmentId as string }, { group: scopeGroupWhere(req.user) }]
        }
      });
      if (!attachment) {
        throw new ApiHttpError(404, "ATTACHMENT_NOT_FOUND", "That attachment does not exist.");
      }

      await prisma.attachment.delete({ where: { id: attachment.id } });
      await removeAttachmentFile(attachment.storagePath);

      await appendAuditEvent({
        actorUserId: req.user?.id ?? null,
        entityType: "GROUP_VISIT",
        entityId: attachment.visitId ?? attachment.groupId,
        type: "VISIT_ATTACHMENT_REMOVED",
        payload: {
          attachmentId: attachment.id,
          groupId: attachment.groupId,
          sha256: attachment.sha256,
          fileName: attachment.fileName
        }
      });

      ok(res, { removed: true });
    } catch (error) {
      next(error);
    }
  }
);

/** Operational visibility, so a full disk is noticed before it bites. */
attachmentsRouter.get("/uploads/storage", requireAuth("audit:read"), async (_req, res, next) => {
  try {
    ok(res, await checkUploadStorage());
  } catch (error) {
    next(error);
  }
});

function serializeAttachment(row: {
  id: string;
  kind: string;
  groupId: string;
  visitId: string | null;
  sectionKey: string | null;
  questionKey: string | null;
  storagePath: string;
  fileName: string;
  mimeType: string;
  sizeBytes: number;
  sha256: string;
  capturedAt: Date | null;
  caption: string | null;
  createdAt: Date;
}) {
  return {
    id: row.id,
    kind: row.kind,
    groupId: row.groupId,
    visitId: row.visitId,
    sectionKey: row.sectionKey,
    questionKey: row.questionKey,
    url: attachmentUrl(row.storagePath),
    fileName: row.fileName,
    mimeType: row.mimeType,
    size: row.sizeBytes,
    sha256: row.sha256,
    capturedAt: row.capturedAt,
    caption: row.caption,
    createdAt: row.createdAt
  };
}
