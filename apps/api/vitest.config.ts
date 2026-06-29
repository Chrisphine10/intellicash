import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    environment: "node",
    globals: true,
    include: ["tests/**/*.test.ts"],
    testTimeout: 15000,
    // Test files share a single SQLite database and each reseeds it, so they
    // must not run in parallel worker threads (sequence.concurrent only
    // serialises tests *within* a file, not across files).
    fileParallelism: false,
    sequence: {
      concurrent: false
    }
  }
});
