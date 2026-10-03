import { Router } from "express";
import { isOversightRole, seesFieldComments } from "@intellicash/shared";
import { requireAuth } from "../middleware/auth";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";
import { scopeGroupWhere } from "../services/account-scope";
import type { AssessmentScore } from "../domain/visit-assessment-contract";

export const needsAssessmentsRouter = Router();

/** Answers about named people: the group's and IWL's to see, not a partner's. */
const PERSONAL_SECTIONS = ["leadership", "signOff"];
const PERSONAL_ANSWERS = ["Safeguarding or Protection Concerns"];
/**
 * The field team's own remarks — what the enumerator observed and recommended.
 * IWL staff and the CBTs who visit see them; partners and the group do not.
 * The automatic quality checks go to the system issue log, not to readers.
 */
const COMMENT_SECTIONS = ["observations"];

/**
 * A group's needs assessments, oldest first — the first is its baseline.
 *
 * Figures are as the group reported them, and say so. Partners, lenders and
 * read-only viewers get the group-level profile without leaders' names or
 * safeguarding notes (they see groups, not the people in them).
 */
needsAssessmentsRouter.get("/groups/:groupId/needs-assessments", requireAuth("groups:read"), async (req, res, next) => {
  try {
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: String(req.params.groupId) }),
      select: { id: true }
    });
    if (!group) throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside your access.");

    const viewOnly = isOversightRole(req.user?.role);
    const comments = seesFieldComments(req.user?.role);
    const rows = await prisma.groupNeedsAssessment.findMany({
      where: { groupId: group.id },
      orderBy: { assessedOn: "asc" },
      include: {
        visit: {
          select: {
            id: true,
            visitType: true,
            assessment: { select: { percentage: true, bandLabel: true, templateVersion: true, breakdownJson: true } }
          }
        }
      }
    });

    ok(
      res,
      rows.map((row, index) => {
        const answers = JSON.parse(row.answersJson) as Record<string, Record<string, string>>;
        if (viewOnly) {
          for (const section of PERSONAL_SECTIONS) delete answers[section];
          for (const section of Object.values(answers)) for (const label of PERSONAL_ANSWERS) delete section[label];
        }
        if (!comments) for (const section of COMMENT_SECTIONS) delete answers[section];
        const score = row.visit.assessment
          ? (JSON.parse(row.visit.assessment.breakdownJson) as AssessmentScore)
          : null;
        const questions = score?.sections.flatMap((section) => section.questions) ?? [];
        const { answersJson: _answers, qualityFlagsJson, visit, ...fields } = row;
        void _answers;
        return {
          ...fields,
          baseline: index === 0,
          fieldOfficer: comments ? row.fieldOfficer : null,
          enumerator: comments ? row.enumerator : null,
          answers,
          // Only for the people who can fix them; everyone else gets an empty list.
          qualityFlags: comments ? (JSON.parse(qualityFlagsJson) as string[]) : [],
          visitId: visit.id,
          scorecard: visit.assessment
            ? {
                percentage: visit.assessment.percentage,
                band: visit.assessment.bandLabel,
                templateVersion: visit.assessment.templateVersion,
                asked: questions.filter((question) => question.answered && !question.excluded).length,
                total: questions.length
              }
            : null
        };
      })
    );
  } catch (error) {
    next(error);
  }
});
