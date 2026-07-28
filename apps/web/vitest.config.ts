import { resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vitest/config";

const rootDir = fileURLToPath(new URL(".", import.meta.url));

export default defineConfig({
  resolve: {
    alias: {
      "@": resolve(rootDir, "src")
    }
  },
  test: {
    environment: "jsdom",
    globals: true,
    include: ["tests/**/*.test.ts", "tests/**/*.test.tsx"],
    setupFiles: ["tests/setup.ts"],
    // The demo login panel is opt-in and OFF in production builds. The suite
    // exercises the feature itself, so it enables the flag here; the
    // "hidden by default" case is covered explicitly in
    // tests/demo-login-disabled.test.tsx, which unsets it.
    env: { NEXT_PUBLIC_ENABLE_DEMO_LOGIN: "true" }
  }
});
