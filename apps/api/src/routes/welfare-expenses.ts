import { Router } from "express";
import { z } from "zod";
import { requireAuth } from "../middleware/auth";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import { ensureActiveCycle } from "../services/cycle-service";
import { appendLedgerEntry, resolveFundAccount } from "./groups";

export const welfareExpensesRouter = Router();

/**
 * Welfare spending.
 *
 * Money paid OUT of the social (welfare) fund during a cycle. The rule that
 * matters for share-out: the welfare fund is spent down as the cycle runs, and
 * **what remains at the end is what gets distributed**. So an expense here
 * directly reduces what every member receives — which is why it is recorded
 * with a payee and an approver rather than as an anonymous debit.
 *
 * The amount goes through `appendLedgerEntry`, reusing the existing money path
 * whole: hash signing, cycle stamping, and the overdraw guard that already
 * refuses a debit larger than the fund holds. This module adds context, never
 * a second way to move money.
 */
async function loadGroupInScope(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: { AND: [{ id: groupId }, scopeGroupWhere(user)] },
    select: { id: true, name: true, code: true }
  });
  if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");
  return group;
}

const createSchema = z.object({
  amountCents: z.number().int().min(1),
  category: z.string().trim().min(2).max(60),
  payeeMemberId: z.string().optional(),
  payeeName: z.string().trim().max(120).optional(),
  note: z.string().trim().max(500).optional(),
  meetingId: z.string().optional(),
  clientRequestId: z.string().trim().min(4).max(120).optional()
});

welfareExpensesRouter.post(
  "/groups/:groupId/welfare-expenses",
  // ledger:write, not a new permission string: ensureRolePermissionTemplates
  // upserts with `update: {}`, so a new permission would never reach existing
  // template rows and nobody could record an expense.
  requireAuth("ledger:write"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const body = createSchema.parse(req.body ?? {});

      if (!body.payeeMemberId && !body.payeeName) {
        throw new ApiHttpError(
          400,
          "PAYEE_REQUIRED",
          "Record who received the money — a member, or a name. Welfare is often paid to a member's family or a hospital."
        );
      }

      const expense = await prisma.$transaction(async (tx) => {
        const cycle = await ensureActiveCycle(tx, group.id);
        const fund = await resolveFundAccount(tx, group.id, "SOCIAL");

        // The overdraw guard inside appendLedgerEntry refuses an expense larger
        // than the welfare fund. Checked here first only so the message names
        // the welfare fund rather than a generic account.
        if (body.amountCents > fund.balanceCents) {
          throw new ApiHttpError(
            400,
            "INSUFFICIENT_WELFARE_FUND",
            `The welfare fund holds ${(fund.balanceCents / 100).toLocaleString("en-KE", {
              minimumFractionDigits: 2
            })} and this expense is more than that.`,
            {
              requestedCents: body.amountCents,
              availableCents: fund.balanceCents,
              shortfallCents: body.amountCents - fund.balanceCents
            }
          );
        }

        if (body.payeeMemberId) {
          const member = await tx.member.findFirst({
            where: { id: body.payeeMemberId, groupId: group.id },
            select: { id: true }
          });
          if (!member) {
            throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "Payee is not a member of this group.");
          }
        }

        const entry = await appendLedgerEntry(tx, {
          groupId: group.id,
          memberId: body.payeeMemberId ?? null,
          meetingId: body.meetingId ?? null,
          fundAccountId: fund.id,
          type: "WELFARE_EXPENSE",
          amountCents: body.amountCents,
          direction: "DEBIT",
          description: `Welfare: ${body.category}`,
          clientRequestId: body.clientRequestId
        });

        return tx.welfareExpense.create({
          data: {
            groupId: group.id,
            cycleId: cycle.id,
            meetingId: body.meetingId ?? null,
            ledgerEntryId: entry.id,
            category: body.category,
            payeeMemberId: body.payeeMemberId ?? null,
            payeeName: body.payeeName ?? null,
            note: body.note ?? null,
            approvedByUserId: req.user?.id ?? null
          },
          include: { ledgerEntry: { select: { amountCents: true, createdAt: true } } }
        });
      });

      const fund = await prisma.fundAccount.findFirst({
        where: { groupId: group.id, type: "SOCIAL" },
        select: { balanceCents: true }
      });

      ok(res.status(201), {
        expense,
        // Returned so a UI can show the consequence immediately: this is the
        // money that will be shared out if nothing else changes.
        welfareBalanceCents: fund?.balanceCents ?? 0
      });
    } catch (error) {
      next(error);
    }
  }
);

welfareExpensesRouter.get(
  "/groups/:groupId/welfare-expenses",
  requireAuth("ledger:read"),
  async (req, res, next) => {
    try {
      const group = await loadGroupInScope(req.user, req.params.groupId as string);
      const cycleId = typeof req.query.cycleId === "string" ? req.query.cycleId : undefined;

      const expenses = await prisma.welfareExpense.findMany({
        where: { groupId: group.id, ...(cycleId ? { cycleId } : {}) },
        orderBy: { createdAt: "desc" },
        include: {
          ledgerEntry: { select: { amountCents: true, createdAt: true } },
          payeeMember: { select: { id: true, fullName: true } }
        }
      });

      const fund = await prisma.fundAccount.findFirst({
        where: { groupId: group.id, type: "SOCIAL" },
        select: { balanceCents: true }
      });

      const spentCents = expenses.reduce((sum, e) => sum + e.ledgerEntry.amountCents, 0);

      ok(res, {
        group,
        expenses,
        spentCents,
        /**
         * The closing balance — contributions minus expenses. THIS is what
         * share-out distributes, not gross contributions. Named explicitly so
         * no caller has to infer it.
         */
        welfareBalanceCents: fund?.balanceCents ?? 0
      });
    } catch (error) {
      next(error);
    }
  }
);
