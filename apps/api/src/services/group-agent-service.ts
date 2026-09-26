/**
 * Which village agents / CBTs serve a group.
 *
 * A group can have several agents and each one has the same access to it
 * (`GroupAgent`). One is the lead: reports name them first, and
 * `Group.villageAgentId` mirrors the lead so older phones, imports and exports
 * that read the single column keep working. Every write goes through here so
 * the mirror can never drift from the links.
 */

import type { Prisma } from "@prisma/client";

type Tx = Prisma.TransactionClient;

/**
 * A group whose agent was set through the single column only (an older path,
 * an import) gets that agent as a lead link BEFORE anything changes, so a
 * later edit never drops them by accident.
 */
async function adoptLegacyLead(tx: Tx, groupId: string) {
  const group = await tx.group.findUnique({ where: { id: groupId }, select: { villageAgentId: true } });
  if (!group?.villageAgentId) return;
  const anyLead = await tx.groupAgent.findFirst({ where: { groupId, isLead: true }, select: { id: true } });
  await tx.groupAgent.upsert({
    where: { groupId_villageAgentId: { groupId, villageAgentId: group.villageAgentId } },
    create: { groupId, villageAgentId: group.villageAgentId, isLead: !anyLead },
    update: {}
  });
}

/** Point `Group.villageAgentId` at the lead link (or any link, or nothing). */
export async function syncLeadMirror(tx: Tx, groupId: string) {
  const links = await tx.groupAgent.findMany({
    where: { groupId },
    orderBy: [{ isLead: "desc" }, { createdAt: "asc" }],
    select: { id: true, villageAgentId: true, isLead: true }
  });
  const lead = links[0] ?? null;
  if (lead && !lead.isLead) {
    await tx.groupAgent.update({ where: { id: lead.id }, data: { isLead: true } });
  }
  // Exactly one lead.
  if (lead) {
    await tx.groupAgent.updateMany({
      where: { groupId, id: { not: lead.id }, isLead: true },
      data: { isLead: false }
    });
  }
  await tx.group.update({
    where: { id: groupId },
    data: { villageAgentId: lead?.villageAgentId ?? null }
  });
}

/**
 * Sets the full list of agents for one group. [leadAgentId] must be one of
 * [agentIds]; when omitted the current lead stays if still listed, otherwise
 * the first listed agent leads.
 */
export async function setGroupAgents(tx: Tx, groupId: string, agentIds: string[], leadAgentId?: string | null) {
  const wanted = Array.from(new Set(agentIds));
  await adoptLegacyLead(tx, groupId);
  await tx.groupAgent.deleteMany({
    where: { groupId, villageAgentId: { notIn: wanted.length > 0 ? wanted : ["__none__"] } }
  });
  const existing = new Set(
    (await tx.groupAgent.findMany({ where: { groupId }, select: { villageAgentId: true } })).map(
      (link) => link.villageAgentId
    )
  );
  for (const villageAgentId of wanted) {
    if (!existing.has(villageAgentId)) {
      await tx.groupAgent.create({ data: { groupId, villageAgentId, isLead: false } });
    }
  }
  if (leadAgentId && wanted.includes(leadAgentId)) {
    await tx.groupAgent.updateMany({ where: { groupId }, data: { isLead: false } });
    await tx.groupAgent.update({
      where: { groupId_villageAgentId: { groupId, villageAgentId: leadAgentId } },
      data: { isLead: true }
    });
  } else if (wanted.length > 0 && !(await tx.groupAgent.findFirst({ where: { groupId, isLead: true } }))) {
    await tx.groupAgent.update({
      where: { groupId_villageAgentId: { groupId, villageAgentId: wanted[0]! } },
      data: { isLead: true }
    });
  }
  await syncLeadMirror(tx, groupId);
}

/** Adds one agent to a group without touching the group's other agents. */
export async function addGroupAgent(tx: Tx, groupId: string, villageAgentId: string) {
  await adoptLegacyLead(tx, groupId);
  await tx.groupAgent.upsert({
    where: { groupId_villageAgentId: { groupId, villageAgentId } },
    create: { groupId, villageAgentId, isLead: false },
    update: {}
  });
  await syncLeadMirror(tx, groupId);
}

/**
 * Sets one agent's caseload. Adding a group never removes the group's other
 * agents; the old behaviour (reassigning the single column) silently took a
 * group away from whoever served it before.
 */
export async function setAgentCaseload(tx: Tx, villageAgentId: string, groupIds: string[]) {
  const wanted = Array.from(new Set(groupIds));
  const current = await tx.groupAgent.findMany({ where: { villageAgentId }, select: { groupId: true } });
  // Groups still pointing at this agent only through the legacy column.
  const legacy = await tx.group.findMany({ where: { villageAgentId }, select: { id: true } });
  const touched = new Set([...current.map((link) => link.groupId), ...legacy.map((group) => group.id), ...wanted]);
  for (const groupId of touched) await adoptLegacyLead(tx, groupId);

  await tx.groupAgent.deleteMany({
    where: { villageAgentId, groupId: { notIn: wanted.length > 0 ? wanted : ["__none__"] } }
  });
  for (const groupId of wanted) {
    await tx.groupAgent.upsert({
      where: { groupId_villageAgentId: { groupId, villageAgentId } },
      create: { groupId, villageAgentId, isLead: false },
      update: {}
    });
  }
  for (const groupId of touched) {
    await syncLeadMirror(tx, groupId);
  }
}

/** The agents serving a group, lead first — for responses. */
export const groupAgentLinksInclude = {
  orderBy: [{ isLead: "desc" }, { createdAt: "asc" }],
  select: {
    isLead: true,
    villageAgent: { select: { id: true, name: true, phone: true, email: true, county: true, status: true } }
  }
} satisfies Prisma.Group$agentLinksArgs;
