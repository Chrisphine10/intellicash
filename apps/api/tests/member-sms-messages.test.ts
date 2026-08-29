import { describe, expect, it } from "vitest";
import {
  buildMeetingSummarySms,
  buildSharePurchaseSms,
  formatSmsMoney,
  smsFirstName,
  smsSegments
} from "../src/domain/member-sms-messages";

const meetingDate = new Date("2026-08-29T15:30:00.000Z");

describe("member SMS wording", () => {
  it("prints whole shillings without cents and keeps cents when they exist", () => {
    expect(formatSmsMoney(50_000)).toBe("KES 500");
    expect(formatSmsMoney(50_050)).toBe("KES 500.50");
    expect(formatSmsMoney(0)).toBe("KES 0");
  });

  it("opens with a first name", () => {
    expect(smsFirstName("Mary Wanjiku Kamau")).toBe("Mary");
    expect(smsFirstName("  ")).toBe("Member");
  });

  it("confirms a share purchase with the amount, group, day and running total", () => {
    const body = buildSharePurchaseSms({
      memberName: "Mary Wanjiku",
      groupName: "Karibu VSLA",
      amountCents: 50_000,
      cycleSharesCents: 450_000,
      recordedAt: meetingDate
    });

    expect(body).toBe(
      "Mary: KES 500 shares recorded at Karibu VSLA on 29 Aug 2026. " +
        "Your shares this cycle: KES 4,500. Query? Ask your secretary."
    );
    expect(smsSegments(body)).toBe(1);
  });

  it("dates a late-evening meeting by the Kenyan day, not UTC", () => {
    // 22:30 in Nairobi on the 29th is 19:30 UTC. A naive UTC format is right
    // here but wrong an hour later; this pins the timezone, not the luck.
    const body = buildSharePurchaseSms({
      memberName: "Mary",
      groupName: "Karibu VSLA",
      amountCents: 100,
      cycleSharesCents: 100,
      recordedAt: new Date("2026-08-29T21:30:00.000Z")
    });
    expect(body).toContain("30 Aug 2026");
  });

  it("summarises only what the member actually did", () => {
    const body = buildMeetingSummarySms({
      memberName: "Mary Wanjiku",
      groupName: "Karibu VSLA",
      meetingDate,
      totals: {
        sharesCents: 50_000,
        socialCents: 5_000,
        finesCents: 0,
        loanRepaidCents: 20_000,
        loanReceivedCents: 0
      },
      loanBalanceCents: 180_000
    });

    expect(body).toBe(
      "Mary: Karibu VSLA meeting of 29 Aug 2026 is closed. " +
        "You: shares KES 500, social KES 50, loan repaid KES 200. " +
        "Loan balance KES 1,800. Query? Ask your secretary."
    );
    // A zero fine is not worth a line, let alone a second segment.
    expect(body).not.toContain("fine");
  });

  it("tells a member who transacted nothing exactly that", () => {
    const body = buildMeetingSummarySms({
      memberName: "Otieno",
      groupName: "Karibu VSLA",
      meetingDate,
      totals: {
        sharesCents: 0,
        socialCents: 0,
        finesCents: 0,
        loanRepaidCents: 0,
        loanReceivedCents: 0
      },
      loanBalanceCents: 0
    });

    expect(body).toContain("nothing recorded for you");
  });

  it("says nothing about a loan balance it was not given", () => {
    const body = buildMeetingSummarySms({
      memberName: "Otieno",
      groupName: "Karibu VSLA",
      meetingDate,
      totals: {
        sharesCents: 10_000,
        socialCents: 0,
        finesCents: 0,
        loanRepaidCents: 0,
        loanReceivedCents: 0
      },
      loanBalanceCents: null
    });

    // "Loan balance KES 0" to a member who owes money would read as cleared.
    expect(body).not.toContain("Loan balance");
  });

  it("keeps a busy member's summary inside two segments", () => {
    const body = buildMeetingSummarySms({
      memberName: "Mary Wanjiku",
      groupName: "Kiambu Wendani Women Group",
      meetingDate,
      totals: {
        sharesCents: 250_000,
        socialCents: 5_000,
        finesCents: 2_000,
        loanRepaidCents: 120_000,
        loanReceivedCents: 500_000
      },
      loanBalanceCents: 550_000
    });

    expect(smsSegments(body)).toBeLessThanOrEqual(2);
  });
});
