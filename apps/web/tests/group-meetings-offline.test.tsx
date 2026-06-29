import React from "react";
import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import GroupMeetingsPage from "../src/app/dashboard/groups/[id]/meetings/page";
import { storeGroupMeetingWorkspace } from "../src/lib/meeting-offline-store";

describe("group meetings offline workspace", () => {
  beforeEach(() => {
    const storage = new Map<string, string>();
    vi.stubGlobal("localStorage", {
      getItem: vi.fn((key: string) => storage.get(key) ?? null),
      setItem: vi.fn((key: string, value: string) => storage.set(key, value)),
      removeItem: vi.fn((key: string) => storage.delete(key)),
      clear: vi.fn(() => storage.clear())
    });
    vi.stubGlobal("indexedDB", undefined);
  });

  it("loads cached group meetings when the API is unavailable", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => {
        throw new TypeError("offline");
      })
    );

    await storeGroupMeetingWorkspace("group-1", {
      group: { id: "group-1", name: "Tujijenge Women VSLA", code: "IWL-KBU-0001" },
      meetings: [
        {
          id: "meeting-1",
          title: "Cached weekly meeting",
          status: "SCHEDULED",
          scheduledAt: "2026-06-17T07:00:00.000Z",
          unlockStatus: "PENDING",
          gpsCompliant: false,
          transactionTotal: 0,
          steps: [],
          attendance: [],
          keySubmissions: []
        }
      ],
      members: [],
      user: {
        id: "user-1",
        name: "Group Account",
        email: "group@intellicash.test",
        role: "GROUP_ACCOUNT",
        permissions: ["meetings:read", "meetings:write"]
      },
      cachedAt: "2026-06-10T10:00:00.000Z"
    });

    await React.act(async () => {
      render(
        <React.Suspense fallback={<div>Loading meetings</div>}>
          <GroupMeetingsPage params={Promise.resolve({ id: "group-1" })} />
        </React.Suspense>
      );
    });

    expect(await screen.findByRole("heading", { name: "IWL-KBU-0001" })).toBeInTheDocument();
    expect(screen.getAllByText("Cached weekly meeting").length).toBeGreaterThan(0);
    expect(screen.getByText(/Offline mode/i)).toBeInTheDocument();
  });
});
