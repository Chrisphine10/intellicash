"use client";

import React, { createContext, useContext, type ReactNode } from "react";
import { isOversightRole } from "@intellicash/shared";
import type { User } from "../types/dashboard";

/**
 * The signed-in account, as the dashboard shell loaded it from /auth/me.
 *
 * Pages use it to show only the controls the viewer may use. The API still
 * decides: hiding a button is for the viewer's sake, never the protection.
 */
const CurrentUserContext = createContext<User | null>(null);

export function CurrentUserProvider({ user, children }: { user: User | null; children: ReactNode }) {
  return <CurrentUserContext.Provider value={user}>{children}</CurrentUserContext.Provider>;
}

export function useCurrentUser() {
  return useContext(CurrentUserContext);
}

/** Whether the signed-in account holds [permission]. False while loading. */
export function userCan(user: User | null | undefined, permission: string) {
  return Boolean(user?.permissions?.includes(permission));
}

export function useCan(permission: string) {
  return userCan(useCurrentUser(), permission);
}

/**
 * The group's own account or a platform admin: the people the API lets change
 * a group's officials, rules, keys and join requests.
 */
export function isGroupSteward(user: User | null | undefined, groupId: string | null | undefined) {
  if (!user) return false;
  if (user.role === "IWL_ADMIN" || userCan(user, "groups:write")) return true;
  return user.role === "GROUP_ACCOUNT" && Boolean(groupId) && user.groupId === groupId;
}

/** Partners, lenders and read-only viewers: they see groups, never change them. */
export function isViewOnlyOverGroups(user: User | null | undefined) {
  return isOversightRole(user?.role);
}

/** One line for group pages, so a viewer knows why there is nothing to press. */
export function ViewOnlyNotice() {
  const user = useCurrentUser();
  if (!isViewOnlyOverGroups(user)) return null;
  return (
    <p className="dashboard-notice view-only-notice" role="note">
      View only: you can see this group&apos;s records but not change them.
    </p>
  );
}
