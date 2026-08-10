import { PrismaClient } from "@prisma/client";

/**
 * Coaching topics and the axes a group scores the coaching on.
 *
 * Content, not code — these are rows an admin edits, and the list below is a
 * starting set rather than a fixed vocabulary. Keys are stable because sessions
 * snapshot them and cross-visit reporting joins on them; titles are free to
 * change.
 *
 * Idempotent: safe to run on every deploy. Existing rows keep whatever an admin
 * has since edited them to, and only genuinely new keys are inserted.
 */

const prisma = new PrismaClient();

const TOPICS = [
  {
    key: "record_keeping",
    title: "Record keeping",
    description: "Writing up the ledger and passbooks so they agree with the cash."
  },
  {
    key: "loan_management",
    title: "Loan management",
    description: "Appraising, approving and following up loans within the group's own policy."
  },
  {
    key: "governance",
    title: "Governance and leadership",
    description: "Running meetings, holding elections, applying the constitution evenly."
  },
  {
    key: "conflict_resolution",
    title: "Conflict resolution",
    description: "Handling disputes and default without the group splitting."
  },
  {
    key: "financial_literacy",
    title: "Financial literacy",
    description: "Budgeting, saving goals, and understanding interest."
  },
  {
    key: "business_skills",
    title: "Business skills",
    description: "Pricing, record separation, and reinvesting in a member's enterprise."
  },
  {
    key: "digital_tools",
    title: "Using Intelli-Cash",
    description: "Recording meetings on the phone and keeping the group's data current."
  },
  {
    key: "social_fund",
    title: "Social fund and welfare",
    description: "Running the welfare fund fairly and keeping it separate from savings."
  }
];

const DIMENSIONS = [
  { key: "clarity", title: "Was the advice clear?" },
  { key: "usefulness", title: "Was it useful to the group?" },
  { key: "respect", title: "Were you treated with respect?" },
  { key: "preparedness", title: "Was the agent prepared?" }
];

export async function seedMentorshipReferenceData(client: PrismaClient = prisma) {
  let topicsCreated = 0;
  for (const [index, topic] of TOPICS.entries()) {
    const result = await client.mentorshipTopic.upsert({
      where: { key: topic.key },
      // `update: {}` on purpose. A deploy must not undo an admin's edits to a
      // title or reinstate a topic they deliberately retired.
      update: {},
      create: { ...topic, position: index }
    });
    if (result.createdAt.getTime() === result.updatedAt.getTime()) topicsCreated += 1;
  }

  let dimensionsCreated = 0;
  for (const [index, dimension] of DIMENSIONS.entries()) {
    const existing = await client.mentorshipRatingDimension.findUnique({
      where: { key: dimension.key }
    });
    if (existing) continue;
    await client.mentorshipRatingDimension.create({
      data: { ...dimension, position: index }
    });
    dimensionsCreated += 1;
  }

  return { topicsCreated, dimensionsCreated };
}

const isDirectRun =
  process.argv[1]?.replace(/\\/g, "/").endsWith("seed-mentorship-topics.ts") ?? false;

if (isDirectRun) {
  seedMentorshipReferenceData()
    .then((result) => {
      console.log(
        `Mentorship reference data ready: ${result.topicsCreated} new topics, ${result.dimensionsCreated} new rating dimensions.`
      );
    })
    .catch((error) => {
      console.error(error);
      process.exitCode = 1;
    })
    .finally(() => prisma.$disconnect());
}
