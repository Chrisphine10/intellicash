import { prisma } from "../src/lib/prisma";
import { findSmsIntegration } from "../src/services/sms-provider";

/**
 * Read-only: can groups get into their accounts, and does SMS actually leave.
 *
 * Writes nothing. Prints no full phone numbers and no secrets — only whether
 * credentials are present, and numbers masked to their last three digits.
 */

const mask = (phone: string | null | undefined) =>
  phone ? `${"*".repeat(Math.max(0, phone.length - 3))}${phone.slice(-3)}` : "(none)";

async function main() {
  console.log("\n== SMS ==");
  console.log("ENABLE_SMS_NETWORK_CALLS :", JSON.stringify(process.env.ENABLE_SMS_NETWORK_CALLS ?? null));
  const integration = await findSmsIntegration();
  console.log("provider resolved        :", integration ? integration.provider : "NONE — nothing can be sent");

  const since = new Date(Date.now() - 21 * 24 * 60 * 60 * 1000);
  const recipients = await prisma.smsBroadcastRecipient.findMany({
    where: { createdAt: { gte: since } },
    select: {
      status: true,
      providerStatus: true,
      providerMessage: true,
      phone: true,
      createdAt: true,
      broadcast: { select: { kind: true } }
    },
    orderBy: { createdAt: "desc" }
  });
  const byStatus = new Map<string, number>();
  for (const row of recipients) {
    const key = `${row.broadcast.kind} / ${row.status}`;
    byStatus.set(key, (byStatus.get(key) ?? 0) + 1);
  }
  console.log("last 21 days by kind/status:");
  for (const [key, count] of [...byStatus.entries()].sort()) console.log(`  ${count.toString().padStart(4)}  ${key}`);
  console.log("most recent 10:");
  for (const row of recipients.slice(0, 10)) {
    console.log(
      `  ${row.createdAt.toISOString().slice(0, 16)} ${row.broadcast.kind.padEnd(20)} ${row.status.padEnd(8)} ${mask(row.phone)} ${row.providerStatus ?? ""} ${(row.providerMessage ?? "").slice(0, 60)}`
    );
  }

  // Every OTP request leaves one of these, whatever happened — so a code that
  // "never arrived" shows here as SENT, NO_ACCOUNT, NO_PHONE, TOO_SOON or
  // SMS_FAILED, which is the whole diagnosis.
  console.log("\n== Sign-in / reset code requests (last 7 days) ==");
  const otpEvents = await prisma.auditEvent.findMany({
    where: { type: "AUTH_OTP_REQUESTED", createdAt: { gte: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000) } },
    select: { createdAt: true, payloadJson: true },
    orderBy: { createdAt: "desc" },
    take: 30
  });
  const outcomes = new Map<string, number>();
  for (const event of otpEvents) {
    const payload = JSON.parse(event.payloadJson) as { outcome?: string; purpose?: string; phone?: string | null };
    const outcome = payload.outcome ?? "?";
    outcomes.set(outcome, (outcomes.get(outcome) ?? 0) + 1);
    console.log(
      `  ${event.createdAt.toISOString().slice(0, 19)} ${(payload.purpose ?? "SIGN_IN").padEnd(15)} ${outcome.padEnd(12)} ${payload.phone ?? "(no account matched)"}`
    );
  }
  console.log("  by outcome:", Object.fromEntries(outcomes));

  const otpSends = await prisma.smsBroadcastRecipient.findMany({
    where: { broadcast: { kind: "LOGIN_OTP" } },
    select: { createdAt: true, status: true, providerStatus: true, providerMessage: true, phone: true },
    orderBy: { createdAt: "desc" },
    take: 15
  });
  console.log(`  LOGIN_OTP messages handed to the provider: ${otpSends.length} (latest 15)`);
  for (const row of otpSends) {
    console.log(
      `  ${row.createdAt.toISOString().slice(0, 19)} ${row.status.padEnd(8)} ${mask(row.phone)} ${row.providerStatus ?? ""} ${(row.providerMessage ?? "").slice(0, 80)}`
    );
  }

  console.log("\n== Onboarded group accounts ==");
  const groups = await prisma.group.findMany({
    where: { sourceSystem: "FLOURISH_ONBOARDING_2026" },
    select: {
      code: true,
      name: true,
      userAccounts: { where: { role: "GROUP_ACCOUNT" }, select: { phone: true } }
    },
    orderBy: { code: "asc" }
  });
  const withPhone = groups.filter((g) => g.userAccounts.some((u) => u.phone));
  console.log("groups                   :", groups.length);
  console.log("group login with a phone :", withPhone.length);
  console.log("group login, no phone    :", groups.length - withPhone.length);

  const logins = await prisma.auditEvent.findMany({
    where: { type: "AUTH_LOGIN", createdAt: { gte: new Date("2026-08-27") } },
    select: { actorUserId: true }
  });
  const loggedIn = new Set(logins.map((l) => l.actorUserId));
  const groupUsers = await prisma.user.findMany({
    where: { role: "GROUP_ACCOUNT", group: { sourceSystem: "FLOURISH_ONBOARDING_2026" } },
    select: { id: true }
  });
  console.log("have ever signed in      :", groupUsers.filter((u) => loggedIn.has(u.id)).length);

  console.log("\n== Accounts created by self-signup since the import ==");
  const signups = await prisma.user.findMany({
    where: { createdAt: { gte: new Date("2026-08-27") }, role: { in: ["GROUP_ACCOUNT", "MEMBER", "VILLAGE_AGENT"] } },
    select: { name: true, role: true, phone: true, groupId: true, memberId: true, createdAt: true },
    orderBy: { createdAt: "desc" }
  });
  const imported = new Set(
    (
      await prisma.user.findMany({
        where: { group: { sourceSystem: "FLOURISH_ONBOARDING_2026" } },
        select: { phone: true }
      })
    ).map((u) => u.phone)
  );
  for (const row of signups) {
    const orphan = row.role === "GROUP_ACCOUNT" && !row.groupId;
    console.log(
      `  ${row.createdAt.toISOString().slice(0, 10)} ${row.role.padEnd(14)} ${row.name.slice(0, 34).padEnd(34)} ${mask(row.phone)}${orphan ? "  <-- group login with NO group" : ""}${imported.has(row.phone) ? "  <-- same phone as an imported group" : ""}`
    );
  }
  console.log("total                    :", signups.length);

  await prisma.$disconnect();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
