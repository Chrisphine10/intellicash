import React from "react";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import GroupMembersPage from "../src/app/dashboard/groups/[id]/members/page";

describe("group member administration", () => {
  it("lets admins edit member details from the group members page", async () => {
    const updatedMember = {
      id: "member-1",
      groupId: "group-1",
      fullName: "Mary Njeri Updated",
      phone: "+254757255710",
      role: "TREASURER",
      kycStatus: "VERIFIED",
      status: "SUSPENDED",
      pinSet: true,
      currentOtpSet: false
    };
    const calls: Array<{ url: string; body: Record<string, unknown> }> = [];
    let members = [
      {
        id: "member-1",
        groupId: "group-1",
        fullName: "Mary Njeri",
        phone: "+254700000201",
        role: "CHAIRPERSON",
        kycStatus: "PENDING",
        status: "ACTIVE",
        pinSet: true,
        currentOtpSet: false
      }
    ];

    vi.stubGlobal(
      "fetch",
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);
        const body = init?.body ? (JSON.parse(String(init.body)) as Record<string, unknown>) : {};
        calls.push({ url, body });

        let data: unknown = {};

        if (url.endsWith("/auth/me")) {
          data = {
            id: "admin-1",
            name: "IWL Admin",
            email: "admin@intellicash.co.ke",
            role: "IWL_ADMIN",
            permissions: ["members:write", "groups:read"]
          };
        } else if (url.endsWith("/groups/group-1/members/member-1") && init?.method === "PATCH") {
          members = [updatedMember];
          data = updatedMember;
        } else if (url.endsWith("/groups/group-1/members")) {
          data = members;
        } else if (url.endsWith("/groups/group-1")) {
          data = { id: "group-1", name: "Tujijenge Women VSLA", code: "IWL-KBU-0001" };
        }

        return new Response(JSON.stringify({ data }), {
          status: 200,
          headers: { "Content-Type": "application/json" }
        });
      })
    );

    await React.act(async () => {
      render(
        <React.Suspense fallback={<div>Loading members</div>}>
          <GroupMembersPage params={Promise.resolve({ id: "group-1" })} />
        </React.Suspense>
      );
    });

    expect(await screen.findByRole("heading", { name: "IWL-KBU-0001" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /Edit/i }));

    const dialog = screen.getByRole("dialog", { name: /Edit member/i });
    expect(within(dialog).getByLabelText("Name")).toHaveValue("Mary Njeri");
    expect(within(dialog).getByLabelText("Phone")).toHaveValue("+254700000201");

    fireEvent.change(within(dialog).getByLabelText("Name"), { target: { value: "Mary Njeri Updated" } });
    fireEvent.change(within(dialog).getByLabelText("Phone"), { target: { value: "+254757255710" } });
    fireEvent.change(within(dialog).getByLabelText("Role"), { target: { value: "TREASURER" } });
    fireEvent.change(within(dialog).getByLabelText("KYC"), { target: { value: "VERIFIED" } });
    fireEvent.change(within(dialog).getByLabelText("Status"), { target: { value: "SUSPENDED" } });
    fireEvent.click(within(dialog).getByRole("button", { name: /Save member/i }));

    await waitFor(() =>
      expect(calls).toContainEqual(
        expect.objectContaining({
          url: expect.stringContaining("/groups/group-1/members/member-1"),
          body: {
            fullName: "Mary Njeri Updated",
            phone: "+254757255710",
            role: "TREASURER",
            kycStatus: "VERIFIED",
            status: "SUSPENDED"
          }
        })
      )
    );
    expect(await screen.findByText("Mary Njeri Updated updated.")).toBeInTheDocument();
    expect(screen.getByText("Suspended")).toBeInTheDocument();

    vi.unstubAllGlobals();
  });
});
