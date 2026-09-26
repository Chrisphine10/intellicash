import React from "react";
import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import GroupDetailPage from "../src/app/dashboard/groups/[id]/page";
import { CurrentUserProvider } from "../src/lib/current-user";
import type { User } from "../src/types/dashboard";

/**
 * The group page (26 Sep 2026): money this cycle from the group's statement,
 * the group's profile, and only the controls the viewer may use.
 *
 * A partner "editing welfare" was the complaint. The API already refused them;
 * the page offered the buttons anyway. These tests hold the page to the same
 * rules the API enforces.
 */

const group = {
  id: "group-1",
  name: "Tujijenge Women VSLA",
  code: "IWL-KBU-0001",
  phase: "INTENSIVE",
  county: "Kiambu",
  subCounty: "Ruiru",
  location: "Kimbo",
  contactPersonName: "Mary Njeri",
  contactPhone: "+254700000201",
  meetingDay: "Tuesday",
  shareValueCents: 20000,
  constitutionVersion: "IWLSGS-1.0",
  cycleNumber: 2,
  modules: { store: false, voting: false },
  programmeLinks: [{ id: "link-1", role: "PRIMARY", programme: { id: "prog-1", name: "Graduation Programme" } }],
  villageAgent: { id: "agent-1", name: "Grace Wanjiku" },
  fundAccounts: [
    { type: "INTERNAL_LOAN", balanceCents: 0, currency: "KES" },
    { type: "VSLF", balanceCents: 0, currency: "KES" }
  ],
  creditScores: [],
  _count: { members: 12, meetings: 4, votes: 0, ledgerEntries: 3 }
};

const statement = {
  suppressed: false,
  cycle: { number: 2, status: "ACTIVE" },
  members: { active: 12, total: 12 },
  loanFund: { sharesCents: 4_560_000, closingCents: 1_230_000 },
  socialFund: { closingCents: 88_000, welfarePaidCents: 12_000 },
  loans: { activeCount: 3, pastDueCount: 1, outstandingCents: 3_400_000, par30Rate: 8 }
};

const members = [
  {
    id: "member-1",
    groupId: "group-1",
    fullName: "Mary Njeri",
    phone: "+254700000201",
    role: "CHAIRPERSON",
    kycStatus: "PENDING",
    status: "ACTIVE",
    pinSet: true
  }
];

function stubApi(overrides: Record<string, { status: number; data?: unknown; message?: string }> = {}) {
  const requested: string[] = [];
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      requested.push(url);
      const path = url.replace(/^.*\/api\/v1/, "");
      const override = Object.entries(overrides).find(([suffix]) => path === suffix);
      if (override) {
        const [, value] = override;
        return new Response(
          JSON.stringify(
            value.status >= 400 ? { error: { code: "FORBIDDEN", message: value.message ?? "Refused" } } : { data: value.data }
          ),
          { status: value.status, headers: { "Content-Type": "application/json" } }
        );
      }
      let data: unknown = [];
      if (path === "/groups/group-1") data = group;
      else if (path === "/reports/group/group-1") data = { statement };
      else if (path === "/groups/group-1/members") data = members;
      return new Response(JSON.stringify({ data }), { status: 200, headers: { "Content-Type": "application/json" } });
    })
  );
  return requested;
}

/** `use(params)` reads an already-fulfilled thenable synchronously. */
function resolvedParams(id: string) {
  return Object.assign(Promise.resolve({ id }), { status: "fulfilled", value: { id } }) as unknown as Promise<{ id: string }>;
}

function renderAs(viewer: User) {
  return render(
    <CurrentUserProvider user={viewer}>
      <React.Suspense fallback={<div>loading</div>}>
        <GroupDetailPage params={resolvedParams("group-1")} />
      </React.Suspense>
    </CurrentUserProvider>
  );
}

const READS = ["groups:read", "members:read", "meetings:read", "ledger:read", "votes:read", "visits:read", "documents:read"];

const partner: User = { id: "u-p", name: "Programme Officer", email: "p@example.test", role: "PARTNER_OFFICER", permissions: READS };
const admin: User = {
  id: "u-a",
  name: "IWL Admin",
  email: "a@example.test",
  role: "IWL_ADMIN",
  permissions: [...READS, "groups:write", "members:write", "ledger:write"]
};

describe("group page", () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  it("leads with the group's money this cycle, from its statement", async () => {
    stubApi();
    renderAs(admin);

    expect(await screen.findByText("Shares this cycle")).toBeInTheDocument();
    expect(screen.getByText("Loan fund cash")).toBeInTheDocument();
    expect(screen.getByText("Loans outstanding")).toBeInTheDocument();
    expect(screen.getByText("Social fund")).toBeInTheDocument();
    expect(screen.getByText(/3 active · PAR30 8%/)).toBeInTheDocument();
    // An empty VSLF is not a card of its own any more.
    expect(screen.queryByText("Vslf")).toBeNull();
  });

  it("reads as sentences: spaced subtitle, an honest credit score, profile facts", async () => {
    stubApi();
    renderAs(admin);

    expect(await screen.findByText("IWL-KBU-0001 · Intensive · Cycle 2")).toBeInTheDocument();
    expect(screen.getByText("Credit score: not rated yet")).toBeInTheDocument();
    expect(screen.getByText("About this group")).toBeInTheDocument();
    expect(screen.getByText("Graduation Programme")).toBeInTheDocument();
    expect(screen.getByText("Grace Wanjiku")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Edit/ })).toBeInTheDocument();
  });

  it("gives a partner the records to read and nothing to press", async () => {
    stubApi();
    renderAs(partner);

    expect(await screen.findByText(/View only: you can see this group/)).toBeInTheDocument();
    expect(screen.getByText("This group's records")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Edit/ })).toBeNull();
    // No member phones or contact phone for oversight roles.
    await waitFor(() => expect(screen.getAllByText("Mary Njeri").length).toBeGreaterThan(0));
    expect(screen.queryByText("+254700000201")).toBeNull();
    // Voting is off for this group: no Votes tile.
    expect(screen.queryByRole("link", { name: /Votes/ })).toBeNull();
    // Join requests are answered by the group itself, not by a partner.
    expect(screen.queryByRole("link", { name: /Requests to join/ })).toBeNull();
  });

  it("says why a small group's money is withheld from a partner", async () => {
    stubApi({ "/reports/group/group-1": { status: 200, data: { statement: { ...statement, suppressed: true } } } });
    renderAs(partner);

    expect(await screen.findByText(/Figures withheld: this group has fewer than 5 active members/)).toBeInTheDocument();
    expect(screen.queryByText("Shares this cycle")).toBeNull();
  });

  it("keeps the rest of the page when one section is refused", async () => {
    stubApi({ "/groups/group-1/meetings": { status: 403, message: "You do not have access to meetings." } });
    renderAs(admin);

    expect(await screen.findByText(/Meetings could not be loaded/)).toBeInTheDocument();
    expect(screen.getByText("Shares this cycle")).toBeInTheDocument();
    expect(screen.getByText("About this group")).toBeInTheDocument();
  });

  it("does not load the editor's choices for someone who cannot edit", async () => {
    const requested = stubApi();
    renderAs(partner);

    await screen.findByText("About this group");
    expect(requested.some((url) => url.endsWith("/programmes"))).toBe(false);
    expect(requested.some((url) => url.endsWith("/village-agents"))).toBe(false);
  });
});
