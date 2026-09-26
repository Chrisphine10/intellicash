/**
 * Account scoping — the permission boundary every query runs against.
 *
 * Every list and read in the API begins here: given a logged-in user, these
 * functions produce a Prisma `WHERE` clause that limits the rows to exactly
 * the groups, programmes, partners, members, agents and ledger entries that
 * account is allowed to see. A partner sees their programmes and the groups
 * on them; a village agent sees their caseload; a member sees their own
 * rows. Anything not matched is filtered out at the database, not hidden in
 * the UI, so a bug here is a data leak rather than a rendering glitch.
 *
 * The rule for new roles is: add an explicit branch, or the role silently
 * inherits platform-wide read access. The fall-through at the bottom of every
 * scope function is deliberate and documented — it is the door a forgotten
 * role walks through.
 */

import type { Prisma } from "@prisma/client";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";

function andWhere<T>(where: T | undefined, scope: T): T {
  if (!where || Object.keys(where as Record<string, unknown>).length === 0) {
    return scope;
  }

  return { AND: [where, scope] } as T;
}

function impossibleGroupScope(): Prisma.GroupWhereInput {
  return { id: "__no_access__" };
}

/**
 * The groups one agent serves. A group can have several agents (`GroupAgent`);
 * `Group.villageAgentId` is the lead's mirror and is kept in the filter so a
 * group written by an older path, before its link existed, is not lost.
 */
export function agentCaseloadWhere(villageAgentId: string): Prisma.GroupWhereInput {
  return {
    OR: [{ agentLinks: { some: { villageAgentId } } }, { villageAgentId }]
  };
}

export function groupScopeForUser(user?: AuthenticatedUser): Prisma.GroupWhereInput {
  if (!user) return impossibleGroupScope();

  if (user.role === "PARTNER_OFFICER") {
    return user.partnerId
      ? {
          OR: [
            {
              programme: {
                OR: [
                  { partnerId: user.partnerId },
                  { partnerLinks: { some: { partnerId: user.partnerId } } }
                ]
              }
            },
            {
              programmeLinks: {
                some: {
                  programme: {
                    OR: [
                      { partnerId: user.partnerId },
                      { partnerLinks: { some: { partnerId: user.partnerId } } }
                    ]
                  }
                }
              }
            }
          ]
        }
      : impossibleGroupScope();
  }

  if (user.role === "LENDER") {
    return user.partnerId
      ? {
          OR: [
            {
              programme: {
                partnerLinks: { some: { partnerId: user.partnerId, role: "LENDER" } }
              }
            },
            {
              programmeLinks: {
                some: {
                  programme: {
                    partnerLinks: { some: { partnerId: user.partnerId, role: "LENDER" } }
                  }
                }
              }
            }
          ]
        }
      : impossibleGroupScope();
  }

  if (user.role === "GROUP_ACCOUNT") {
    return user.groupId ? { id: user.groupId } : impossibleGroupScope();
  }

  if (user.role === "MEMBER") {
    return user.memberId ? { members: { some: { id: user.memberId } } } : impossibleGroupScope();
  }

  // A village agent / CBT sees exactly their caseload: every group they are
  // linked to, alongside any other agents serving the same group.
  if (user.role === "VILLAGE_AGENT") {
    return user.villageAgentId ? agentCaseloadWhere(user.villageAgentId) : impossibleGroupScope();
  }

  // Fall-through is platform-wide (IWL_ADMIN / READ_ONLY). Any new role MUST
  // get an explicit branch above, or it silently inherits full read access.
  return {};
}

/**
 * Which group a group-side account speaks for, as a Group filter.
 *
 * GROUP_ACCOUNT is bound to its group directly. A MEMBER is bound through the
 * membership currently in view — never through `User.groupId`, which outlives
 * removal from the roster and would otherwise leak the former group.
 * Null means the account speaks for no group at all.
 */
function memberGroupWhere(user: AuthenticatedUser): Prisma.GroupWhereInput | null {
  if (user.role === "GROUP_ACCOUNT") return user.groupId ? { id: user.groupId } : null;
  if (user.role === "MEMBER") {
    return user.memberId ? { members: { some: { id: user.memberId } } } : null;
  }
  return null;
}

export function scopeGroupWhere(
  user: AuthenticatedUser | undefined,
  where?: Prisma.GroupWhereInput
): Prisma.GroupWhereInput {
  return andWhere(where, groupScopeForUser(user));
}

export function programmeScopeForUser(user?: AuthenticatedUser): Prisma.ProgrammeWhereInput {
  if (!user) return { id: "__no_access__" };

  if (user.role === "PARTNER_OFFICER") {
    return user.partnerId
      ? {
          OR: [
            { partnerId: user.partnerId },
            { partnerLinks: { some: { partnerId: user.partnerId } } }
          ]
        }
      : { id: "__no_access__" };
  }

  if (user.role === "LENDER") {
    return user.partnerId
      ? { partnerLinks: { some: { partnerId: user.partnerId, role: "LENDER" } } }
      : { id: "__no_access__" };
  }

  if (user.role === "GROUP_ACCOUNT" || user.role === "MEMBER") {
    // A MEMBER must resolve through the membership itself. `User.groupId`
    // survives being taken off a roster (the Member row cascades, the pointer
    // does not), so trusting it here would keep a removed member reading their
    // former group's programmes.
    const groupWhere = memberGroupWhere(user);
    return groupWhere ? { OR: [{ groups: { some: groupWhere } }, { groupLinks: { some: { group: groupWhere } } }] } : { id: "__no_access__" };
  }

  // Programmes the agent's own caseload belongs to.
  if (user.role === "VILLAGE_AGENT") {
    return user.villageAgentId
      ? {
          OR: [
            { villageAgentLinks: { some: { villageAgentId: user.villageAgentId } } },
            { groups: { some: agentCaseloadWhere(user.villageAgentId) } },
            { groupLinks: { some: { group: agentCaseloadWhere(user.villageAgentId) } } }
          ]
        }
      : { id: "__no_access__" };
  }

  return {};
}

export function partnerScopeForUser(user?: AuthenticatedUser): Prisma.PartnerWhereInput {
  if (!user) return { id: "__no_access__" };

  if (user.role === "PARTNER_OFFICER") {
    return user.partnerId
      ? {
          OR: [
            { id: user.partnerId },
            { programmeLinks: { some: { programme: { partnerId: user.partnerId } } } },
            { programmeLinks: { some: { programme: { partnerLinks: { some: { partnerId: user.partnerId } } } } } }
          ]
        }
      : { id: "__no_access__" };
  }

  if (user.role === "LENDER") {
    return user.partnerId ? { id: user.partnerId } : { id: "__no_access__" };
  }

  if (user.role === "GROUP_ACCOUNT" || user.role === "MEMBER") {
    const groupWhere = memberGroupWhere(user);
    return groupWhere
      ? {
          programmeLinks: {
            some: {
              programme: {
                OR: [
                  { groups: { some: groupWhere } },
                  { groupLinks: { some: { group: groupWhere } } }
                ]
              }
            }
          }
        }
      : { id: "__no_access__" };
  }

  // An agent has no partner-level visibility.
  if (user.role === "VILLAGE_AGENT") {
    return { id: "__no_access__" };
  }

  return {};
}

export function villageAgentScopeForUser(user?: AuthenticatedUser): Prisma.VillageAgentWhereInput {
  if (!user) return { id: "__no_access__" };

  // An agent may only ever see their own agent profile.
  if (user.role === "VILLAGE_AGENT") {
    return user.villageAgentId ? { id: user.villageAgentId } : { id: "__no_access__" };
  }

  if (user.role === "PARTNER_OFFICER") {
    return user.partnerId
      ? {
          // An agent is in a partner's scope when they serve at least one of
          // that partner's programmes. `partnerId` on the agent says the same
          // thing, but the link is the authority and this keeps working for
          // rows whose partner has not been backfilled.
          programmeLinks: {
            some: {
              programme: {
                OR: [
                  { partnerId: user.partnerId },
                  { partnerLinks: { some: { partnerId: user.partnerId } } }
                ]
              }
            }
          }
        }
      : { id: "__no_access__" };
  }

  // A lender sees the agents serving the programmes it lends to.
  if (user.role === "LENDER") {
    return user.partnerId
      ? {
          programmeLinks: {
            some: { programme: { partnerLinks: { some: { partnerId: user.partnerId, role: "LENDER" } } } }
          }
        }
      : { id: "__no_access__" };
  }

  // A group's own login or a member sees the agents serving their group.
  if (user.role === "GROUP_ACCOUNT" || user.role === "MEMBER") {
    const groupWhere = memberGroupWhere(user);
    return groupWhere
      ? { OR: [{ groupLinks: { some: { group: groupWhere } } }, { groups: { some: groupWhere } }] }
      : { id: "__no_access__" };
  }

  // Fall-through is platform-wide (IWL_ADMIN / READ_ONLY).
  return {};
}

export function memberScopeForUser(
  user: AuthenticatedUser | undefined,
  where?: Prisma.MemberWhereInput
): Prisma.MemberWhereInput {
  if (!user) return { id: "__no_access__" };

  if (user.role === "MEMBER") {
    return andWhere(where, user.memberId ? { id: user.memberId } : { id: "__no_access__" });
  }

  if (user.role === "GROUP_ACCOUNT") {
    return andWhere(where, user.groupId ? { groupId: user.groupId } : { id: "__no_access__" });
  }

  if (user.role === "PARTNER_OFFICER") {
    return andWhere(where, user.partnerId ? { group: groupScopeForUser(user) } : { id: "__no_access__" });
  }

  if (user.role === "LENDER") {
    return andWhere(
      where,
      user.partnerId ? { group: groupScopeForUser(user) } : { id: "__no_access__" }
    );
  }

  if (user.role === "VILLAGE_AGENT") {
    return andWhere(
      where,
      user.villageAgentId ? { group: groupScopeForUser(user) } : { id: "__no_access__" }
    );
  }

  return where ?? {};
}

export function ledgerScopeForUser(
  user: AuthenticatedUser | undefined,
  where?: Prisma.LedgerEntryWhereInput
): Prisma.LedgerEntryWhereInput {
  if (!user) return { id: "__no_access__" };

  if (user.role === "MEMBER") {
    return andWhere(where, user.memberId ? { memberId: user.memberId } : { id: "__no_access__" });
  }

  if (user.role === "GROUP_ACCOUNT") {
    return andWhere(where, user.groupId ? { groupId: user.groupId } : { id: "__no_access__" });
  }

  if (user.role === "PARTNER_OFFICER") {
    return andWhere(where, user.partnerId ? { group: groupScopeForUser(user) } : { id: "__no_access__" });
  }

  if (user.role === "LENDER") {
    return andWhere(
      where,
      user.partnerId ? { group: groupScopeForUser(user) } : { id: "__no_access__" }
    );
  }

  if (user.role === "VILLAGE_AGENT") {
    return andWhere(
      where,
      user.villageAgentId ? { group: groupScopeForUser(user) } : { id: "__no_access__" }
    );
  }

  return where ?? {};
}

export async function assertGroupAccess(user: AuthenticatedUser | undefined, groupId: string) {
  const group = await prisma.group.findFirst({
    where: scopeGroupWhere(user, { id: groupId }),
    select: { id: true }
  });

  if (!group) {
    throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
  }
}

/**
 * Whether [user] speaks for the group itself: a platform admin, or the group's
 * own account. Decisions that belong to the group - who holds an office, what
 * a vote resolved, opening a poll, handing out meeting keys - are theirs, not
 * a village agent's, a member's or an outside partner's.
 */
export function isGroupSteward(user: AuthenticatedUser | undefined, groupId: string) {
  if (!user) return false;
  if (user.role === "IWL_ADMIN" || user.permissions.includes("groups:write")) return true;
  return user.role === "GROUP_ACCOUNT" && user.groupId === groupId;
}

export function assertGroupSteward(user: AuthenticatedUser | undefined, groupId: string, what: string) {
  if (isGroupSteward(user, groupId)) return;
  throw new ApiHttpError(403, "FORBIDDEN", `Only the group's own account or a platform admin may ${what}.`);
}

/**
 * Whether the caller is themselves part of the demo data.
 *
 * Demo rows are excluded from cross-group totals so they cannot be mistaken for
 * evidence — but a demo account looking at its own dashboard must still see its
 * own figures, or the demo appears broken, which defeats the point of having
 * one. The exclusion therefore depends on who is asking.
 *
 * Read from the database rather than the session: `isDemo` can be set on a row
 * after a token was issued, and a stale session must not be a way back into the
 * totals.
 */
export async function callerIsDemo(user?: AuthenticatedUser): Promise<boolean> {
  if (!user) return false;

  const [group, agent, partner] = await Promise.all([
    user.groupId
      ? prisma.group.findUnique({ where: { id: user.groupId }, select: { isDemo: true } })
      : null,
    user.villageAgentId
      ? prisma.villageAgent.findUnique({
          where: { id: user.villageAgentId },
          select: { isDemo: true }
        })
      : null,
    user.partnerId
      ? prisma.partner.findUnique({ where: { id: user.partnerId }, select: { isDemo: true } })
      : null
  ]);

  return Boolean(group?.isDemo || agent?.isDemo || partner?.isDemo);
}

/**
 * The demo filter to apply to a cross-group total, for this caller.
 *
 * `{}` for a demo account — they see their own. `{ isDemo: false }` for
 * everyone else, which is what keeps made-up savings out of a partner's impact
 * numbers and off an auditor's desk.
 */
export async function demoExclusionForUser(
  user?: AuthenticatedUser
): Promise<Prisma.GroupWhereInput> {
  return (await callerIsDemo(user)) ? {} : { isDemo: false };
}
