import React from "react";
import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { demoAccounts } from "@intellicash/shared";
import LoginPage from "../src/app/login/page";

vi.mock("next/navigation", () => ({
  redirect: vi.fn(),
  useRouter: () => ({ push: vi.fn() }),
  usePathname: () => "/login"
}));

describe("phone-based login", () => {
  it("renders phone input instead of email input", () => {
    render(<LoginPage />);

    expect(screen.getByText("Phone Number")).toBeInTheDocument();
    expect(screen.getByText("Password")).toBeInTheDocument();
    expect(screen.getByPlaceholderText("+254700000001")).toBeInTheDocument();
  });

  it("uses phone number as default value from demo accounts", () => {
    render(<LoginPage />);

    const phoneInput = screen.getByPlaceholderText("+254700000001") as HTMLInputElement;
    expect(phoneInput.value).toBe(demoAccounts[0].phone);
  });

  it("shows demo accounts with phone numbers", () => {
    render(<LoginPage />);

    expect(screen.getByText("Demo accounts")).toBeInTheDocument();
    const openButtons = screen.getAllByText("Open");
    expect(openButtons.length).toBeGreaterThan(0);
  });

  it("has sign in button with LogIn icon", () => {
    render(<LoginPage />);

    const signInButton = screen.getByRole("button", { name: /Sign in/ });
    expect(signInButton).toBeInTheDocument();
  });

  it("demo account buttons show Open text", () => {
    render(<LoginPage />);

    const openButtons = screen.getAllByText("Open");
    expect(openButtons.length).toBeGreaterThan(0);
  });

  it("renders the brand logo", () => {
    render(<LoginPage />);

    const logo = screen.getByAltText("Intelli Cash - Trusted Financial Partner");
    expect(logo).toBeInTheDocument();
    expect(logo.getAttribute("src")).toBe("/brand/intelli-cash-logo.png");
  });
});
