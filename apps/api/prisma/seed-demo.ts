/**
 * A demo group with every module populated, for testing against a real server.
 *
 *   npm run db:seed:demo -w @intellicash/api
 *
 * Layered ON TOP of `seed.ts` rather than folded into it. The base seed is
 * what 328 tests build their fixtures from; adding loans, welfare spending and
 * officials there would rewrite the ground every one of them stands on. This
 * runs separately and touches nothing the tests read.
 *
 * Money is written through `appendLedgerEntry`, the same function the API uses
 * — not straight into the tables. That is the whole point: it stamps the cycle,
 * signs the entry, moves the fund balance and creates the `Loan` projection, so
 * the demo data is shaped exactly like data a real group would produce. Writing
 * rows directly would create a database no production code path could ever have
 * made, and the screens would be tested against a fiction.
 *
 * Idempotent: the demo group is dropped and rebuilt on every run.
 */
import { randomBytes } from "node:crypto";
import bcrypt from "bcryptjs";
import { prisma } from "../src/lib/prisma";
import { appendLedgerEntry } from "../src/routes/groups";
import { encryptCredentials } from "../src/services/integration-credentials";

const DEMO_CODE = "IWL-DEMO-0001";
const DEMO_PIN = "112233";

/**
 * The demo accounts' password.
 *
 * `IntellicashDemo#2026` is a constant in the shared package and therefore
 * public. That is fine on a laptop and not fine on a live server, where it
 * would be a known-password account against real infrastructure. On production
 * a strong one is generated and printed ONCE; set DEMO_PASSWORD to choose your
 * own.
 */
function resolveDemoPassword() {
  const supplied = process.env.DEMO_PASSWORD?.trim();
  if (supplied) return { password: supplied, generated: false };

  if (process.env.NODE_ENV === "production") {
    const password = `Demo-${randomBytes(12).toString("base64url")}`;
    return { password, generated: true };
  }

  return { password: "IntellicashDemo#2026", generated: false };
}

/** Members of the demo group, with the offices a VSLA actually fills. */
const ROSTER = [
  { name: "Naomi Wairimu", phone: "254720100001", role: "CHAIRPERSON" },
  { name: "Esther Adhiambo", phone: "254720100002", role: "SECRETARY" },
  { name: "Lucy Kamene", phone: "254720100003", role: "TREASURER" },
  { name: "Beatrice Nyambura", phone: "254720100004", role: "MONEY_COUNTER" },
  { name: "Sarah Chebet", phone: "254720100005", role: "KEY_HOLDER" },
  { name: "Miriam Atieno", phone: "254720100006", role: "MEMBER" },
  { name: "Peninah Wangui", phone: "254720100007", role: "MEMBER" },
  { name: "Judith Anyango", phone: "254720100008", role: "MEMBER" }
] as const;

function log(step: string, detail = "") {
  console.log(`  ${step.padEnd(34)}${detail}`);
}

const { password: demoPassword, generated: passwordGenerated } = resolveDemoPassword();

async function main() {
  console.log("\nDemo data for testing\n");

  // ---- start clean -------------------------------------------------------
  const existing = await prisma.group.findFirst({ where: { code: DEMO_CODE } });
  if (existing) {
    /**
     * `LedgerEntry.group` is `onDelete: Restrict` on purpose: deleting a group
     * must never silently destroy its money record. That protection is right,
     * so this steps around it explicitly for the demo group rather than
     * weakening the constraint for every group on the platform.
     *
     * Order is forced by the foreign keys: welfare expenses point at ledger
     * entries, repayments point at loans, and users do not cascade from a
     * group at all (deleting a group should not delete people's sign-ins).
     */
    const groupId = existing.id;
    await prisma.welfareExpense.deleteMany({ where: { groupId } });
    await prisma.ledgerEntry.deleteMany({ where: { groupId } });
    await prisma.loan.deleteMany({ where: { groupId } });
    await prisma.user.deleteMany({ where: { groupId } });
    await prisma.group.delete({ where: { id: groupId } });
    log("removed previous demo group", DEMO_CODE);
  }

  /**
   * Stand alone rather than depending on the base seed.
   *
   * A production database has no programmes — and it must not be given the
   * base seed to get one, because that fixture ships demo accounts on a
   * password published in the repo. So the minimum scaffolding a group needs
   * is created here if it is missing, and reused if it is not.
   */
  const partner =
    (await prisma.partner.findFirst()) ??
    (await prisma.partner.create({
      data: { name: "Demo Programme Partner", type: "NGO", status: "ACTIVE" }
    }));

  const programme =
    (await prisma.programme.findFirst()) ??
    (await prisma.programme.create({
      data: {
        partnerId: partner.id,
        name: "Demo Programme",
        country: "Kenya",
        county: "Embu",
        // ONGOING, not the DRAFT default: the public store only lists products
        // whose programme is ongoing, so a draft programme yields an empty
        // catalogue and the store looks broken rather than unconfigured.
        publicStatus: "ONGOING",
        publicSlug: "demo-programme",
        description: "Scaffolding for the demo group. Safe to delete once real programmes exist."
      }
    }));

  const villageAgent = await prisma.villageAgent.findFirst();

  // ---- the group ---------------------------------------------------------
  const group = await prisma.group.create({
    data: {
      programmeId: programme.id,
      villageAgentId: villageAgent?.id ?? null,
      name: "Demo Test VSLA",
      code: DEMO_CODE,
      phase: "INTENSIVE",
      county: "Embu",
      subCounty: "Manyatta",
      meetingDay: "Tuesday",
      gpsLatitude: -0.5389,
      gpsLongitude: 37.4597,
      gpsRadiusMeters: 100,
      constitutionVersion: "IWLSGS-1.0",
      cycleNumber: 1
    }
  });
  log("group", `${group.name} (${group.code})`);

  const funds = await Promise.all(
    (["INTERNAL_LOAN", "SOCIAL", "EXTERNAL_LOAN", "GRANT", "VSLF"] as const).map((type) =>
      prisma.fundAccount.create({
        data: { groupId: group.id, type, balanceCents: 0, currency: "KES" }
      })
    )
  );
  const loanFund = funds.find((f) => f.type === "INTERNAL_LOAN")!;
  const socialFund = funds.find((f) => f.type === "SOCIAL")!;

  // ---- members, each able to unlock a meeting ----------------------------
  const pinHash = await bcrypt.hash(DEMO_PIN, 12);
  const members: Array<{ id: string; fullName: string }> = [];
  for (const person of ROSTER) {
    members.push(
      await prisma.member.create({
        data: {
          groupId: group.id,
          fullName: person.name,
          phone: person.phone,
          role: person.role,
          status: "ACTIVE",
          kycStatus: "VERIFIED",
          pinHash,
          pinSetAt: new Date()
        }
      })
    );
  }
  log("members", `${members.length}, all with PIN ${DEMO_PIN}`);

  // ---- a sign-in for this group -----------------------------------------
  //
  // A GROUP_ACCOUNT is scoped to ONE group, so the existing demo account can
  // never see this one — it would sign in and find nothing. This group needs
  // an account of its own, and a member account to test the member's own view.
  const accountPassword = await bcrypt.hash(demoPassword, 12);
  await prisma.user.deleteMany({
    where: {
      email: {
        in: [
          "demo.group@intellicash.co.ke",
          "demo.member@intellicash.co.ke",
          "demo.agent@intellicash.co.ke"
        ]
      }
    }
  });
  await prisma.user.create({
    data: {
      name: "Demo Group Account",
      email: "demo.group@intellicash.co.ke",
      phone: "254720100100",
      passwordHash: accountPassword,
      role: "GROUP_ACCOUNT",
      groupId: group.id,
      status: "ACTIVE"
    }
  });
  await prisma.user.create({
    data: {
      name: members[0]!.fullName,
      email: "demo.member@intellicash.co.ke",
      phone: "254720100101",
      passwordHash: accountPassword,
      role: "MEMBER",
      groupId: group.id,
      memberId: members[0]!.id,
      status: "ACTIVE"
    }
  });
  // A Village Agent / CBT sees exactly the groups whose `villageAgentId` is
  // theirs (see account-scope.ts), so the login alone is not enough — without
  // the VillageAgent record AND the group pointing at it, the agent signs in
  // to an empty caseload, which looks identical to the app being broken.
  const agent =
    (await prisma.villageAgent.findFirst({
      where: { email: "demo.agent@intellicash.co.ke" }
    })) ??
    (await prisma.villageAgent.create({
      data: {
        programmeId: programme.id,
        name: "Grace Wanjiku",
        phone: "254720100102",
        email: "demo.agent@intellicash.co.ke",
        county: "Bungoma",
        status: "ACTIVE"
      }
    }));
  await prisma.group.update({
    where: { id: group.id },
    data: { villageAgentId: agent.id }
  });
  await prisma.user.create({
    data: {
      name: agent.name,
      email: "demo.agent@intellicash.co.ke",
      phone: "254720100102",
      passwordHash: accountPassword,
      role: "VILLAGE_AGENT",
      villageAgentId: agent.id,
      status: "ACTIVE"
    }
  });
  log("sign-ins", "demo.group@, demo.member@ and demo.agent@intellicash.co.ke");

  // ---- the group's own rules --------------------------------------------
  await prisma.groupPolicy.create({
    data: {
      groupId: group.id,
      defaultLoanTermMonths: 3,
      loanInterestRateBps: 1000, // 10% a month, the common VSLA rate
      expenseFundType: "SOCIAL"
    }
  });
  log("group policy", "3-month loans at 10% a month");

  // ---- who holds which office -------------------------------------------
  const cycle = await prisma.cycle.findFirst({ where: { groupId: group.id, status: "ACTIVE" } });
  for (const [index, person] of ROSTER.entries()) {
    if (person.role === "MEMBER") continue;
    await prisma.memberRoleAssignment.create({
      data: {
        groupId: group.id,
        memberId: members[index]!.id,
        cycleId: cycle?.id ?? null,
        role: person.role,
        startedAt: new Date(Date.now() - 60 * 24 * 3600 * 1000),
        note: "Elected at the cycle-opening meeting"
      }
    });
  }
  log("officials", "chairperson, secretary, treasurer, counter, key holder");

  // ---- per-group payment providers ---------------------------------------
  // Both providers configured so the screen has something real to show. The
  // Daraja row is explicitly SANDBOX and the Paystack key is an sk_test_ key,
  // so nothing here can move real money even by accident.
  await prisma.groupIntegrationConfig.create({
    data: {
      groupId: group.id,
      provider: "MPESA_DARAJA",
      enabled: true,
      mode: "SANDBOX",
      credentialsUpdatedAt: new Date(),
      credentialsJson: encryptCredentials({
        MPESA_CONSUMER_KEY: "demo-consumer-key",
        MPESA_CONSUMER_SECRET: "demo-consumer-secret",
        MPESA_SHORTCODE: "174379",
        MPESA_PASSKEY: "demo-passkey",
        MPESA_ENVIRONMENT: "SANDBOX"
      })
    }
  });
  await prisma.groupIntegrationConfig.create({
    data: {
      groupId: group.id,
      provider: "PAYSTACK",
      enabled: true,
      mode: "SANDBOX",
      credentialsUpdatedAt: new Date(),
      credentialsJson: encryptCredentials({
        PAYSTACK_SECRET_KEY: "sk_test_demo0000000000000000000000000000",
        PAYSTACK_PUBLIC_KEY: "pk_test_demo0000000000000000000000000000"
      })
    }
  });
  log("payment providers", "M-Pesa Daraja (sandbox) + Paystack (test key)");

  // ---- a sealed meeting's worth of money, then an open one ---------------
  const sealed = await prisma.meeting.create({
    data: {
      groupId: group.id,
      title: "Week 1 — savings and loans",
      scheduledAt: new Date(Date.now() - 35 * 24 * 3600 * 1000),
      openedAt: new Date(Date.now() - 35 * 24 * 3600 * 1000),
      closedAt: new Date(Date.now() - 35 * 24 * 3600 * 1000),
      status: "SEALED",
      unlockStatus: "OFFICIALS_VERIFIED",
      sealedByMemberId: members[1]!.id
    }
  });

  const open = await prisma.meeting.create({
    data: {
      groupId: group.id,
      title: "This week's meeting",
      scheduledAt: new Date(),
      openedAt: new Date(),
      status: "IN_PROGRESS",
      unlockStatus: "OFFICIALS_VERIFIED"
    }
  });
  log("meetings", "one sealed, one OPEN for recording");

  // Everything below goes through the real money path.
  await prisma.$transaction(async (tx) => {
    for (const member of members) {
      await appendLedgerEntry(tx, {
        groupId: group.id,
        memberId: member.id,
        meetingId: sealed.id,
        fundAccountId: loanFund.id,
        type: "SHARE_PURCHASE",
        amountCents: 500_000,
        direction: "CREDIT",
        description: "Shares bought"
      });
      await appendLedgerEntry(tx, {
        groupId: group.id,
        memberId: member.id,
        meetingId: sealed.id,
        fundAccountId: socialFund.id,
        type: "SOCIAL_CONTRIBUTION",
        amountCents: 50_000,
        direction: "CREDIT",
        description: "Welfare contribution"
      });
    }

    // Two fines, so the social fund is not uniform.
    await appendLedgerEntry(tx, {
      groupId: group.id,
      memberId: members[6]!.id,
      meetingId: sealed.id,
      fundAccountId: socialFund.id,
      type: "FINE_COLLECTION",
      amountCents: 10_000,
      direction: "CREDIT",
      description: "Late to the meeting"
    });
  }, { timeout: 30_000 });
  log("savings", "8 x KSh 5,000 shares, 8 x KSh 500 welfare, 1 fine");

  // ---- loans, which now create Loan rows through the projection ----------
  await prisma.$transaction(async (tx) => {
    await appendLedgerEntry(tx, {
      groupId: group.id,
      memberId: members[5]!.id,
      meetingId: sealed.id,
      fundAccountId: loanFund.id,
      type: "INTERNAL_LOAN_DISBURSEMENT",
      amountCents: 1_000_000,
      direction: "DEBIT",
      description: "Business stock"
    });
    await appendLedgerEntry(tx, {
      groupId: group.id,
      memberId: members[7]!.id,
      meetingId: sealed.id,
      fundAccountId: loanFund.id,
      type: "INTERNAL_LOAN_DISBURSEMENT",
      amountCents: 600_000,
      direction: "DEBIT",
      description: "School fees"
    });
  }, { timeout: 30_000 });

  // Backdate one loan so it has accrued interest to show, and part-repay it.
  const firstLoan = await prisma.loan.findFirst({
    where: { groupId: group.id, memberId: members[5]!.id }
  });
  if (firstLoan) {
    await prisma.loan.update({
      where: { id: firstLoan.id },
      data: { disbursedAt: new Date(Date.now() - 31 * 24 * 3600 * 1000) }
    });
  }
  await prisma.$transaction(async (tx) => {
    await appendLedgerEntry(tx, {
      groupId: group.id,
      memberId: members[5]!.id,
      meetingId: sealed.id,
      fundAccountId: loanFund.id,
      type: "LOAN_REPAYMENT",
      amountCents: 300_000,
      direction: "CREDIT",
      description: "First repayment"
    });
  }, { timeout: 30_000 });

  const loans = await prisma.loan.count({ where: { groupId: group.id } });
  log("loans", `${loans} active, one a month old with interest and a repayment`);

  // ---- welfare spending, inside the open meeting -------------------------
  await prisma.$transaction(async (tx) => {
    const entry = await appendLedgerEntry(tx, {
      groupId: group.id,
      memberId: members[3]!.id,
      meetingId: open.id,
      fundAccountId: socialFund.id,
      type: "WELFARE_EXPENSE",
      amountCents: 120_000,
      direction: "DEBIT",
      description: "Hospital bill"
    });
    await tx.welfareExpense.create({
      data: {
        groupId: group.id,
        cycleId: entry.cycleId,
        meetingId: open.id,
        ledgerEntryId: entry.id,
        category: "MEDICAL",
        payeeMemberId: members[3]!.id,
        note: "Maternity bill at Embu Level 5"
      }
    });
  }, { timeout: 30_000 });
  log("welfare", "KSh 1,200 paid out, recorded in the open meeting");

  // ---- a decision and an election ---------------------------------------
  const decision = await prisma.poll.create({
    data: {
      groupId: group.id,
      meetingId: open.id,
      type: "DECISION",
      title: "Raise the share value to KSh 600?",
      description: "Proposed at the last meeting.",
      status: "OPEN",
      options: {
        create: [
          { label: "Yes", position: 0 },
          { label: "No", position: 1 }
        ]
      }
    },
    include: { options: true }
  });
  for (const [index, member] of members.slice(0, 5).entries()) {
    await prisma.pollVote.create({
      data: {
        pollId: decision.id,
        optionId: decision.options[index < 3 ? 0 : 1]!.id,
        memberId: member.id
      }
    });
  }
  log("votes", "an open motion with 5 ballots cast");

  // ---- Intelli-Store: more to browse, and a live credit request ----------
  const supplier =
    (await prisma.storeSupplier.findFirst()) ??
    (await prisma.storeSupplier.create({
      data: { name: "Demo Supplies Ltd", status: "ACTIVE", county: "Embu" }
    }));

  const catalogue = [
    {
      name: "Dairy Milking Can (50L)",
      slug: "dairy-milking-can-50l",
      category: "AGRI_EQUIPMENT",
      priceCents: 1_200_000,
      depositCents: 120_000,
      inventoryCount: 25,
      description: "Stainless milking can for dairy groups selling to a cooling plant."
    },
    {
      name: "Solar Home Lighting Kit",
      slug: "solar-home-lighting-kit",
      category: "ENERGY",
      priceCents: 2_400_000,
      depositCents: 240_000,
      inventoryCount: 40,
      description: "Three lamps, a phone charger and a panel — bought on credit and repaid weekly."
    },
    {
      name: "Certified Maize Seed (10kg)",
      slug: "certified-maize-seed-10kg",
      category: "AGRI_INPUTS",
      priceCents: 320_000,
      depositCents: 32_000,
      inventoryCount: 120,
      description: "One season's certified seed for a smallholder plot."
    }
  ];

  let created = 0;
  let linked = 0;
  for (const item of catalogue) {
    const exists = await prisma.storeProduct.findFirst({
      where: { slug: item.slug },
      include: { programmeLinks: true }
    });
    if (exists) {
      // An earlier run created this product before the programme link existed.
      // Skipping outright leaves it permanently invisible in the catalogue —
      // "already there" is not the same as "already correct".
      if (exists.programmeLinks.length === 0) {
        await prisma.storeProductProgramme.create({
          data: {
            productId: exists.id,
            programmeId: programme.id,
            creditTerms: "10% deposit, then weekly repayment through the group.",
            depositRateBps: 1000,
            installmentCount: 8,
            installmentFrequency: "WEEKLY",
            flatInterestRateBps: 1000,
            gracePeriodDays: 14
          }
        });
        linked += 1;
      }
      continue;
    }
    await prisma.storeProduct.create({
      data: {
        ...item,
        status: "ACTIVE",
        supplierId: supplier.id,
        currency: "KES",
        sellerName: supplier.name,
        creditSummary: "Deposit, then weekly repayment through the group.",
        fulfilmentSummary: "Delivered to the group's meeting point.",
        // Without a programme link the public catalogue filters the product
        // out entirely — it is listed by programme, not by supplier.
        programmeLinks: {
          create: {
            programmeId: programme.id,
            creditTerms: "10% deposit, then weekly repayment through the group.",
            depositRateBps: 1000,
            installmentCount: 8,
            installmentFrequency: "WEEKLY",
            flatInterestRateBps: 1000,
            gracePeriodDays: 14
          }
        }
      }
    });
    created += 1;
  }
  // An earlier run may have created these before the programme link existed,
  // and the base seed's programme may still be DRAFT. Publish whatever is
  // there so the catalogue a tester opens is not silently empty.
  await prisma.programme.updateMany({
    where: { id: programme.id },
    data: { publicStatus: "ONGOING" }
  });
  const visible = await prisma.storeProduct.count({
    where: {
      status: "ACTIVE",
      programmeLinks: { some: { programme: { publicStatus: "ONGOING" } } }
    }
  });
  log("intelli-store", `${created} added, ${linked} linked, ${visible} visible in the catalogue`);

  // ---- summary -----------------------------------------------------------
  const [loanBal, socialBal] = await Promise.all([
    prisma.fundAccount.findFirst({ where: { groupId: group.id, type: "INTERNAL_LOAN" } }),
    prisma.fundAccount.findFirst({ where: { groupId: group.id, type: "SOCIAL" } })
  ]);

  console.log("\nReady to test:");
  console.log(`  group          ${group.name} (${group.code})`);
  console.log(`  loan fund      KSh ${((loanBal?.balanceCents ?? 0) / 100).toLocaleString()}`);
  console.log(`  welfare fund   KSh ${((socialBal?.balanceCents ?? 0) / 100).toLocaleString()}`);
  console.log(`  open meeting   "${open.title}" — welfare and votes can be recorded here`);
  console.log(`  member PIN     ${DEMO_PIN} (every member)`);
  console.log("\nSign in as:");
  console.log("  group    demo.group@intellicash.co.ke");
  console.log("  member   demo.member@intellicash.co.ke");
  console.log("  agent    demo.agent@intellicash.co.ke  (VA / CBT)");
  console.log(`  password ${demoPassword}`);
  if (passwordGenerated) {
    console.log("\n  ^ generated for this environment and shown ONCE. Store it now —");
    console.log("    it is not recoverable, only replaceable by re-running this.");
  }
  console.log("");
}

main()
  .catch((error) => {
    console.error(error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
