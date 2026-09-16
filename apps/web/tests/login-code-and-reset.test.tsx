import React from "react";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import LoginPage from "../src/app/login/page";

const push = vi.fn();
vi.mock("next/navigation", () => ({
  redirect: vi.fn(),
  useRouter: () => ({ push }),
  usePathname: () => "/login"
}));

/**
 * The ways into an existing account besides a password.
 *
 * Field teams were stuck: the group already had an account, and the only way
 * in offered was a password the champion never had.
 */

function mockFetch(response: unknown = { data: { role: "GROUP_ACCOUNT", groupId: null } }) {
  const calls: { url: string; body: Record<string, unknown> }[] = [];
  global.fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), body: init?.body ? JSON.parse(String(init.body)) : {} });
    return new Response(JSON.stringify(response), { status: 200, headers: { "Content-Type": "application/json" } });
  }) as typeof fetch;
  return calls;
}

afterEach(() => {
  vi.restoreAllMocks();
  push.mockReset();
});

describe("signing in without a password", () => {
  it("sends a code, then signs in with it", async () => {
    const calls = mockFetch();
    render(<LoginPage />);

    fireEvent.click(screen.getByRole("button", { name: "Sign in with a code sent to my phone" }));
    fireEvent.change(screen.getByPlaceholderText("0712 345 678"), { target: { value: "0712 345 678" } });
    fireEvent.click(screen.getByRole("button", { name: "Send me a code" }));

    await screen.findByText("6-digit code from the SMS");
    expect(calls.at(-1)?.url).toContain("/auth/otp/request");

    fireEvent.change(screen.getByLabelText("6-digit code from the SMS"), { target: { value: "12 34-56" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(push).toHaveBeenCalledWith("/dashboard"));
    const verify = calls.find((call) => call.url.includes("/auth/otp/verify"));
    // Only digits reach the server, however the code was typed.
    expect(verify?.body).toMatchObject({ phone: "0712 345 678", code: "123456" });
  });

  it("does not claim the number has an account", async () => {
    mockFetch();
    render(<LoginPage />);
    fireEvent.click(screen.getByRole("button", { name: "Sign in with a code sent to my phone" }));
    fireEvent.change(screen.getByPlaceholderText("0712 345 678"), { target: { value: "0712345678" } });
    fireEvent.click(screen.getByRole("button", { name: "Send me a code" }));

    expect(await screen.findByText(/If that number has an account/)).toBeInTheDocument();
  });
});

describe("resetting a forgotten password", () => {
  it("asks for the code and a new password, then signs in", async () => {
    const calls = mockFetch();
    render(<LoginPage />);

    fireEvent.click(screen.getByRole("button", { name: "Forgot password?" }));
    expect(screen.getByText("Reset your password")).toBeInTheDocument();
    fireEvent.change(screen.getByPlaceholderText("0712 345 678"), { target: { value: "0712345678" } });
    fireEvent.click(screen.getByRole("button", { name: "Send me a code" }));

    await screen.findByText("New password (at least 8 characters)");
    expect(calls.at(-1)?.url).toContain("/auth/password/reset/request");

    fireEvent.change(screen.getByLabelText("6-digit code from the SMS"), { target: { value: "654321" } });
    fireEvent.change(screen.getByLabelText("New password (at least 8 characters)"), {
      target: { value: "a-new-password" }
    });
    fireEvent.click(screen.getByRole("button", { name: "Set password and sign in" }));

    await waitFor(() => expect(push).toHaveBeenCalledWith("/dashboard"));
    const reset = calls.find((call) => call.url.endsWith("/auth/password/reset"));
    expect(reset?.body).toMatchObject({ code: "654321", newPassword: "a-new-password" });
  });
});

describe("signing in with a group email", () => {
  it("sends an email as an email, not as a phone", async () => {
    // The groups with no phone on record sign in this way; sending the email
    // as `phone` failed for exactly them.
    const calls = mockFetch();
    render(<LoginPage />);

    fireEvent.change(screen.getByPlaceholderText("0712 345 678"), {
      target: { value: "iwl-emb-0003@groups.intellicash.co.ke" }
    });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "IntelliCash@2026" } });
    fireEvent.click(screen.getByRole("button", { name: /^Sign in$/ }));

    await waitFor(() => expect(calls.some((call) => call.url.includes("/auth/login"))).toBe(true));
    const login = calls.find((call) => call.url.includes("/auth/login"));
    expect(login?.body).toEqual({ email: "iwl-emb-0003@groups.intellicash.co.ke", password: "IntelliCash@2026" });
  });
});
