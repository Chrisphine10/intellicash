import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { appendAuditEvent } from "../services/audit-service";
import {
  DATA_SUBJECT_CONTRACT_VERSION,
  planMemberErasure,
  stripSecrets
} from "../domain/data-subject";

export const dataSubjectRouter = Router();

/**
 * Data subject rights — Kenya Data Protection Act, 2019.
 *
 * The privacy notice has promised these since it was written. Until now there
 * was nothing behind the promise: answering an access request meant somebody
 * writing SQL by hand, and an erasure request had no answer at all.
 *
 * Two rights are served here, because they are the two that can be executed
 * mechanically:
 *
 *  - **Access and portability** (ss. 26(a), 38) — everything held about the
 *    person, as JSON, in one response.
 *  - **Erasure** (ss. 26(e), 40) — identity and contact data removed, the
 *    group's financial record kept. See `domain/data-subject.ts` for why that
 *    split is the correct reading rather than a convenient one.
 *
 * Rectification is deliberately absent: correcting a name or phone already has
 * an endpoint (`PATCH /groups/:id/members/:memberId`), and a second way to
 * write the same columns is a second thing to keep in step.
 */

/**
 * Who may act on a member's behalf.
 *
 * A member is the data subject, but most members of a savings group have no
 * login — the group account holds the phone. So a request can be executed by
 * the member themselves, by the group that holds their record, or by a
 * platform admin. A field agent cannot: they visit groups, they do not
 * administer a member's personal data.
 */
async function loadMemberForSubjectRequest(user: AuthenticatedUser | undefined, memberId: string) {
  if (!user) throw new ApiHttpError(401, "UNAUTHENTICATED", "Authentication is required.");

  const member = await prisma.member.findFirst({
    where: { AND: [{ id: memberId }, { group: scopeGroupWhere(user) }] },
    include: { group: { select: { id: true, name: true, code: true } } }
  });
  // 404 rather than 403, the house convention: "forbidden" would confirm this
  // member exists to someone who should not know that.
  if (!member) {
    throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Member does not exist or is outside your access.");
  }

  const isSelf = user.memberId === member.id;
  const isAdmin = user.permissions.includes("groups:write");
  const isTheirGroup = user.role === "GROUP_ACCOUNT" && user.groupId === member.groupId;

  if (!isSelf && !isAdmin && !isTheirGroup) {
    throw new ApiHttpError(
      403,
      "NOT_THE_DATA_SUBJECT",
      "Only the member, their group, or a platform administrator can act on this record."
    );
  }
  return member;
}

/**
 * Access and portability: everything held about one member.
 *
 * Credentials are stripped on the way out — a PIN hash of a four-digit secret
 * is recoverable, so returning it would turn a privacy right into a way to
 * harvest meeting keys.
 */
dataSubjectRouter.get(
  "/members/:memberId/personal-data",
  requireAuth("members:read"),
  async (req, res, next) => {
    try {
      const member = await loadMemberForSubjectRequest(req.user, req.params.memberId as string);

      const [attendance, ledgerEntries, loans, keySubmissions, pinDeliveries, groupPayments, pollVotes, roleAssignments] =
        await Promise.all([
          prisma.attendance.findMany({ where: { memberId: member.id } }),
          prisma.ledgerEntry.findMany({ where: { memberId: member.id } }),
          prisma.loan.findMany({ where: { memberId: member.id } }),
          prisma.meetingKeySubmission.findMany({ where: { memberId: member.id } }),
          prisma.memberPinDelivery.findMany({ where: { memberId: member.id } }),
          prisma.groupPayment.findMany({ where: { memberId: member.id } }),
          prisma.pollVote.findMany({ where: { memberId: member.id } }),
          prisma.memberRoleAssignment.findMany({ where: { memberId: member.id } })
        ]);

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "MEMBER",
        entityId: member.id,
        type: "PERSONAL_DATA_EXPORTED",
        payload: { memberId: member.id, groupId: member.groupId, requestedBy: req.user?.role }
      });

      ok(res, {
        contractVersion: DATA_SUBJECT_CONTRACT_VERSION,
        exportedAt: new Date().toISOString(),
        subject: stripSecrets(member as unknown as Record<string, unknown>),
        records: {
          attendance,
          ledgerEntries,
          loans,
          // Delivery bodies are ciphertext; the metadata is the person's data.
          meetingKeySubmissions: keySubmissions.map((row) => stripSecrets(row as unknown as Record<string, unknown>)),
          pinDeliveries: pinDeliveries.map((row) => stripSecrets(row as unknown as Record<string, unknown>)),
          groupPayments,
          pollVotes,
          roleAssignments
        },
        note:
          "This is everything held about you in this system, except credentials, which are never disclosed even to their owner."
      });
    } catch (error) {
      next(error);
    }
  }
);

const erasureSchema = z.object({
  /*
   * Typing the member's name is the confirmation step. Erasure cannot be
   * undone and the financial rows stay behind under a pseudonym, so a mis-click
   * on a member list must not be enough to trigger it.
   */
  confirmFullName: z.string().min(1),
  reason: z.string().max(500).optional()
});

dataSubjectRouter.post(
  "/members/:memberId/erase",
  requireAuth("members:write"),
  async (req, res, next) => {
    try {
      const member = await loadMemberForSubjectRequest(req.user, req.params.memberId as string);
      const payload = erasureSchema.parse(req.body);

      /*
       * Already-erased is checked BEFORE the confirmation, deliberately.
       *
       * After an erasure the name is a pseudonym, so a client retrying with the
       * name it originally held would fail the confirmation and read as an
       * error — for an operation that already succeeded. There is also nothing
       * left to guard: confirmation protects against erasing the wrong person,
       * and this person's identity is already gone.
       */
      if (member.fullName.startsWith("Erased member ")) {
        ok(res, { alreadyErased: true, memberId: member.id });
        return;
      }
      if (payload.confirmFullName.trim() !== member.fullName.trim()) {
        throw new ApiHttpError(
          400,
          "CONFIRMATION_MISMATCH",
          "Type the member's full name exactly to confirm. Erasure cannot be undone."
        );
      }

      const plan = planMemberErasure(member.id);
      const data: Record<string, string | null> = {};
      for (const field of plan.erase) data[field.field] = field.replacement;

      await prisma.$transaction(async (tx) => {
        await tx.member.update({ where: { id: member.id }, data });
        // Deliveries carry the phone number and the message body in the clear
        // enough to matter, and nothing depends on them financially.
        await tx.memberPinDelivery.deleteMany({ where: { memberId: member.id } });
      });

      await appendAuditEvent({
        actorUserId: req.user?.id,
        entityType: "MEMBER",
        entityId: member.id,
        type: "PERSONAL_DATA_ERASED",
        // Never the erased values themselves — that would defeat the erasure.
        payload: {
          memberId: member.id,
          groupId: member.groupId,
          fieldsErased: plan.erase.map((field) => field.field),
          retained: plan.retain.map((item) => item.entity),
          reason: payload.reason ?? null
        }
      });

      ok(res, {
        erased: true,
        memberId: member.id,
        fieldsErased: plan.erase.map((field) => field.field),
        retained: plan.retain,
        note:
          "Identity and contact details are gone. The group's financial record is kept under a pseudonym, because those rows are also other members' balances."
      });
    } catch (error) {
      next(error);
    }
  }
);
