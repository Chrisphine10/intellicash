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

  // A village agent / CBT sees exactly their assigned caseload.
  if (user.role === "VILLAGE_AGENT") {
    return user.villageAgentId
      ? { villageAgentId: user.villageAgentId }
      : impossibleGroupScope();
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
            { villageAgents: { some: { id: user.villageAgentId } } },
            { groups: { some: { villageAgentId: user.villageAgentId } } },
            { groupLinks: { some: { group: { villageAgentId: user.villageAgentId } } } }
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
          programme: {
            OR: [
              { partnerId: user.partnerId },
              { partnerLinks: { some: { partnerId: user.partnerId } } }
            ]
          }
        }
      : { id: "__no_access__" };
  }

  if (user.role === "GROUP_ACCOUNT" || user.role === "MEMBER") {
    return user.groupId ? { groups: { some: { id: user.groupId } } } : { id: "__no_access__" };
  }

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
