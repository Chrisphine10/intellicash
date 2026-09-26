import React from "react";
import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import GroupWelfarePage from "../src/app/dashboard/groups/[id]/welfare/page";
import { CurrentUserProvider } from "../src/lib/current-user";
import type { User } from "../src/types/dashboard";

/**
 * "A partner should not edit group welfare" (26 Sep 2026). The API refuses a
 * partner's welfare payment and leaves out who received it; the page must not
 * offer the form, and must not ask for the members and meetings the form needs.
 */

function stubApi(expenses: unknown[]) {
  const requested: string[] = [];
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      requested.push(url);
      let data: unknown = [];
      if (url.endsWith("/groups/group-1/welfare-expenses")) {
        data = {
          group: { id: "group-1", name: "Tujijenge Women VSLA", code: "IWL-KBU-0001" },
          expenses,
          spentCents: 5_000,
          welfareBalanceCents: 45_000
        };
      }
      return new Response(JSON.stringify({ data }), { status: 200, headers: { "Content-Type": "application/json" } });
    })
  );
  return requested;
}

function resolvedParams(id: string) {
  return Object.assign(Promise.resolve({ id }), { status: "fulfilled", value: { id } }) as unknown as Promise<{ id: string }>;
}

function renderAs(viewer: User) {
  return render(
    <CurrentUserProvider user={viewer}>
      <React.Suspense fallback={<div>loading</div>}>
        <GroupWelfarePage params={resolvedParams("group-1")} />
      </React.Suspense>
    </CurrentUserProvider>
  );
}

describe("group welfare", () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows a partner what was spent, with no form and no payee", async () => {
    // What the API sends a partner: payee and note already left out.
    const requested = stubApi([
      {
        id: "exp-1",
        category: "BEREAVEMENT",
        note: null,
        payeeName: null,
        payeeMember: null,
        ledgerEntry: { amountCents: 5_000, createdAt: "2026-09-20T10:00:00.000Z" }
      }
    ]);
    renderAs({
      id: "u-p",
      name: "Programme Officer",
      email: "p@example.test",
      role: "PARTNER_OFFICER",
      permissions: ["groups:read", "ledger:read", "members:read", "meetings:read"]
    });

    expect(await screen.findByText(/View only: you can see this group/)).toBeInTheDocument();
    expect(screen.queryByText("Record a payment")).toBeNull();
    expect(screen.queryByRole("button", { name: "Record expense" })).toBeNull();
    expect(screen.queryByText(/unrecorded payee/)).toBeNull();
    expect(screen.getByText(/Bereavement/)).toBeInTheDocument();
    expect(requested.some((url) => url.endsWith("/groups/group-1/members"))).toBe(false);
    expect(requested.some((url) => url.endsWith("/groups/group-1/meetings"))).toBe(false);
  });

  it("gives the group's own account the form", async () => {
    stubApi([]);
    renderAs({
      id: "u-g",
      name: "Tujijenge Women VSLA",
      email: "g@example.test",
      role: "GROUP_ACCOUNT",
      groupId: "group-1",
      permissions: ["groups:read", "ledger:read", "ledger:write", "members:read", "meetings:read"]
    });

    expect(await screen.findByText("Record a payment")).toBeInTheDocument();
    expect(screen.queryByText(/View only/)).toBeNull();
  });
});
