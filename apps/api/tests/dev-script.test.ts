import { readFile } from "node:fs/promises";
import { describe, expect, it } from "vitest";

describe("API dev script", () => {
  it("pins the local API port expected by the web client", async () => {
    const packageJson = JSON.parse(
      await readFile(new URL("../package.json", import.meta.url), "utf8")
    ) as { scripts: Record<string, string> };

    expect(packageJson.scripts.dev).toContain("API_PORT=4000");
  });
});
