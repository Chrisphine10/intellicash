import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  clearOfflineMeetingSchedule,
  loadGroupMeetingWorkspace,
  loadOfflineMeetingSchedules,
  queueOfflineMeetingSchedule,
  storeGroupMeetingWorkspace
} from "../src/lib/meeting-offline-store";

describe("meeting offline store", () => {
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

  it("caches the group meeting workspace for offline use", async () => {
    await storeGroupMeetingWorkspace("group-1", {
      group: { id: "group-1", name: "Tujijenge", code: "IWL-001" },
      meetings: [{ id: "meeting-1", title: "Week 1", scheduledAt: "2026-06-15T07:00:00.000Z" }],
      members: [{ id: "member-1", fullName: "Mary Njeri" }],
      user: { id: "user-1", role: "GROUP_ACCOUNT" },
      cachedAt: "2026-06-10T10:00:00.000Z"
    });

    await expect(loadGroupMeetingWorkspace("group-1")).resolves.toEqual({
      group: { id: "group-1", name: "Tujijenge", code: "IWL-001" },
      meetings: [{ id: "meeting-1", title: "Week 1", scheduledAt: "2026-06-15T07:00:00.000Z" }],
      members: [{ id: "member-1", fullName: "Mary Njeri" }],
      user: { id: "user-1", role: "GROUP_ACCOUNT" },
      cachedAt: "2026-06-10T10:00:00.000Z"
    });
  });

  it("queues offline meeting schedules until they are synced", async () => {
    const queued = await queueOfflineMeetingSchedule({
      groupId: "group-1",
      title: "Offline weekly meeting",
      scheduledAt: "2026-06-17T07:00:00.000Z",
      gpsCompliant: false
    });

    expect(queued.id).toMatch(/^offline-meeting-/);
    await expect(loadOfflineMeetingSchedules("group-1")).resolves.toEqual([queued]);

    await clearOfflineMeetingSchedule("group-1", queued.id);

    await expect(loadOfflineMeetingSchedules("group-1")).resolves.toEqual([]);
  });
});
