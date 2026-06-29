import React from "react";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import SmsBroadcastPage from "../src/app/dashboard/sms/page";

const failedBroadcast = {
  id: "broadcast-1",
  targetType: "GROUP",
  targetGroupId: "group-1",
  targetMemberId: null,
  provider: "BONGA_SMS",
  message: "Reminder: please attend your group meeting.",
  status: "FAILED",
  recipientCount: 1,
  queuedCount: 0,
  sentCount: 0,
  failedCount: 1,
  createdAt: "2026-06-10T06:48:28.965Z",
  recipients: [
    {
      id: "recipient-1",
      memberId: "member-1",
      memberName: "Peter Mwangi",
      phone: "+254757255710",
      provider: "BONGA_SMS",
      status: "FAILED",
      providerStatus: "666",
      providerMessage: "wrong api credentials",
      sentAt: null
    }
  ]
};

describe("SMS broadcasts", () => {
  it("shows Bonga provider failure details when a broadcast fails", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);
        let data: unknown = {};

        if (url.endsWith("/groups")) {
          data = [{ id: "group-1", name: "Tujijenge Women VSLA", code: "IWL-KBU-0001", _count: { members: 1 } }];
        } else if (url.endsWith("/groups/group-1/members")) {
          data = [
            {
              id: "member-1",
              fullName: "Peter Mwangi",
              phone: "+254757255710",
              role: "MEMBER",
              status: "ACTIVE"
            }
          ];
        } else if (url.endsWith("/sms/broadcasts") && init?.method === "POST") {
          data = failedBroadcast;
        } else if (url.endsWith("/sms/broadcasts")) {
          data = [];
        }

        return new Response(JSON.stringify({ data }), {
          status: 200,
          headers: { "Content-Type": "application/json" }
        });
      })
    );

    render(<SmsBroadcastPage />);

    expect(await screen.findByRole("heading", { name: "SMS Broadcasts" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Send SMS" }));

    expect(await screen.findByText(/wrong api credentials/i)).toBeInTheDocument();
    expect(screen.getByText(/Bonga status 666/i)).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText(/Peter Mwangi/i)).toBeInTheDocument();
    });

    vi.unstubAllGlobals();
  });
});
