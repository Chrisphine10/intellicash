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
  /*
   * The automatic JSX runtime, as Next itself compiles.
   *
   * tsconfig says `"jsx": "preserve"` because Next owns that transform, and
   * with no React plugin here Vite fell back to esbuild's CLASSIC runtime --
   * `React.createElement`. A page that does not import React by name therefore
   * threw "React is not defined" the moment a test rendered it, which is why
   * the suite only ever covered the pages that happened to import it. That is
   * a property of the test setup, not of the page.
   */
  esbuild: { jsx: "automatic" },

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
