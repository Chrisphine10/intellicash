/**
 * Optional modules — Intelli-Store and Voting — switched on per programme.
 *
 * The rule, everywhere: a group has a module when ANY programme it belongs to
 * (its own `programmeId`, or a `ProgrammeGroup` link) has the module on. A group
 * in no programme has neither. Both switches start off.
 *
 * IWL admins are never refused: they prepare a module (products, suppliers,
 * agents) before it is switched on, and they need to see what they prepared.
 * Everyone else is refused by the API itself, not only hidden in the UI, because
 * phones already in the field keep calling the endpoints they know.
 */

import type { Prisma } from "@prisma/client";
import type { AuthenticatedUser } from "../middleware/auth";
import { ApiHttpError } from "../lib/http";
import { prisma } from "../lib/prisma";
import { programmeScopeForUser } from "./account-scope";

export type ModuleKey = "store" | "voting";
export type ModuleState = Record<ModuleKey, boolean>;

const moduleColumn: Record<ModuleKey, "storeEnabled" | "votingEnabled"> = {
  store: "storeEnabled",
  voting: "votingEnabled"
};

const moduleLabel: Record<ModuleKey, string> = {
  store: "Intelli-Store",
  voting: "Voting"
};

/** Programmes with [module] switched on, as a Prisma filter. */
export function programmesWithModule(module: ModuleKey): Prisma.ProgrammeWhereInput {
  return { [moduleColumn[module]]: true };
}

/** Groups whose programmes have [module] on — the one rule, as a filter. */
export function groupsWithModule(module: ModuleKey): Prisma.GroupWhereInput {
  const on = programmesWithModule(module);
  return { OR: [{ programme: on }, { programmeLinks: { some: { programme: on } } }] };
}

export async function groupHasModule(groupId: string, module: ModuleKey) {
  const count = await prisma.group.count({ where: { AND: [{ id: groupId }, groupsWithModule(module)] } });
  return count > 0;
}

export async function modulesForGroup(groupId: string): Promise<ModuleState> {
  const [store, voting] = await Promise.all([groupHasModule(groupId, "store"), groupHasModule(groupId, "voting")]);
  return { store, voting };
}

export async function programmeHasModule(programmeId: string, module: ModuleKey) {
  const count = await prisma.programme.count({ where: { id: programmeId, ...programmesWithModule(module) } });
  return count > 0;
}

/**
 * Off for an EXISTING programme. A programme that does not exist is not this
 * check's to answer: the handler reports it as not found, as it always has.
 */
async function programmeExistsWithModuleOff(programmeId: string, module: ModuleKey) {
  const programme = await prisma.programme.findUnique({
    where: { id: programmeId },
    select: { storeEnabled: true, votingEnabled: true }
  });
  return programme !== null && !programme[moduleColumn[module]];
}

/**
 * What a signed-in account can use, for menus. Admins see everything; anyone
 * else sees a module when some programme within their own scope has it on.
 */
export async function modulesForUser(user: AuthenticatedUser | undefined): Promise<ModuleState> {
  if (!user) return { store: false, voting: false };
  if (user.role === "IWL_ADMIN") return { store: true, voting: true };
  const scope = programmeScopeForUser(user);
  const inScope = async (module: ModuleKey) =>
    (await prisma.programme.count({ where: { AND: [scope, programmesWithModule(module)] } })) > 0;
  const [store, voting] = await Promise.all([inScope("store"), inScope("voting")]);
  return { store, voting };
}

function refuse(module: ModuleKey): never {
  throw new ApiHttpError(
    403,
    "MODULE_DISABLED",
    `${moduleLabel[module]} is not switched on for this group's programme.`,
    { module }
  );
}

/**
 * Refuses a non-admin when [module] is off for the group or programme the
 * request is about. With neither given, the module must be on for at least one
 * programme within the caller's scope.
 */
export async function assertModuleEnabled(
  user: AuthenticatedUser | undefined,
  module: ModuleKey,
  target: { groupId?: string | null; programmeId?: string | null } = {}
) {
  if (user?.role === "IWL_ADMIN") return;
  if (target.groupId) {
    if (!(await groupHasModule(target.groupId, module))) refuse(module);
    return;
  }
  if (target.programmeId) {
    if (await programmeExistsWithModuleOff(target.programmeId, module)) refuse(module);
    return;
  }
  if (!(await modulesForUser(user))[module]) refuse(module);
}

/** Same check for public (signed-out) requests: no admin bypass. */
export async function assertPublicModuleEnabled(module: ModuleKey, programmeId: string | null | undefined) {
  if (programmeId && (await programmeExistsWithModuleOff(programmeId, module))) refuse(module);
}
