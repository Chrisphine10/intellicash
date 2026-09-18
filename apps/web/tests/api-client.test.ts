import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiClientError, apiFetch } from "../src/lib/api";

describe("API client traceability", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("propagates trace IDs from failed API responses", async () => {
    const traceId = "trace-web-123";
    const fetchMock = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
      const headers = new Headers(init?.headers);

      expect(headers.get("X-Request-Id")).toBe(traceId);
      expect(headers.get("Content-Type")).toBe("application/json");

      return new Response(
        JSON.stringify({
          error: {
            code: "BROKEN",
            message: "The request failed.",
            details: { field: "name" },
            traceId
          }
        }),
        {
          status: 500,
          headers: {
            "Content-Type": "application/json",
            "X-Request-Id": traceId
          }
        }
      );
    });

    vi.stubGlobal("fetch", fetchMock);

    try {
      await apiFetch<never>("/broken", {
        method: "POST",
        headers: { "X-Request-Id": traceId },
        body: JSON.stringify({ ok: false })
      });
      throw new Error("Expected apiFetch to fail");
    } catch (error) {
      expect(error).toBeInstanceOf(ApiClientError);
      expect(error).toMatchObject({
        status: 500,
        code: "BROKEN",
        traceId,
        path: "/broken",
        method: "POST"
      });
      // A server fault carries a short reference for support, not the raw trace.
      expect((error as Error).message).toBe(`The request failed. (Reference: ${traceId.slice(0, 8)})`);
    }
  });

  it("shows a person what to do on a wrong password, with no trace ID", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () =>
        new Response(
          JSON.stringify({ error: { code: "INVALID_CREDENTIALS", message: "Invalid credentials.", traceId: "5f32dfcb-4d19" } }),
          { status: 401, headers: { "Content-Type": "application/json" } }
        )
      )
    );

    const error = await apiFetch<never>("/auth/login", { method: "POST" }).catch((caught: unknown) => caught as Error);
    expect(error.message).toMatch(/do not match an account/);
    expect(error.message).toMatch(/code sent by SMS/);
    expect(error.message).not.toMatch(/Trace|Reference|5f32dfcb/);
    expect(error).toMatchObject({ traceId: "5f32dfcb-4d19" });
  });

  it("names the problem when the server only says validation failed", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () =>
        new Response(JSON.stringify({ error: { code: "VALIDATION_ERROR", message: "Request validation failed." } }), {
          status: 400,
          headers: { "Content-Type": "application/json" }
        })
      )
    );

    const error = await apiFetch<never>("/x", { method: "POST" }).catch((caught: unknown) => caught as Error);
    expect(error.message).toBe("Some details are missing or not valid. Check the form and try again.");
  });

  it("tells an offline user to check their connection", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => {
        throw new TypeError("Failed to fetch");
      })
    );
    const error = await apiFetch<never>("/x", { headers: { "X-Request-Id": "abcdef1234" } }).catch((caught: unknown) => caught as Error);
    expect(error.message).toBe(
      "We could not reach IntelliCash. Check your internet connection and try again. (Reference: abcdef12)"
    );
  });

  it("wraps network failures with a trace ID", async () => {
    const traceId = "trace-network-123";

    vi.stubGlobal(
      "fetch",
      vi.fn(async () => {
        throw new TypeError("Failed to fetch");
      })
    );

    await expect(
      apiFetch("/offline", {
        headers: { "X-Request-Id": traceId }
      })
    ).rejects.toMatchObject({
      status: 0,
      code: "NETWORK_ERROR",
      traceId,
      path: "/offline",
      method: "GET"
    });
  });
});
