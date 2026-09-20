import { describe, expect, it } from "vitest";
import {
  CREDIT_FACTORS,
  CREDIT_RATING_CONTRACT_VERSION,
  MIN_MEETINGS_TO_RATE,
  bandForScore,
  creditTermsForBand,
  evaluateCreditRating,
  type CreditRatingFacts
} from "../src/domain/credit-rating-contract";

/** A well-run group: full committee, disciplined meetings, loans repaid. */
function exemplaryFacts(): CreditRatingFacts {
  return {
    activeMembers: 10,
    officialRoles: {
      chairperson: 1,
      secretary: 1,
      treasurer: 1,
      moneyCounter: 1,
      keyHolder: 1
    },
    hasConstitution: true,
    votesRecorded: 10,
    meetingsTotal: 10,
    meetingsOpened: 10,
    meetingsUnlockCompliant: 10,
    meetingsSealed: 10,
    meetingsWithSharePurchase: 10,
    meetingsWithSocialContribution: 10,
    attendanceRecords: 100,
    attendancePresent: 100,
    loanDisbursedCents: 100000,
    loanRepaidCents: 100000,
    cycleNumber: 3
  };
}

/** A group that exists but does nothing right. */
function poorFacts(): CreditRatingFacts {
  return {
    activeMembers: 5,
    officialRoles: {
      chairperson: 0,
      secretary: 0,
      treasurer: 0,
      moneyCounter: 0,
      keyHolder: 0
    },
    hasConstitution: false,
    votesRecorded: 0,
    meetingsTotal: 10,
    meetingsOpened: 10,
    meetingsUnlockCompliant: 0,
    meetingsSealed: 0,
    meetingsWithSharePurchase: 0,
    meetingsWithSocialContribution: 0,
    attendanceRecords: 50,
    attendancePresent: 0,
    loanDisbursedCents: 100000,
    loanRepaidCents: 0,
    cycleNumber: 1
  };
}

describe("credit rating contract — shape", () => {
  it("weights total exactly 100 and split 40/60 across the two pillars", () => {
    const total = CREDIT_FACTORS.reduce((sum, f) => sum + f.weight, 0);
    expect(total).toBe(100);

    const governance = CREDIT_FACTORS.filter((f) => f.pillar === "GOVERNANCE")
      .reduce((s, f) => s + f.weight, 0);
    const compliance = CREDIT_FACTORS.filter((f) => f.pillar === "COMPLIANCE")
      .reduce((s, f) => s + f.weight, 0);
    expect(governance).toBe(40);
    expect(compliance).toBe(60);
  });

  it("stamps the contract version on every rating", () => {
    expect(evaluateCreditRating(exemplaryFacts()).contractVersion).toBe(
      CREDIT_RATING_CONTRACT_VERSION
    );
  });

  it("is deterministic — same facts always produce the same score", () => {
    const facts = exemplaryFacts();
    const a = evaluateCreditRating(facts);
    const b = evaluateCreditRating(facts);
    expect(a.score).toBe(b.score);
    expect(a.band).toBe(b.band);
  });
});

describe("credit rating contract — scoring", () => {
  it("gives a perfectly-run group 100 and band A", () => {
    const rating = evaluateCreditRating(exemplaryFacts());
    expect(rating.score).toBe(100);
    expect(rating.band).toBe("A");
    expect(rating.bandLabel).toBe("Excellent");
    expect(rating.rated).toBe(true);
    expect(rating.pillars.governance).toBe(40);
    expect(rating.pillars.compliance).toBe(60);
  });

  it("floors a non-compliant group into band D", () => {
    const rating = evaluateCreditRating(poorFacts());
    expect(rating.band).toBe("D");
    // Repayment has real (bad) evidence — 0 repaid of what was lent — so it
    // earns nothing, not the no-evidence baseline.
    const repayment = rating.factors.find((f) => f.key === "repaymentRate")!;
    expect(repayment.fromBaseline).toBe(false);
    expect(repayment.points).toBe(0);
    // The only points available are cycleMaturity: cycle 1 of 3 = 1/3 of 3 pts.
    expect(rating.score).toBe(1);
  });

  it("scores each pillar independently", () => {
    // Perfect governance, zero compliance evidence except loans repaid.
    const facts: CreditRatingFacts = {
      ...poorFacts(),
      officialRoles: {
        chairperson: 1,
        secretary: 1,
        treasurer: 1,
        moneyCounter: 1,
        keyHolder: 1
      },
      hasConstitution: true,
      votesRecorded: 10,
      meetingsSealed: 10,
      meetingsUnlockCompliant: 10
    };
    const rating = evaluateCreditRating(facts);
    expect(rating.pillars.governance).toBe(40);
    // meetingCompletion now scores (10/10 sealed) = 6 pts of compliance.
    expect(rating.pillars.compliance).toBeGreaterThan(0);
  });

  it("weights leadership: core offices carry 80%, support roles 20%", () => {
    const base = poorFacts();
    const coreOnly = evaluateCreditRating({
      ...base,
      officialRoles: { chairperson: 1, secretary: 1, treasurer: 1, moneyCounter: 0, keyHolder: 0 }
    }).factors.find((f) => f.key === "leadershipComplete")!;
    expect(coreOnly.rawScore).toBe(80);

    const partial = evaluateCreditRating({
      ...base,
      officialRoles: { chairperson: 1, secretary: 0, treasurer: 0, moneyCounter: 0, keyHolder: 0 }
    }).factors.find((f) => f.key === "leadershipComplete")!;
    expect(Math.round(partial.rawScore)).toBe(27); // (1/3)*80
  });
});

describe("credit rating contract — evidence handling", () => {
  it("marks a group with too little history UNRATED rather than D", () => {
    const rating = evaluateCreditRating({
      ...exemplaryFacts(),
      meetingsTotal: MIN_MEETINGS_TO_RATE - 1,
      meetingsOpened: 1,
      meetingsSealed: 1,
      meetingsUnlockCompliant: 1,
      meetingsWithSharePurchase: 1,
      meetingsWithSocialContribution: 1
    });
    expect(rating.rated).toBe(false);
    expect(rating.band).toBe("UNRATED");
    expect(rating.recommendations[0]).toContain("credit rating");
  });

  it("treats no lending history as a neutral baseline, not a free pass", () => {
    const rating = evaluateCreditRating({
      ...exemplaryFacts(),
      loanDisbursedCents: 0,
      loanRepaidCents: 0
    });
    const repayment = rating.factors.find((f) => f.key === "repaymentRate")!;
    expect(repayment.fromBaseline).toBe(true);
    expect(repayment.rawScore).toBe(50); // declared baseline
    expect(repayment.evidence).toBe("No loans issued yet");
    // 100 - (20 weight * 50% shortfall) = 90
    expect(rating.score).toBe(90);
  });

  it("never lets a factor exceed its weight or drop below zero", () => {
    const rating = evaluateCreditRating({
      ...exemplaryFacts(),
      // Over-repaid and over-voted: ratios must clamp at 100.
      loanRepaidCents: 999999,
      votesRecorded: 999
    });
    for (const factor of rating.factors) {
      expect(factor.rawScore).toBeLessThanOrEqual(100);
      expect(factor.rawScore).toBeGreaterThanOrEqual(0);
      expect(factor.points).toBeLessThanOrEqual(factor.weight);
    }
    expect(rating.score).toBeLessThanOrEqual(100);
  });

  it("survives an all-zero group without throwing", () => {
    const empty: CreditRatingFacts = {
      activeMembers: 0,
      officialRoles: { chairperson: 0, secretary: 0, treasurer: 0, moneyCounter: 0, keyHolder: 0 },
      hasConstitution: false,
      votesRecorded: 0,
      meetingsTotal: 0,
      meetingsOpened: 0,
      meetingsUnlockCompliant: 0,
      meetingsSealed: 0,
      meetingsWithSharePurchase: 0,
      meetingsWithSocialContribution: 0,
      attendanceRecords: 0,
      attendancePresent: 0,
      loanDisbursedCents: 0,
      loanRepaidCents: 0,
      cycleNumber: 0
    };
    const rating = evaluateCreditRating(empty);
    expect(rating.band).toBe("UNRATED");
    // Only the repayment baseline (50 * 20/100 = 10) can score.
    expect(rating.score).toBe(10);
    expect(rating.rated).toBe(false);
  });

  it("explains every factor with evidence and a guideline", () => {
    const rating = evaluateCreditRating(exemplaryFacts());
    expect(rating.factors).toHaveLength(CREDIT_FACTORS.length);
    for (const factor of rating.factors) {
      expect(factor.evidence.length).toBeGreaterThan(0);
      expect(factor.guideline.length).toBeGreaterThan(0);
    }
  });

  it("recommends the biggest scoring gaps first", () => {
    const rating = evaluateCreditRating({
      ...exemplaryFacts(),
      loanRepaidCents: 0, // -20 pts, the largest single gap
      meetingsWithSocialContribution: 0 // -4 pts
    });
    expect(rating.recommendations[0]).toContain("Loan repayment");
    expect(rating.recommendations.length).toBeLessThanOrEqual(3);
  });
});

describe("credit rating contract — bands and terms", () => {
  it("maps scores to bands at the published thresholds", () => {
    expect(bandForScore(100)).toBe("A");
    expect(bandForScore(80)).toBe("A");
    expect(bandForScore(79)).toBe("B");
    expect(bandForScore(65)).toBe("B");
    expect(bandForScore(64)).toBe("C");
    expect(bandForScore(50)).toBe("C");
    expect(bandForScore(49)).toBe("D");
    expect(bandForScore(0)).toBe("D");
  });

  it("prices credit better for better bands", () => {
    const a = creditTermsForBand("A");
    const b = creditTermsForBand("B");
    const c = creditTermsForBand("C");
    const d = creditTermsForBand("D");

    // Deposit rises as the band falls.
    expect(a.depositRateBps).toBeLessThan(b.depositRateBps);
    expect(b.depositRateBps).toBeLessThan(c.depositRateBps);
    expect(c.depositRateBps).toBeLessThan(d.depositRateBps);

    // Interest rises as the band falls.
    expect(a.interestMultiplierBps).toBeLessThan(d.interestMultiplierBps);

    // Longer terms for better bands.
    expect(a.maxInstallments).toBeGreaterThan(d.maxInstallments);
  });

  it("treats an unrated group as conservatively as the weakest band", () => {
    const unrated = creditTermsForBand("UNRATED");
    const d = creditTermsForBand("D");
    expect(unrated.depositRateBps).toBe(d.depositRateBps);
    expect(unrated.maxInstallments).toBe(d.maxInstallments);
  });

  it("attaches the band's terms to the rating", () => {
    const rating = evaluateCreditRating(exemplaryFacts());
    expect(rating.terms.band).toBe("A");
    expect(rating.terms.depositRateBps).toBe(1000);
  });
});

describe("meetings held on a phone count as completed", () => {
  it("does not score a group that meets on a phone as having completed no meetings", async () => {
    // Found on the emulator: the agent's rating screen said "0 of 5 meetings
    // sealed" for a group that had recorded money and attendance in all five —
    // phone meetings are never sealed on the server — and priced it as Band D.
    const { prisma } = await import("../src/lib/prisma");
    const { gatherCreditFacts } = await import("../src/services/credit-rating-service");
    const group = await prisma.group.create({
      data: { name: "Phone Kept Group", code: `PKG-${Date.now()}`, county: "Embu", phase: "MOBILISATION" },
      select: { id: true }
    });
    const member = await prisma.member.create({
      data: { groupId: group.id, fullName: "Phone Kept Member", phone: `2547${String(Date.now()).slice(-8)}`, status: "ACTIVE" },
      select: { id: true }
    });
    const held = await prisma.meeting.create({
      data: { groupId: group.id, title: "Held on a phone", scheduledAt: new Date(), status: "SCHEDULED" }
    });
    await prisma.attendance.create({ data: { meetingId: held.id, memberId: member.id, status: "PRESENT" } });
    await prisma.meeting.create({
      data: { groupId: group.id, title: "Only planned", scheduledAt: new Date(Date.now() + 7 * 24 * 3600 * 1000), status: "SCHEDULED" }
    });
    await prisma.meeting.create({
      data: { groupId: group.id, title: "Sealed on the server", scheduledAt: new Date(), status: "SEALED" }
    });

    const facts = await gatherCreditFacts(group.id);
    expect(facts.meetingsTotal).toBe(3);
    // The held one and the sealed one; the merely planned one is not complete.
    expect(facts.meetingsSealed).toBe(2);
  }, 60000);
});
