import React from "react";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AdminPasswordReset } from "../src/components/dashboard/admin-password-reset";
import { CredentialButton } from "../src/components/dashboard/credential-button";

function mockFetch(routes: Record<string, unknown>) {
  const calls: { url: string; body: Record<string, unknown> }[] = [];
  global.fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    const key = Object.keys(routes).find((path) => String(url).includes(path)) ?? "";
    calls.push({ url: String(url), body: init?.body ? JSON.parse(String(init.body)) : {} });
    return new Response(JSON.stringify({ data: routes[key] ?? {} }), {
      status: 200,
      headers: { "Content-Type": "application/json" }
    });
  }) as typeof fetch;
  return calls;
}

afterEach(() => vi.restoreAllMocks());

describe("an admin resetting someone's password", () => {
  it("texts a reset code and says where it went", async () => {
    const calls = mockFetch({
      "/auth/me": { id: "admin-1" },
      "/users/user-9/password": { sentTo: "*********123", expiresInMinutes: 10 }
    });
    render(<AdminPasswordReset hasPhone userId="user-9" />);

    fireEvent.click(screen.getByRole("button", { name: /Send reset code/ }));

    expect(await screen.findByText(/Reset code texted to \*+123/)).toBeInTheDocument();
    expect(calls.find((call) => call.url.includes("/password"))?.body).toEqual({ mode: "SEND_CODE" });
  });

  it("sets a password only once it meets the rule", async () => {
    const calls = mockFetch({
      "/auth/me": { id: "admin-1" },
      "/users/user-9/password": { endedSessions: 2, ownerNotified: true }
    });
    render(<AdminPasswordReset hasPhone={false} userId="user-9" />);

    const setButton = screen.getByRole("button", { name: /Set password/ });
    const field = screen.getByLabelText(/Or set a new password/);

    fireEvent.change(field, { target: { value: "short" } });
    expect(setButton).toBeDisabled();

    fireEvent.change(field, { target: { value: "long-enough-1" } });
    fireEvent.click(setButton);

    expect(await screen.findByText(/2 signed-in sessions were ended/)).toBeInTheDocument();
    expect(calls.find((call) => call.url.includes("/password"))?.body).toEqual({
      mode: "SET",
      newPassword: "long-enough-1"
    });
  });

  it("offers no code for an account without a phone", async () => {
    mockFetch({ "/auth/me": { id: "admin-1" } });
    render(<AdminPasswordReset hasPhone={false} userId="user-9" />);
    expect(screen.queryByRole("button", { name: /Send reset code/ })).toBeNull();
    expect(screen.getByText(/has no phone number/)).toBeInTheDocument();
  });

  it("sends the admin to My account for their own password", async () => {
    mockFetch({ "/auth/me": { id: "same-user" } });
    render(<AdminPasswordReset hasPhone userId="same-user" />);
    await waitFor(() => expect(screen.getByText(/from My account/)).toBeInTheDocument());
    expect(screen.queryByRole("button", { name: /Set password/ })).toBeNull();
  });
});

describe("the shared credential button", () => {
  it("words the busy state by what the button does", () => {
    const { rerender } = render(<CredentialButton busy kind="sms" label="Send PIN" />);
    expect(screen.getByRole("button", { name: /Sending/ })).toBeDisabled();
    rerender(<CredentialButton busy kind="password" label="Update password" />);
    expect(screen.getByRole("button", { name: /Saving/ })).toBeDisabled();
  });

  it("marks an alternative action as secondary", () => {
    render(<CredentialButton emphasis="secondary" kind="sms" label="Send OTP" />);
    expect(screen.getByRole("button", { name: /Send OTP/ }).className).toBe("button secondary");
  });
});
