import fs from "node:fs";
import path from "node:path";
import React from "react";
import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * The public site links to the Intelli-Store only when the store has something
 * in it.
 *
 * Every store product on production belonged to the demo programme, so once
 * demo data stopped being served publicly the catalogue was bare. A navigation
 * item is a promise that something is behind it; the landing page was going
 * further and advertising "0 products" and "0 field officers you can book" as
 * selling points.
 *
 * The admin console keeps its own Intelli-Store nav unconditionally — that is
 * where products are added, and hiding it would leave no way to ever bring the
 * public link back.
 */

const apiFetch = vi.fn();

vi.mock("../src/lib/api", () => ({
  apiFetch: (...args: unknown[]) => apiFetch(...args),
  API_BASE_URL: "/api/v1"
}));

async function loadModules() {
  const availability = await import("../src/lib/public-store-availability");
  availability.resetStoreAvailabilityCache();
  const { PublicSiteHeader } = await import("../src/components/public-site-header");
  const { PublicSiteFooter } = await import("../src/components/public-site-footer");
  return { PublicSiteHeader, PublicSiteFooter };
}

function storePayload(products: number, agents: number) {
  return {
    products: Array.from({ length: products }, (_, index) => ({ id: `p${index}` })),
    agents: Array.from({ length: agents }, (_, index) => ({ id: `a${index}` })),
    serviceTypes: []
  };
}

beforeEach(() => {
  apiFetch.mockReset();
  vi.resetModules();
});

afterEach(() => {
  vi.restoreAllMocks();
});

const playStoreUrl = "https://play.google.com/store/apps/details?id=com.intellicash.app";

describe("the public Intelli-Store link", () => {
  it("stays hidden while the store is empty", async () => {
    apiFetch.mockResolvedValue(storePayload(0, 0));
    const { PublicSiteHeader } = await loadModules();

    render(<PublicSiteHeader playStoreUrl={playStoreUrl} />);
    await waitFor(() => expect(apiFetch).toHaveBeenCalled());

    expect(screen.queryByRole("link", { name: "Intelli-Store" })).toBeNull();
    // The rest of the Services menu is untouched.
    expect(screen.getByRole("link", { name: "Guide" })).toBeInTheDocument();
  });

  it("appears once there are products", async () => {
    apiFetch.mockResolvedValue(storePayload(3, 0));
    const { PublicSiteHeader } = await loadModules();

    render(<PublicSiteHeader playStoreUrl={playStoreUrl} />);

    await waitFor(() =>
      expect(screen.getByRole("link", { name: "Intelli-Store" })).toHaveAttribute(
        "href",
        "/intelli-store"
      )
    );
  });

  it("appears when there are bookable field officers but no products", async () => {
    // Products are not the only reason to go there. A programme with agents and
    // an empty catalogue can still be booked.
    apiFetch.mockResolvedValue(storePayload(0, 2));
    const { PublicSiteHeader } = await loadModules();

    render(<PublicSiteHeader playStoreUrl={playStoreUrl} />);

    await waitFor(() =>
      expect(screen.getByRole("link", { name: "Intelli-Store" })).toBeInTheDocument()
    );
  });

  it("is never shown before the answer is known", async () => {
    // Rendering it first and removing it a moment later makes the nav jump and
    // briefly offers a dead end, so the link starts hidden and appears.
    let release: (value: unknown) => void = () => {};
    apiFetch.mockReturnValue(new Promise((resolve) => (release = resolve)));
    const { PublicSiteHeader } = await loadModules();

    render(<PublicSiteHeader playStoreUrl={playStoreUrl} />);
    expect(screen.queryByRole("link", { name: "Intelli-Store" })).toBeNull();

    release(storePayload(1, 0));
    await waitFor(() =>
      expect(screen.getByRole("link", { name: "Intelli-Store" })).toBeInTheDocument()
    );
  });

  it("stays hidden when the store cannot be reached", async () => {
    // Fail closed: if the endpoint is down the page behind the link cannot
    // render either, so the link would lead somewhere broken.
    apiFetch.mockRejectedValue(new Error("network down"));
    const { PublicSiteHeader } = await loadModules();

    render(<PublicSiteHeader playStoreUrl={playStoreUrl} />);
    await waitFor(() => expect(apiFetch).toHaveBeenCalled());

    expect(screen.queryByRole("link", { name: "Intelli-Store" })).toBeNull();
  });

  it("hides it in the footer on the same rule", async () => {
    apiFetch.mockResolvedValue(storePayload(0, 0));
    const { PublicSiteFooter } = await loadModules();

    render(<PublicSiteFooter playStoreUrl={playStoreUrl} />);
    await waitFor(() => expect(apiFetch).toHaveBeenCalled());

    expect(screen.queryByRole("link", { name: "Intelli-Store" })).toBeNull();
    expect(screen.getByRole("link", { name: "Privacy notice" })).toBeInTheDocument();
  });

  it("asks the API once no matter how many components need the answer", async () => {
    apiFetch.mockResolvedValue(storePayload(2, 1));
    const { PublicSiteHeader, PublicSiteFooter } = await loadModules();

    render(
      <>
        <PublicSiteHeader playStoreUrl={playStoreUrl} />
        <PublicSiteFooter playStoreUrl={playStoreUrl} />
      </>
    );

    await waitFor(() => expect(screen.getAllByRole("link", { name: "Intelli-Store" })).toHaveLength(2));
    expect(apiFetch).toHaveBeenCalledTimes(1);
  });
});

describe("the admin console nav", () => {
  it("keeps Intelli-Store whatever the public store holds", async () => {
    // Chicken-and-egg guard: products are added from the console. If this were
    // hidden alongside the public link there would be no way to load the very
    // products that bring the public link back.
    const shell = fs.readFileSync(
      path.resolve(__dirname, "../src/components/dashboard/dashboard-shell.tsx"),
      "utf8"
    );

    expect(shell).toContain('title: "Intelli-Store"');
    expect(shell).not.toContain("useStoreIsLive");
  });
});
