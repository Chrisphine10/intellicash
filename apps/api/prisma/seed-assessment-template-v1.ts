import { PrismaClient } from "@prisma/client";
import {
  DEFAULT_TEMPLATE_FAMILY,
  TEMPLATE_STATUS,
  publishTemplate
} from "../src/services/visit-assessment-service";

/**
 * Seeds and publishes v1 of the VSLA field assessment scorecard.
 *
 * **This is content, not code.** The engine computes `maxPoints` from whatever
 * questions are here — there is no 92 anywhere in the contract — so replacing
 * this question set with IWL's real one is an edit to this file and nothing
 * else. If a future set totals 88 or 104, the engine is already right.
 *
 * The set below is a faithful reconstruction of the IWL 92-point instrument:
 * 46 questions across 7 sections, each worth 2 points, scored Yes / Partial /
 * No at 2 / 1 / 0. It is deliberately shaped to the real thing's arithmetic so
 * the pipeline can be exercised end to end, and it should be replaced with the
 * genuine wording when IWL sends the form. Anything already scored under v1
 * keeps its v1 snapshot, so replacing it later is a clone-and-publish, never a
 * rewrite of history.
 *
 * Idempotent: it does nothing if the family already has a published version.
 */

const prisma = new PrismaClient();

type SeedQuestion = { key: string; prompt: string; guidance?: string };
type SeedSection = { key: string; title: string; description: string; questions: SeedQuestion[] };

/** Every question is worth this much. Bands below are derived from the total. */
const POINTS_PER_QUESTION = 2;

const SECTIONS: SeedSection[] = [
  {
    key: "governance",
    title: "Governance and leadership",
    description: "Is the group properly constituted, led and held to its own rules?",
    questions: [
      { key: "constitution_written", prompt: "Does the group have a written constitution?" },
      {
        key: "constitution_understood",
        prompt: "Can members explain the key rules in the constitution?",
        guidance: "Ask two ordinary members, not an official."
      },
      { key: "committee_complete", prompt: "Are all committee positions filled?" },
      { key: "committee_elected", prompt: "Was the committee elected by members?" },
      { key: "elections_scheduled", prompt: "Are elections held at the agreed interval?" },
      { key: "meetings_regular", prompt: "Does the group meet on its agreed schedule?" },
      { key: "quorum_respected", prompt: "Is a quorum required and observed for decisions?" },
      { key: "fines_applied", prompt: "Are fines and penalties applied consistently to all members?" }
    ]
  },
  {
    key: "membership",
    title: "Membership and participation",
    description: "Do members join knowingly, attend, and take part in decisions?",
    questions: [
      { key: "register_current", prompt: "Is the membership register up to date?" },
      { key: "attendance_recorded", prompt: "Is attendance recorded at every meeting?" },
      { key: "attendance_rate", prompt: "Is average attendance at or above the group's target?" },
      { key: "joining_process", prompt: "Is there a clear process for admitting new members?" },
      { key: "exit_process", prompt: "Is there a clear process for a member leaving, including their share-out?" },
      { key: "participation_inclusive", prompt: "Do women and younger members speak and hold positions?" }
    ]
  },
  {
    key: "savings",
    title: "Savings discipline",
    description: "Are share purchases regular, recorded and matched by cash?",
    questions: [
      { key: "share_value_agreed", prompt: "Is the share value agreed and known to members?" },
      { key: "savings_every_meeting", prompt: "Do members buy shares at every meeting?" },
      { key: "savings_recorded_passbook", prompt: "Is every share purchase entered in the member's passbook?" },
      { key: "savings_recorded_ledger", prompt: "Is every share purchase entered in the group ledger?" },
      { key: "passbook_ledger_agree", prompt: "Do passbook totals agree with the ledger?", guidance: "Spot-check three members." },
      { key: "savings_counted_publicly", prompt: "Is money counted in front of the members?" },
      { key: "arrears_followed", prompt: "Are members behind on savings followed up?" }
    ]
  },
  {
    key: "lending",
    title: "Loans and repayment",
    description: "Are loans issued by the rules and repaid on time?",
    questions: [
      { key: "loan_policy_written", prompt: "Is the loan policy written and applied?" },
      { key: "loan_limit_respected", prompt: "Are loans kept within the agreed multiple of savings?" },
      { key: "loan_approved_in_meeting", prompt: "Are loans approved in a meeting, not by an official alone?" },
      { key: "loan_terms_recorded", prompt: "Are the amount, interest and due date recorded for every loan?" },
      { key: "repayment_on_time", prompt: "Are loans repaid on or before the due date?" },
      { key: "defaults_managed", prompt: "Is there a followed process for handling default?" },
      { key: "interest_applied", prompt: "Is interest charged at the agreed rate on every loan?" }
    ]
  },
  {
    key: "record_keeping",
    title: "Record keeping",
    description: "Do the books tell the truth, and could someone else read them?",
    questions: [
      { key: "ledger_current", prompt: "Is the group ledger written up to the last meeting?" },
      { key: "minutes_kept", prompt: "Are minutes recorded for every meeting?" },
      { key: "decisions_recorded", prompt: "Are decisions and votes recorded in the minutes?" },
      { key: "records_legible", prompt: "Are the records legible and free of unexplained alterations?" },
      { key: "records_secure", prompt: "Are records kept somewhere safe between meetings?" },
      { key: "digital_records", prompt: "Is the group recording meetings in IntelliCash?" }
    ]
  },
  {
    key: "financial_management",
    title: "Cash handling and controls",
    description: "Is the money physically safe, and does it reconcile?",
    questions: [
      { key: "cashbox_present", prompt: "Does the group have a lockable cash box?" },
      { key: "three_key_control", prompt: "Is the 3-key control observed, with keys held by three different members?" },
      { key: "cash_reconciles", prompt: "Does cash on hand match the ledger balance?", guidance: "Count it." },
      { key: "bank_or_mobile_account", prompt: "Does the group hold surplus funds in a bank or mobile money account?" },
      { key: "no_single_signatory", prompt: "Do withdrawals require more than one signatory?" },
      { key: "audit_at_share_out", prompt: "Is the cycle audited before share-out?" }
    ]
  },
  {
    key: "social_fund",
    title: "Social fund and member welfare",
    description: "Does the welfare fund exist, work, and stay separate from savings?",
    questions: [
      { key: "social_fund_exists", prompt: "Does the group operate a social fund?" },
      { key: "social_contributions_regular", prompt: "Are social fund contributions collected as agreed?" },
      { key: "social_fund_separate", prompt: "Is the social fund kept separate from the savings fund?" },
      { key: "social_rules_known", prompt: "Do members know what the social fund may be used for?" },
      { key: "social_disbursements_recorded", prompt: "Are social fund payouts recorded with the reason?" },
      { key: "social_fund_used", prompt: "Has the social fund actually been used to help a member when needed?" }
    ]
  }
];

/**
 * Bands as percentages of whatever the questions total, converted to points.
 *
 * Expressed this way so the bands stay right if the question set changes size:
 * a hard-coded 74–92 would silently mis-band the moment a question is added.
 * The boundary arithmetic below tiles [0, maxPoints] exactly, which is what
 * `validateAssessmentTemplate` insists on.
 */
const BAND_THRESHOLDS = [
  { key: "weak", label: "Weak", fromPercent: 0, guidance: "Needs close support. Agree a plan before the next visit." },
  { key: "fair", label: "Fair", fromPercent: 40, guidance: "Functioning, with gaps to close in specific areas." },
  { key: "good", label: "Good", fromPercent: 60, guidance: "Sound practice. Keep the weaker sections moving." },
  { key: "excellent", label: "Excellent", fromPercent: 80, guidance: "Strong across the board. A candidate to mentor other groups." }
];

function bandsFor(maxPoints: number) {
  const starts = BAND_THRESHOLDS.map((band) => ({
    ...band,
    minPoints: Math.ceil((band.fromPercent / 100) * maxPoints)
  }));

  return starts.map((band, index) => {
    const next = starts[index + 1];
    return {
      key: band.key,
      label: band.label,
      minPoints: band.minPoints,
      // Each band runs up to just below the next one's start, and the top band
      // runs to the maximum — so together they cover every attainable score.
      maxPoints: next ? next.minPoints - 1 : maxPoints,
      guidance: band.guidance
    };
  });
}

export async function seedAssessmentTemplateV1(client: PrismaClient = prisma) {
  const existing = await client.assessmentTemplate.findFirst({
    where: { familyKey: DEFAULT_TEMPLATE_FAMILY, status: TEMPLATE_STATUS.published }
  });
  if (existing) {
    return { created: false, templateId: existing.id, version: existing.version };
  }

  const questionCount = SECTIONS.reduce((sum, section) => sum + section.questions.length, 0);
  const maxPoints = questionCount * POINTS_PER_QUESTION;

  const template = await client.assessmentTemplate.create({
    data: {
      familyKey: DEFAULT_TEMPLATE_FAMILY,
      version: 1,
      status: TEMPLATE_STATUS.draft,
      title: "VSLA field assessment",
      description:
        "Completed by the field agent during a group visit. Each question scores Yes (full), Partial (half) or No (none).",
      bandsJson: JSON.stringify(bandsFor(maxPoints)),
      sections: {
        create: SECTIONS.map((section, sectionIndex) => ({
          key: section.key,
          title: section.title,
          description: section.description,
          position: sectionIndex,
          questions: {
            create: section.questions.map((question, questionIndex) => ({
              key: question.key,
              prompt: question.prompt,
              guidance: question.guidance ?? null,
              weight: POINTS_PER_QUESTION,
              position: questionIndex
            }))
          }
        }))
      }
    }
  });

  // Publishing runs the same validation an admin's publish does — so a broken
  // seed fails here, loudly, rather than producing a template nobody can score.
  const published = await publishTemplate({ templateId: template.id });

  return {
    created: true,
    templateId: template.id,
    version: published.template.version,
    questionCount,
    maxPoints: published.template.maxPoints,
    checksum: published.checksum
  };
}

const isDirectRun =
  process.argv[1]?.replace(/\\/g, "/").endsWith("seed-assessment-template-v1.ts") ?? false;

if (isDirectRun) {
  seedAssessmentTemplateV1()
    .then((result) => {
      if (!result.created) {
        console.log(
          `Assessment template already published (v${result.version}). Nothing to do.`
        );
        return;
      }
      console.log(
        `Published assessment template v${result.version}: ${result.questionCount} questions, ${result.maxPoints} points.`
      );
      console.log(`Checksum ${result.checksum}`);
    })
    .catch((error) => {
      console.error(error);
      process.exitCode = 1;
    })
    .finally(() => prisma.$disconnect());
}
