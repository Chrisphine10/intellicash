import type { Prisma, PrismaClient } from "@prisma/client";

import { ApiHttpError } from "../lib/http";

type Tx = Prisma.TransactionClient | PrismaClient;

/**
 * Which programmes a village agent serves.
 *
 * An agent used to carry a single `programmeId`, which meant somebody setting
 * up an agent who works across three of a partner's programmes had to pick one
 * and drop the other two on the floor. Programmes now live in a join table.
 *
 * One rule governs the set: **every programme must belong to the same partner**.
 * An agent is engaged by a partner and then deployed across that partner's
 * work; letting one agent straddle two partners would put another partner's
 * groups, ratings and visit notes inside their caseload, which is a
 * confidentiality problem rather than a tidiness one.
 *
 * The rule is enforced here rather than in the database. SQLite can express it
 * only through composite foreign keys, which would make every ordinary read of
 * the join table more awkward than the rule is worth — and this is the single
 * place programmes are ever attached to an agent, so there is one door to
 * guard.
 */

export type ProgrammeAssignment = {
  agentId: string;
  /** The full set of programmes the agent should serve after this call. */
  programmeIds: string[];
};

/**
 * Replaces an agent's programmes, and returns the partner they all belong to.
 *
 * The set is applied whole rather than added to: the caller sends the state it
 * wants, which makes an accidental partial update impossible to express.
 * Passing an empty array detaches the agent from every programme and leaves
 * their partner as it was — an agent between assignments is a normal state,
 * not an error.
 */
export async function setAgentProgrammes(
  tx: Tx,
  { agentId, programmeIds }: ProgrammeAssignment
): Promise<{ partnerId: string | null }> {
  const wanted = [...new Set(programmeIds)];

  if (wanted.length === 0) {
    await tx.villageAgentProgramme.deleteMany({ where: { villageAgentId: agentId } });
    return { partnerId: null };
  }

  const programmes = await tx.programme.findMany({
    where: { id: { in: wanted } },
    select: { id: true, name: true, partnerId: true }
  });

  const missing = wanted.filter((id) => !programmes.some((p) => p.id === id));
  if (missing.length > 0) {
    throw new ApiHttpError(
      404,
      "PROGRAMME_NOT_FOUND",
      `No programme exists with id ${missing.join(", ")}.`
    );
  }

  // The rule. Reported with the programme names rather than ids, because the
  // person reading this is choosing from a list of names.
  const partnerIds = [...new Set(programmes.map((p) => p.partnerId))];
  if (partnerIds.length > 1) {
    throw new ApiHttpError(
      400,
      "AGENT_PROGRAMMES_CROSS_PARTNER",
      "An agent can serve several programmes, but they must all belong to one " +
        `partner. These belong to ${partnerIds.length} different partners: ` +
        `${programmes.map((p) => p.name).join(", ")}.`
    );
  }

  const partnerId = partnerIds[0]!;

  await tx.villageAgentProgramme.deleteMany({
    where: { villageAgentId: agentId, programmeId: { notIn: wanted } }
  });

  // Only the genuinely new links are created, rather than deleting them all
  // and re-inserting: that keeps `createdAt` on links that were already there,
  // so "since when has this agent covered this programme" survives an
  // unrelated edit to their phone number.
  //
  // Written as a read plus targeted creates because `createMany` has no
  // `skipDuplicates` on SQLite.
  const existing = await tx.villageAgentProgramme.findMany({
    where: { villageAgentId: agentId },
    select: { programmeId: true }
  });
  const alreadyLinked = new Set(existing.map((link) => link.programmeId));

  for (const programmeId of wanted) {
    if (alreadyLinked.has(programmeId)) continue;
    await tx.villageAgentProgramme.create({ data: { villageAgentId: agentId, programmeId } });
  }

  return { partnerId };
}

/**
 * The programmes an agent serves, as a plain list of ids.
 *
 * Used where the old code read `agent.programmeId` and needs the set instead.
 */
export async function agentProgrammeIds(tx: Tx, agentId: string): Promise<string[]> {
  const links = await tx.villageAgentProgramme.findMany({
    where: { villageAgentId: agentId },
    select: { programmeId: true },
    orderBy: { createdAt: "asc" }
  });
  return links.map((link) => link.programmeId);
}

/**
 * Resolves which programme a piece of work belongs to.
 *
 * Where the old code could fall back to `agent.programmeId`, there may now be
 * several. The rules, in order:
 *
 *   - an explicit choice always wins, but must be one the agent serves;
 *   - exactly one programme means no choice to make;
 *   - more than one, and nothing chosen, is an error rather than a guess —
 *     filing a credit request against the wrong programme is not something the
 *     person would notice, and it moves real money.
 */
export async function resolveAgentProgramme(
  tx: Tx,
  agentId: string,
  requested?: string | null
): Promise<string> {
  const served = await agentProgrammeIds(tx, agentId);

  if (requested) {
    if (served.length > 0 && !served.includes(requested)) {
      throw new ApiHttpError(
        400,
        "AGENT_PROGRAMME_MISMATCH",
        "That agent does not serve the programme this request names."
      );
    }
    return requested;
  }

  if (served.length === 1) return served[0]!;

  if (served.length === 0) {
    throw new ApiHttpError(
      400,
      "AGENT_HAS_NO_PROGRAMME",
      "This agent is not attached to a programme yet, so there is nothing to " +
        "file this against. Assign one first, or name the programme explicitly."
    );
  }

  throw new ApiHttpError(
    400,
    "AGENT_PROGRAMME_REQUIRED",
    `This agent serves ${served.length} programmes, so the programme has to be ` +
      "chosen rather than assumed."
  );
}
