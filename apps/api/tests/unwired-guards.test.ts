import { describe, expect, it } from "vitest";
import { assertAppendOnlyOperation } from "../src/domain/ledger";

/**
 * Guards that existed and were called from nowhere.
 *
 * Both were written for a property the system is described as having, tested in
 * isolation, and then never wired — so the property rested on convention. These
 * assert the behaviour now that the guards are actually in the path.
 */
describe("the ledger append-only guard", () => {
  it("allows a row to be created", () => {
    expect(() => assertAppendOnlyOperation("create")).not.toThrow();
  });

  it("refuses a delete outright", () => {
    expect(() => assertAppendOnlyOperation("delete")).toThrow(/append-only/i);
  });

  it("refuses an update that changes the money or the parties", () => {
    for (const field of ["amountCents", "direction", "memberId", "groupId", "signature", "type"]) {
      expect(() => assertAppendOnlyOperation("update", [field]), field).toThrow(/append-only/i);
    }
  });

  it("names the offending field, so the failure is actionable", () => {
    expect(() => assertAppendOnlyOperation("update", ["description", "amountCents"]))
      .toThrow(/amountCents/);
  });

  it("permits a back-link, which is the one thing that arrives later", () => {
    // Attaching a repayment to the loan it settles moves no money. The
    // all-or-nothing version could not allow this, which is presumably why it
    // was never wired to anything.
    expect(() => assertAppendOnlyOperation("update", ["loanId"])).not.toThrow();
  });

  it("refuses a back-link smuggled alongside a financial change", () => {
    expect(() => assertAppendOnlyOperation("update", ["loanId", "amountCents"]))
      .toThrow(/amountCents/);
  });
});
