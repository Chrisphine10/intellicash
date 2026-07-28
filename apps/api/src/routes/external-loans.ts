import { Router } from "express";
import { z } from "zod";
import type { Prisma } from "@prisma/client";
import { appendAuditEvent } from "../services/audit-service";
import { requireAuth, type AuthenticatedUser } from "../middleware/auth";
import { partnerScopeForUser, scopeGroupWhere } from "../services/account-scope";
import { computeCreditRating, latestCreditRating } from "../services/credit-rating-service";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";

/**
 * External loans — credit offers ("credit ventures") published by lending
 * partners and surfaced to groups through Intelli-Stores. They live under the
 * `store:*` permission umbrella deliberately: they are sold in the same
 * marketplace, every store-capable role can browse and apply, and the mobile
 * MOBILE_CORE keys already carry the scopes. Decisions (approve / reject /
 * disburse) are additionally restricted to IWL admins and the officers of the
 * lending partner itself.
 */
const router = Router();

const loanCategorySchema = z.enum([
  "WORKING_CAPITAL",
  "ASSET_FINANCE",
  "AGRI_INPUTS",
  "EMERGENCY",
  "EDUCATION",
  "BUSINESS_GROWTH"
]);

const bandOrder = ["A", "B", "C", "D"] as const;

const productCreateSchema = z.object({
  partnerId: z.string().min(1),
  name: z.string().min(3),
  category: loanCategorySchema.default("WORKING_CAPITAL"),
  description: z.string().min(10),
  minAmountCents: z.number().int().positive(),
  maxAmountCents: z.number().int().positive(),
  interestRateBps: z.number().int().min(0).max(100_000),
  interestPeriod: z.enum(["PER_YEAR", "PER_MONTH", "FLAT"]).default("PER_YEAR"),
  termMonths: z.number().int().min(1).max(120),
  repaymentFrequency: z.enum(["WEEKLY", "BIWEEKLY", "MONTHLY"]).default("MONTHLY"),
  minCreditBand: z.enum(bandOrder).nullish(),
  requirements: z.string().nullish(),
  status: z.enum(["ACTIVE", "INACTIVE", "ARCHIVED"]).default("ACTIVE")
});

const productUpdateSchema = productCreateSchema.partial().omit({ partnerId: true });

const applicationCreateSchema = z.object({
  productId: z.string().min(1),
  amountCents: z.number().int().positive(),
  purpose: z.string().min(5),
  groupId: z.string().min(1).optional(),
  meetingId: z.string().min(1).optional()
});

const decisionSchema = z.object({
  action: z.enum(["APPROVE", "REJECT"]),
  notes: z.string().nullish()
});

function slugify(name: string) {
  return `${name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")}-${Date.now().toString(36)}`;
}

/** True when the band meets the product's minimum (A best … D worst). */
function bandMeetsMinimum(band: string | null, minBand: string | null) {
  if (!minBand) return true;
  if (!band || !bandOrder.includes(band as (typeof bandOrder)[number])) return false;
  return bandOrder.indexOf(band as (typeof bandOrder)[number]) <= bandOrder.indexOf(minBand as (typeof bandOrder)[number]);
}

function assertCanManageLoanCatalog(user: AuthenticatedUser | undefined) {
  if (!user || (user.role !== "IWL_ADMIN" && user.role !== "PARTNER_OFFICER")) {
    throw new ApiHttpError(403, "FORBIDDEN", "Only IWL admins or partner officers manage external loan products.");
  }
}

async function assertCanDecide(user: AuthenticatedUser | undefined, partnerId: string) {
  assertCanManageLoanCatalog(user);
  if (user?.role === "PARTNER_OFFICER") {
    const partner = await prisma.partner.findFirst({
      where: { AND: [partnerScopeForUser(user), { id: partnerId }] },
      select: { id: true }
    });
    if (!partner) {
      throw new ApiHttpError(403, "FORBIDDEN", "This loan product belongs to another lending partner.");
    }
  }
}

const productInclude = {
  partner: { select: { id: true, name: true, type: true } },
  _count: { select: { applications: true } }
} satisfies Prisma.ExternalLoanProductInclude;

router.get("/external-loans/products", requireAuth("store:read"), async (req, res, next) => {
  try {
    const category = req.query.category ? loanCategorySchema.parse(req.query.category) : undefined;
    const includeInactive = req.query.includeInactive === "1" && req.user?.role === "IWL_ADMIN";
    const products = await prisma.externalLoanProduct.findMany({
      where: {
        ...(includeInactive ? {} : { status: "ACTIVE" }),
        ...(category ? { category } : {})
      },
      include: productInclude,
      orderBy: { createdAt: "desc" }
    });
    ok(res, products);
  } catch (error) {
    next(error);
  }
});

router.get("/external-loans/products/:id", requireAuth("store:read"), async (req, res, next) => {
  try {
    const product = await prisma.externalLoanProduct.findUnique({
      where: { id: z.string().parse(req.params.id) },
      include: productInclude
    });
    if (!product) throw new ApiHttpError(404, "NOT_FOUND", "Loan product not found.");
    ok(res, product);
  } catch (error) {
    next(error);
  }
});

router.post("/external-loans/products", requireAuth("store:write"), async (req, res, next) => {
  try {
    assertCanManageLoanCatalog(req.user);
    const body = productCreateSchema.parse(req.body);
    if (body.maxAmountCents < body.minAmountCents) {
      throw new ApiHttpError(400, "INVALID_RANGE", "Maximum amount must be at least the minimum amount.");
    }
    await assertCanDecide(req.user, body.partnerId);

    const product = await prisma.externalLoanProduct.create({
      data: { ...body, slug: slugify(body.name) },
      include: productInclude
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "EXTERNAL_LOAN_PRODUCT",
      entityId: product.id,
      type: "EXTERNAL_LOAN_PRODUCT_CREATED",
      payload: product
    });

    ok(res.status(201), product);
  } catch (error) {
    next(error);
  }
});

router.patch("/external-loans/products/:id", requireAuth("store:write"), async (req, res, next) => {
  try {
    const productId = z.string().parse(req.params.id);
    const existing = await prisma.externalLoanProduct.findUnique({ where: { id: productId } });
    if (!existing) throw new ApiHttpError(404, "NOT_FOUND", "Loan product not found.");
    await assertCanDecide(req.user, existing.partnerId);

    const body = productUpdateSchema.parse(req.body);
    const product = await prisma.externalLoanProduct.update({
      where: { id: productId },
      data: body,
      include: productInclude
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "EXTERNAL_LOAN_PRODUCT",
      entityId: product.id,
      type: "EXTERNAL_LOAN_PRODUCT_UPDATED",
      payload: product
    });

    ok(res, product);
  } catch (error) {
    next(error);
  }
});

const applicationInclude = {
  product: {
    select: {
      id: true,
      name: true,
      category: true,
      interestRateBps: true,
      interestPeriod: true,
      termMonths: true,
      repaymentFrequency: true,
      partner: { select: { id: true, name: true } }
    }
  },
  group: { select: { id: true, name: true, code: true } }
} satisfies Prisma.ExternalLoanApplicationInclude;

router.post("/external-loans/applications", requireAuth("store:write"), async (req, res, next) => {
  try {
    const user = req.user;
    const body = applicationCreateSchema.parse(req.body);

    const product = await prisma.externalLoanProduct.findUnique({
      where: { id: body.productId },
      include: { partner: { select: { id: true, name: true } } }
    });
    if (!product || product.status !== "ACTIVE") {
      throw new ApiHttpError(404, "NOT_FOUND", "Loan product not found or not open for applications.");
    }

    // External loans are group facilities: resolve the applying group the same
    // way the store resolves a buying group, then scope-check it.
    const groupId =
      body.groupId ??
      (user?.role === "MEMBER" || user?.role === "GROUP_ACCOUNT" ? user?.groupId ?? undefined : undefined);
    if (!groupId) {
      throw new ApiHttpError(400, "GROUP_REQUIRED", "An external loan application must be made for a group.");
    }
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(user, { id: groupId }),
      select: { id: true, name: true }
    });
    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    }

    if (body.amountCents < product.minAmountCents || body.amountCents > product.maxAmountCents) {
      throw new ApiHttpError(
        400,
        "AMOUNT_OUT_OF_RANGE",
        `${product.name} lends between ${product.minAmountCents / 100} and ${product.maxAmountCents / 100} KES.`
      );
    }

    if (body.meetingId) {
      const meeting = await prisma.meeting.findFirst({
        where: { id: body.meetingId, groupId: group.id },
        select: { id: true }
      });
      if (!meeting) {
        throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist in this group.");
      }
    }

    // Freeze the group's band at application time and enforce the lender's
    // minimum band, mirroring the store's credit-rating-based pricing.
    const rating = (await latestCreditRating(group.id)) ?? (await computeCreditRating(group.id));
    if (!bandMeetsMinimum(rating.rated ? rating.band : null, product.minCreditBand)) {
      throw new ApiHttpError(
        400,
        "BAND_BELOW_MINIMUM",
        `${product.name} requires a credit rating of band ${product.minCreditBand} or better; ` +
          `${group.name} is currently ${rating.rated ? `band ${rating.band}` : "unrated"}.`
      );
    }

    const application = await prisma.externalLoanApplication.create({
      data: {
        productId: product.id,
        groupId: group.id,
        meetingId: body.meetingId,
        requesterUserId: user?.id,
        amountCents: body.amountCents,
        purpose: body.purpose,
        creditBand: rating.rated ? rating.band : null
      },
      include: applicationInclude
    });

    await appendAuditEvent({
      actorUserId: user?.id,
      entityType: "EXTERNAL_LOAN_APPLICATION",
      entityId: application.id,
      type: "EXTERNAL_LOAN_APPLICATION_CREATED",
      payload: application
    });

    ok(res.status(201), application);
  } catch (error) {
    next(error);
  }
});

router.get("/external-loans/applications", requireAuth("store:read"), async (req, res, next) => {
  try {
    const user = req.user;
    // Admins see everything; partner officers see applications on their own
    // partner's products; everyone else is scoped to groups they can act for.
    const where: Prisma.ExternalLoanApplicationWhereInput =
      user?.role === "IWL_ADMIN"
        ? {}
        : user?.role === "PARTNER_OFFICER"
          ? { product: { partner: partnerScopeForUser(user) } }
          : { group: scopeGroupWhere(user, {}) };
    const groupId = typeof req.query.groupId === "string" ? req.query.groupId : undefined;
    const applications = await prisma.externalLoanApplication.findMany({
      where: { ...where, ...(groupId ? { groupId } : {}) },
      include: applicationInclude,
      orderBy: { createdAt: "desc" },
      take: 200
    });
    ok(res, applications);
  } catch (error) {
    next(error);
  }
});

router.post("/external-loans/applications/:id/decision", requireAuth("store:write"), async (req, res, next) => {
  try {
    const applicationId = z.string().parse(req.params.id);
    const body = decisionSchema.parse(req.body);
    const existing = await prisma.externalLoanApplication.findUnique({
      where: { id: applicationId },
      include: { product: { select: { partnerId: true } } }
    });
    if (!existing) throw new ApiHttpError(404, "NOT_FOUND", "Application not found.");
    await assertCanDecide(req.user, existing.product.partnerId);
    if (existing.status !== "PENDING") {
      throw new ApiHttpError(409, "ALREADY_DECIDED", `Application is already ${existing.status.toLowerCase()}.`);
    }

    const application = await prisma.externalLoanApplication.update({
      where: { id: applicationId },
      data: {
        status: body.action === "APPROVE" ? "APPROVED" : "REJECTED",
        reviewNotes: body.notes,
        decidedAt: new Date()
      },
      include: applicationInclude
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "EXTERNAL_LOAN_APPLICATION",
      entityId: application.id,
      type: "EXTERNAL_LOAN_APPLICATION_DECIDED",
      payload: application
    });

    ok(res, application);
  } catch (error) {
    next(error);
  }
});

router.post("/external-loans/applications/:id/disburse", requireAuth("store:write"), async (req, res, next) => {
  try {
    const applicationId = z.string().parse(req.params.id);
    const existing = await prisma.externalLoanApplication.findUnique({
      where: { id: applicationId },
      include: { product: { select: { partnerId: true } } }
    });
    if (!existing) throw new ApiHttpError(404, "NOT_FOUND", "Application not found.");
    await assertCanDecide(req.user, existing.product.partnerId);
    if (existing.status !== "APPROVED") {
      throw new ApiHttpError(409, "NOT_APPROVED", "Only approved applications can be disbursed.");
    }

    // Marks the milestone only — actual money movement runs on the lender's
    // rails (or the partner wallet once payments go live).
    const application = await prisma.externalLoanApplication.update({
      where: { id: applicationId },
      data: { status: "DISBURSED", disbursedAt: new Date() },
      include: applicationInclude
    });

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "EXTERNAL_LOAN_APPLICATION",
      entityId: application.id,
      type: "EXTERNAL_LOAN_DISBURSED",
      payload: application
    });

    ok(res, application);
  } catch (error) {
    next(error);
  }
});

export { router as externalLoansRouter };
