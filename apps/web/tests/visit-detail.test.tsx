import React from "react";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  usePathname: () => "/dashboard/visits/vis_1",
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }),
  useSearchParams: () => new URLSearchParams()
}));

/**
 * The field visit page.
 *
 * Two things are worth a test here. The first is that an agent can raise an
 * action against the visit at all: `POST /visits/:id/action-items` shipped with
 * the action plan and had no caller in either client, so anything a group
 * agreed had to be remembered rather than recorded. The second is the order of
 * the page -- the action plan has to come BEFORE the scorecard, because the
 * scorecard is 46 rows and everything a reader must act on used to sit under
 * it.
 */

const VISIT = {
  visit: {
    id: "vis_1",
    groupId: "grp_1",
    clientRequestId: "visit-1b6d",
    visitType: "QUARTERLY_REVIEW",
    status: "SUBMITTED",
    startedAt: "2026-08-14T08:20:00.000Z",
    submittedAt: "2026-08-16T18:41:00.000Z",
    revision: 1,
    authenticityFlags: ["LOW_ACCURACY_FIX"],
    notes: "Met at the chief's camp; the usual place was flooded.",
    location: {
      outcome: "OUTSIDE_GEOFENCE",
      withinGeofence: false,
      distanceFromGroupM: 1840,
      accuracyM: 42,
      latitude: -0.5382,
      longitude: 37.4571,
      note: "Group met at the chief's camp."
    }
  },
  group: { id: "grp_1", name: "Kianjokoma Women Group", code: "IWL-KBU-0043", county: "Embu" },
  agent: { id: "va_1", name: "Grace Wanjiku", phone: "+254720100102" },
  submittedBy: { id: "usr_1", name: "Grace Wanjiku" },
  revisions: []
};

const OPEN_ITEM = {
  id: "act_1",
  title: "Write up the ledger to the last meeting",
  detail: null,
  owner: "Secretary",
  status: "OPEN",
  closingNote: null,
  state: {
    state: "OVERDUE",
    label: "11 days overdue",
    daysOverdue: 11,
    daysUntilDue: null,
    open: true,
    dueDate: "2026-08-16T00:00:00.000Z"
  }
};

const CLOSED_ITEM = {
  id: "act_2",
  title: "Minute every loan approval",
  detail: null,
  owner: "Chairperson",
  status: "DONE",
  closingNote: "Seen in the 12 Aug minutes.",
  state: {
    state: "DONE",
    label: "Done",
    daysOverdue: 0,
    daysUntilDue: null,
    open: false,
    dueDate: "2026-08-20T00:00:00.000Z"
  }
};

let actionItems: unknown[] = [];
const calls: { url: string; method: string; body: unknown }[] = [];

function stubFetch() {
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? "GET";
      calls.push({
        url,
        method,
        body: init?.body ? JSON.parse(String(init.body)) : undefined
      });

      let data: unknown = VISIT;
      if (url.includes("/attachments")) data = [];
      else if (url.includes("/assessment")) {
        return {
          ok: false,
          status: 404,
          headers: new Headers({ "content-type": "application/json" }),
          json: async () => ({ error: { code: "NOT_FOUND", message: "No assessment." } })
        } as unknown as Response;
      } else if (url.includes("/mentorship")) {
        data = { sessions: [], ratings: [], averageGroupRating: null, ratedByGroup: false };
      } else if (url.includes("/action-items")) {
        // The POST answers with the created row; the GET answers with the list.
        data = method === "POST" ? OPEN_ITEM : { items: actionItems };
      }

      return {
        ok: true,
        status: method === "POST" ? 201 : 200,
        headers: new Headers({ "content-type": "application/json" }),
        json: async () => ({ data })
      } as unknown as Response;
    })
  );
}

/**
 * `use(params)` suspends on a pending promise, and a promise that settles
 * inside a test is never flushed -- the render sits on the fallback. React
 * reads an already-fulfilled thenable synchronously, which is what this is.
 */
function resolvedParams(id: string) {
  return Object.assign(Promise.resolve({ id }), {
    status: "fulfilled",
    value: { id }
  }) as unknown as Promise<{ id: string }>;
}

async function renderPage() {
  const { default: Page } = await import("@/app/dashboard/visits/[id]/page");
  const view = render(
    <React.Suspense fallback={<div>loading</div>}>
      <Page params={resolvedParams("vis_1")} />
    </React.Suspense>
  );
  await waitFor(() => expect(screen.getByText("Action plan")).toBeTruthy());
  return view;
}

describe("field visit detail", () => {
  beforeEach(() => {
    calls.length = 0;
    actionItems = [OPEN_ITEM, CLOSED_ITEM];
    stubFetch();
  });

  it("answers the four opening questions before any scrolling", async () => {
    const { container } = await renderPage();

    const summary = container.querySelector(".visit-summary");
    expect(summary).toBeTruthy();

    // One tile per question, each linking to the section that answers it.
    const links = Array.from(summary!.querySelectorAll("a")).map((a) =>
      a.getAttribute("href")
    );
    expect(links).toEqual([
      "#visit-assessment",
      "#visit-location",
      "#visit-actions",
      "#visit-evidence"
    ]);

    expect(within(summary as HTMLElement).getByText("1 open")).toBeTruthy();
    expect(within(summary as HTMLElement).getByText("1 overdue")).toBeTruthy();
  });

  it("puts the action plan ahead of the scorecard", async () => {
    const { container } = await renderPage();

    const actions = container.querySelector("#visit-actions");
    const assessment = container.querySelector("#visit-assessment");
    expect(actions).toBeTruthy();
    expect(assessment).toBeTruthy();

    // DOCUMENT_POSITION_FOLLOWING: the assessment comes after the plan.
    expect(actions!.compareDocumentPosition(assessment!) & Node.DOCUMENT_POSITION_FOLLOWING)
      .toBeTruthy();
  });

  it("says what a flag means rather than printing the enum", async () => {
    await renderPage();

    expect(screen.queryByText(/LOW_ACCURACY_FIX/)).toBeNull();
    expect(screen.getByText(/location reading was imprecise/i)).toBeTruthy();
  });

  it("leads the location card with the distance, not the code path", async () => {
    const { container } = await renderPage();

    const card = container.querySelector("#visit-location");
    expect(within(card as HTMLElement).getByText("1.8 km away")).toBeTruthy();
    expect(within(card as HTMLElement).queryByText("Outside Geofence")).toBeNull();
  });

  it("lets an agent agree a new action against this visit", async () => {
    await renderPage();

    fireEvent.click(screen.getByRole("button", { name: "Agree an action" }));

    fireEvent.change(screen.getByPlaceholderText(/Write up the ledger/), {
      target: { value: "Open a group bank account" }
    });
    fireEvent.change(screen.getByPlaceholderText("Secretary"), {
      target: { value: "Treasurer" }
    });

    fireEvent.click(screen.getByRole("button", { name: "Add to the plan" }));

    await waitFor(() => {
      const post = calls.find((call) => call.method === "POST");
      expect(post).toBeTruthy();
      // Raised against the VISIT, which is what ties the work to the occasion
      // it was agreed at.
      expect(post!.url).toContain("/visits/vis_1/action-items");
      expect(post!.body).toMatchObject({
        title: "Open a group bank account",
        owner: "Treasurer"
      });
    });

    // The list is re-read afterwards rather than patched locally, so the
    // server's derived "overdue" is what the reader sees.
    await waitFor(() =>
      expect(
        calls.filter(
          (call) => call.method === "GET" && call.url.includes("/groups/grp_1/action-items")
        ).length
      ).toBeGreaterThan(1)
    );
  });

  it("refuses an action with no title, without calling the server", async () => {
    await renderPage();

    fireEvent.click(screen.getByRole("button", { name: "Agree an action" }));
    fireEvent.submit(screen.getByRole("button", { name: "Add to the plan" }).closest("form")!);

    await waitFor(() => expect(screen.getByText(/An action needs a title/)).toBeTruthy());
    expect(calls.some((call) => call.method === "POST")).toBe(false);
  });

  it("closes an open item against this visit, and reopens a closed one", async () => {
    await renderPage();

    fireEvent.click(screen.getByRole("button", { name: "Mark done" }));
    await waitFor(() => {
      const patch = calls.find((call) => call.method === "PATCH");
      expect(patch!.url).toContain("/action-items/act_1");
      // Traceable both ways: where it was agreed, and where it was signed off.
      expect(patch!.body).toMatchObject({ status: "DONE", closedAtVisitId: "vis_1" });
    });

    calls.length = 0;
    fireEvent.click(screen.getByRole("button", { name: "Reopen" }));
    await waitFor(() => {
      const patch = calls.find((call) => call.method === "PATCH");
      expect(patch!.body).toMatchObject({ status: "OPEN" });
      // Reopening must not claim the item was finished at this visit.
      expect(patch!.body).not.toHaveProperty("closedAtVisitId");
    });
  });

  it("renders dates as spelled months, not as an ambiguous numeric order", async () => {
    await renderPage();

    expect(screen.getAllByText("14 Aug 2026").length).toBeGreaterThan(0);
    expect(screen.queryByText("8/14/2026")).toBeNull();
  });
});
