import React from "react";
import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  redirect: vi.fn(),
  useRouter: () => ({ push: vi.fn() }),
  usePathname: () => "/login"
}));

/**
 * The demo panel published a working password on the public login page.
 * It must stay invisible unless explicitly switched on, so this asserts the
 * DEFAULT, with the flag removed rather than set to "false" — a production
 * build simply never defines it.
 */
describe("demo login is off unless explicitly enabled", () => {
  afterEach(() => {
    cleanup();
    vi.unstubAllEnvs();
    vi.resetModules();
  });

  it("renders no demo accounts and no prefilled credentials", async () => {
    vi.stubEnv("NEXT_PUBLIC_ENABLE_DEMO_LOGIN", "");
    vi.resetModules();

    const { LoginExperience } = await import("@/components/login-experience");
    render(<LoginExperience copyText="text" copyTitle="title" />);

    expect(screen.queryByText("Demo accounts")).not.toBeInTheDocument();
    expect(screen.queryByText("One-click access")).not.toBeInTheDocument();

    // The credential fields must arrive empty, not carrying someone's login.
    for (const field of Array.from(document.querySelectorAll("input"))) {
      expect(field.value).toBe("");
    }
    expect(document.body.textContent).not.toContain("IntellicashDemo");
  });
});
