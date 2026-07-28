import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { normalisePhone, phoneTail, samePhone } from "../src/lib/phone";

const app = createApp();

const TUJIJENGE = "IWL-KBU-0001";
const PASSWORD = "OneLine#2026";

/** Every way a Kenyan writes one line. */
const AS_LOCAL = "0788777666";
const AS_INTERNATIONAL = "+254788777666";
const AS_BARE = "254788777666";

async function wipeAccountsFor(tail: string) {
  const users = await prisma.user.findMany({ where: { phone: { contains: tail } } });
  for (const user of users) {
    await prisma.notification.deleteMany({ where: { userId: user.id } });
    await prisma.userMembership.deleteMany({ where: { userId: user.id } });
    await prisma.groupJoinRequest.deleteMany({ where: { userId: user.id } });
    await prisma.user.delete({ where: { id: user.id } });
  }
}

describe("one phone number is one person", () => {
  beforeAll(async () => {
    await seedDatabase();
    await wipeAccountsFor("788777666");

    await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "MEMBER", name: "Winnie Kiptoo", phone: AS_LOCAL, password: PASSWORD })
      .expect(201);
  }, 60000);

  it("refuses a second account for the same line written differently", async () => {
    for (const written of [AS_INTERNATIONAL, AS_BARE, AS_LOCAL]) {
      const res = await request(app)
        .post("/api/v1/auth/register")
        .send({ accountType: "MEMBER", name: "Winnie Kiptoo", phone: written, password: PASSWORD });
      expect(res.status).toBe(409);
      expect(res.body.error.code).toBe("ACCOUNT_EXISTS");
    }
    const accounts = await prisma.user.findMany({ where: { phone: { contains: "788777666" } } });
    expect(accounts).toHaveLength(1);
  });

  it("lets them sign in however they type their number", async () => {
    for (const written of [AS_LOCAL, AS_INTERNATIONAL, AS_BARE]) {
      const res = await request(app)
        .post("/api/v1/auth/login")
        .send({ phone: written, password: PASSWORD });
      expect(res.status).toBe(200);
      expect(res.body.data.name).toBe("Winnie Kiptoo");
    }
  });

  it("still refuses a wrong password", async () => {
    // The lookup got looser; the credential check must not have.
    const res = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: AS_INTERNATIONAL, password: "not-the-password" });
    expect(res.status).toBe(401);
  });

  it("does not confuse two different people whose numbers look alike", async () => {
    await wipeAccountsFor("788777000");
    await request(app)
      .post("/api/v1/auth/register")
      .send({ accountType: "MEMBER", name: "Someone Else", phone: "0788777000", password: PASSWORD })
      .expect(201);

    const res = await request(app)
      .post("/api/v1/auth/login")
      .send({ phone: "0788777000", password: PASSWORD })
      .expect(200);
    expect(res.body.data.name).toBe("Someone Else");
  });
});

describe("a roster entry belongs to one account", () => {
  let groupId: string;

  beforeAll(async () => {
    const group = await prisma.group.findFirstOrThrow({ where: { code: TUJIJENGE } });
    groupId = group.id;
  }, 60000);

  it("refuses to hand a member already signed in on one account to another", async () => {
    // Two accounts for one roster entry can only arise from bad data now, but
    // if it ever does, moving the link would detach the first account from
    // that person's savings without telling anyone.
    const member = await prisma.member.findFirstOrThrow({ where: { groupId } });

    const first = await prisma.user.create({
      data: {
        name: "Holder One",
        email: "holder-one@example.com",
        phone: "254799000111",
        passwordHash: "x",
        role: "MEMBER"
      }
    });
    const second = await prisma.user.create({
      data: {
        name: "Holder Two",
        email: "holder-two@example.com",
        phone: "254799000222",
        passwordHash: "x",
        role: "MEMBER"
      }
    });

    await prisma.userMembership.deleteMany({ where: { memberId: member.id } });
    await prisma.userMembership.create({
      data: { userId: first.id, memberId: member.id, groupId }
    });

    const { linkMembership, MemberAlreadyLinkedError } = await import(
      "../src/services/membership-service"
    );
    await expect(linkMembership(second.id, member.id, groupId)).rejects.toBeInstanceOf(
      MemberAlreadyLinkedError
    );

    // The first account still holds it — nothing was quietly moved.
    const link = await prisma.userMembership.findUnique({ where: { memberId: member.id } });
    expect(link?.userId).toBe(first.id);

    await prisma.userMembership.deleteMany({ where: { memberId: member.id } });
    await prisma.user.deleteMany({ where: { id: { in: [first.id, second.id] } } });
  });
});

describe("normalisePhone", () => {
  it("reduces every written form to one canonical number", () => {
    // Each of these is the same line. `+254 (0)7…` is how numbers get printed
    // on letterheads and business cards; `00254…` is the dialled form.
    for (const written of [
      "0712345678",
      "+254 712 345 678",
      "254712345678",
      "712345678",
      "2540712345678",
      "+254 (0)712 345 678",
      "00254712345678",
      "+254-0712-345-678"
    ]) {
      expect(normalisePhone(written), written).toBe("254712345678");
    }
  });

  it("does not invent a country code for a foreign number", () => {
    // Mangling these would be worse than leaving them: two different foreign
    // lines must never collapse into one.
    expect(normalisePhone("+256712345678")).toBe("256712345678");
    expect(samePhone("+256712345678", "+255712345678")).toBe(false);
  });

  it("leaves a too-short number alone rather than padding it", () => {
    expect(normalisePhone("0712345")).toBe("0712345");
    expect(samePhone("0712345678", "0712345")).toBe(false);
  });

  it("finds the same nine significant digits whatever the prefix", () => {
    // phoneTail is the database pre-filter; if it missed these forms the
    // canonical comparison would never get a chance to run.
    for (const written of ["0712345678", "2540712345678", "00254712345678"]) {
      expect(phoneTail(written), written).toBe("712345678");
    }
  });

  it("treats an absent number as no number, never a match", () => {
    expect(normalisePhone(null)).toBe("");
    expect(samePhone(null, null)).toBe(false);
    expect(samePhone("", "")).toBe(false);
  });

  it("does not collapse two genuinely different numbers", () => {
    expect(samePhone("0712345678", "0712345679")).toBe(false);
  });
});
