import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { credentialsFor, mpesaBaseUrl, mpesaEnvironment } from "../src/services/payment-service";
import { encryptCredentials } from "../src/services/integration-credentials";

const app = createApp();

async function signIn(phone: string, password = demoPassword) {
  const response = await request(app).post("/api/v1/auth/login").send({ phone, password }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("per-group payment providers", () => {
  let groupId: string;
  let otherGroupId: string;

  beforeAll(async () => {
    await seedDatabase();
    const groups = await prisma.group.findMany({ orderBy: { createdAt: "asc" }, select: { id: true } });
    groupId = groups[0]!.id;
    otherGroupId = groups[1]?.id ?? groups[0]!.id;
    await prisma.groupIntegrationConfig.deleteMany({});
  }, 60000);

  describe("credential resolution", () => {
    it("falls back to the platform when the group has configured nothing", async () => {
      process.env.MPESA_SHORTCODE = "PLATFORM_SHORTCODE";
      const credentials = await credentialsFor("MPESA_DARAJA", groupId);

      expect(credentials.MPESA_SHORTCODE).toBe("PLATFORM_SHORTCODE");
    });

    it("prefers the group's own credentials over the platform's", async () => {
      process.env.MPESA_SHORTCODE = "PLATFORM_SHORTCODE";
      await prisma.groupIntegrationConfig.create({
        data: {
          groupId,
          provider: "MPESA_DARAJA",
          enabled: true,
          credentialsJson: encryptCredentials({ MPESA_SHORTCODE: "GROUP_SHORTCODE" })
        }
      });

      const mine = await credentialsFor("MPESA_DARAJA", groupId);
      expect(mine.MPESA_SHORTCODE).toBe("GROUP_SHORTCODE");

      // One group's till must never leak into another group's collection.
      const theirs = await credentialsFor("MPESA_DARAJA", otherGroupId);
      if (otherGroupId !== groupId) {
        expect(theirs.MPESA_SHORTCODE).toBe("PLATFORM_SHORTCODE");
      }

      // And no group at all still means the platform's.
      const platform = await credentialsFor("MPESA_DARAJA");
      expect(platform.MPESA_SHORTCODE).toBe("PLATFORM_SHORTCODE");
    });

    it("falls back rather than failing when the group's config is disabled", async () => {
      process.env.MPESA_SHORTCODE = "PLATFORM_SHORTCODE";
      await prisma.groupIntegrationConfig.update({
        where: { groupId_provider: { groupId, provider: "MPESA_DARAJA" } },
        data: { enabled: false }
      });

      const credentials = await credentialsFor("MPESA_DARAJA", groupId);
      expect(credentials.MPESA_SHORTCODE).toBe("PLATFORM_SHORTCODE");

      await prisma.groupIntegrationConfig.update({
        where: { groupId_provider: { groupId, provider: "MPESA_DARAJA" } },
        data: { enabled: true }
      });
    });

    it("only overrides the keys the group actually set", async () => {
      process.env.MPESA_CONSUMER_KEY = "PLATFORM_CONSUMER_KEY";
      process.env.MPESA_SHORTCODE = "PLATFORM_SHORTCODE";

      const credentials = await credentialsFor("MPESA_DARAJA", groupId);

      expect(credentials.MPESA_SHORTCODE).toBe("GROUP_SHORTCODE");
      expect(credentials.MPESA_CONSUMER_KEY).toBe("PLATFORM_CONSUMER_KEY");
    });
  });

  describe("the API never returns secrets", () => {
    it("reports a secret as set without disclosing it", async () => {
      await prisma.groupIntegrationConfig.update({
        where: { groupId_provider: { groupId, provider: "MPESA_DARAJA" } },
        data: {
          credentialsJson: encryptCredentials({
            MPESA_SHORTCODE: "GROUP_SHORTCODE",
            MPESA_PASSKEY: "super-secret-passkey"
          })
        }
      });

      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/payment-providers`)
        .set("Cookie", cookies)
        .expect(200);

      const body = JSON.stringify(response.body);
      expect(body).not.toContain("super-secret-passkey");

      const mpesa = response.body.data.providers.find(
        (entry: { provider: string }) => entry.provider === "MPESA_DARAJA"
      );
      expect(mpesa.values.MPESA_PASSKEY).toBe("__set__");
      // A non-secret stays readable so an operator can confirm the till.
      expect(mpesa.values.MPESA_SHORTCODE).toBe("GROUP_SHORTCODE");
      expect(mpesa.configured).toBe(true);
    });
  });

  describe("who may change where money lands", () => {
    it("lets a platform admin configure any group", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      const response = await request(app)
        .put(`/api/v1/groups/${groupId}/payment-providers/PAYSTACK`)
        .set("Cookie", cookies)
        .send({ credentials: { PAYSTACK_SECRET_KEY: "sk_test_admin", PAYSTACK_PUBLIC_KEY: "pk_test_admin" } })
        .expect(200);

      expect(response.body.data.configured).toBe(true);
      expect(JSON.stringify(response.body)).not.toContain("sk_test_admin");
    });

    it("refuses a provider that takes no credentials", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      const response = await request(app)
        .put(`/api/v1/groups/${groupId}/payment-providers/MPESA_CLASSIC`)
        .set("Cookie", cookies)
        .send({ credentials: {} })
        .expect(400);

      expect(response.body.error.code).toBe("PROVIDER_NOT_CONFIGURABLE");
    });

    it("reverting hands the group back to the platform account", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      await request(app)
        .delete(`/api/v1/groups/${groupId}/payment-providers/PAYSTACK`)
        .set("Cookie", cookies)
        .expect(200);

      const row = await prisma.groupIntegrationConfig.findUnique({
        where: { groupId_provider: { groupId, provider: "PAYSTACK" } }
      });
      expect(row).toBeNull();
    });
  });

  /**
   * Which Safaricom host a group's Daraja calls actually reach.
   *
   * Until 2 Aug 2026 every Daraja request was hardcoded to the sandbox host,
   * so a group holding a REAL Daraja account could not transact at all — live
   * credentials presented to sandbox are rejected outright. The whole
   * per-group provider feature was therefore untestable with real money, and
   * nothing in the code said so.
   */
  describe("a group can point Daraja at its own live till", () => {
    async function setEnvironment(value: string) {
      await prisma.groupIntegrationConfig.upsert({
        where: { groupId_provider: { groupId, provider: "MPESA_DARAJA" } },
        create: {
          groupId,
          provider: "MPESA_DARAJA",
          credentialsJson: encryptCredentials({
            MPESA_CONSUMER_KEY: "group-key",
            MPESA_CONSUMER_SECRET: "group-secret",
            MPESA_ENVIRONMENT: value
          }),
          enabled: true,
          mode: "SANDBOX"
        },
        update: {
          credentialsJson: encryptCredentials({
            MPESA_CONSUMER_KEY: "group-key",
            MPESA_CONSUMER_SECRET: "group-secret",
            MPESA_ENVIRONMENT: value
          }),
          enabled: true
        }
      });
      return credentialsFor("MPESA_DARAJA", groupId);
    }

    it("defaults to sandbox when nothing is set", async () => {
      // The safe direction: a wrong guess here fails at authentication rather
      // than pushing a misconfigured group's members onto the live rails.
      await prisma.groupIntegrationConfig.deleteMany({ where: { groupId, provider: "MPESA_DARAJA" } });
      delete process.env.MPESA_ENVIRONMENT;
      const credentials = await credentialsFor("MPESA_DARAJA", groupId);
      expect(mpesaEnvironment(credentials)).toBe("SANDBOX");
      expect(mpesaBaseUrl(credentials)).toBe("https://sandbox.safaricom.co.ke");
    });

    it("sends a LIVE group to the production host", async () => {
      const credentials = await setEnvironment("LIVE");
      expect(mpesaBaseUrl(credentials)).toBe("https://api.safaricom.co.ke");
    });

    it("accepts PRODUCTION, the word Safaricom's own portal uses", async () => {
      const credentials = await setEnvironment("PRODUCTION");
      expect(mpesaEnvironment(credentials)).toBe("LIVE");
    });

    it("is not case- or whitespace-sensitive", async () => {
      const credentials = await setEnvironment("  live  ");
      expect(mpesaEnvironment(credentials)).toBe("LIVE");
    });

    it("treats anything unrecognised as sandbox, never live", async () => {
      // If a bad value ever reaches storage, the failure must be "cannot reach
      // the real till", not "moved real money by accident".
      const credentials = await setEnvironment("banana");
      expect(mpesaEnvironment(credentials)).toBe("SANDBOX");
    });

    it("one group going live does not move another group", async () => {
      await setEnvironment("LIVE");
      const other = await credentialsFor("MPESA_DARAJA", otherGroupId);
      expect(mpesaBaseUrl(other)).toBe("https://sandbox.safaricom.co.ke");
    });
  });

  describe("the API refuses a mistyped environment", () => {
    it("rejects it instead of quietly falling back to sandbox", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      const response = await request(app)
        .put(`/api/v1/groups/${groupId}/payment-providers/MPESA_DARAJA`)
        .set("Cookie", cookies)
        .send({ credentials: { MPESA_ENVIRONMENT: "lve" } })
        .expect(400);

      expect(response.body.error.code).toBe("INVALID_CREDENTIAL_VALUE");
    });

    it("stores a valid value upper-cased", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      await request(app)
        .put(`/api/v1/groups/${groupId}/payment-providers/MPESA_DARAJA`)
        .set("Cookie", cookies)
        .send({ credentials: { MPESA_ENVIRONMENT: "live" } })
        .expect(200);

      const credentials = await credentialsFor("MPESA_DARAJA", groupId);
      expect(credentials.MPESA_ENVIRONMENT).toBe("LIVE");
      expect(mpesaBaseUrl(credentials)).toBe("https://api.safaricom.co.ke");
    });
  });

  /**
   * What an operator is TOLD will happen, versus what will happen.
   *
   * A group can set an environment flag and paste a key that contradicts it.
   * The flag would read "live" while the money went nowhere real, and nobody
   * would find out until a member complained about a missing payment.
   */
  describe("the screen reports the real destination", () => {
    it("reads Paystack's mode from the key itself, not from a setting", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      await request(app)
        .put(`/api/v1/groups/${groupId}/payment-providers/PAYSTACK`)
        .set("Cookie", cookies)
        // Claims PRODUCTION mode while supplying a TEST key — exactly the
        // contradiction the derived value exists to catch.
        .send({ mode: "PRODUCTION", credentials: { PAYSTACK_SECRET_KEY: "sk_test_abc123" } })
        .expect(200);

      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/payment-providers`)
        .set("Cookie", cookies)
        .expect(200);

      const paystack = response.body.data.providers.find((p: any) => p.provider === "PAYSTACK");
      expect(paystack.effective.environment).toBe("SANDBOX");
      expect(paystack.effective.note).toMatch(/no real money/i);
    });

    it("names the exact host a Daraja group will reach", async () => {
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);

      const response = await request(app)
        .get(`/api/v1/groups/${groupId}/payment-providers`)
        .set("Cookie", cookies)
        .expect(200);

      const mpesa = response.body.data.providers.find((p: any) => p.provider === "MPESA_DARAJA");
      expect(mpesa.effective.host).toBe("https://api.safaricom.co.ke");
      expect(mpesa.effective.note).toMatch(/real money/i);
    });

    it("does not report the optional environment key as missing", async () => {
      // It has a safe default, so listing it would tell a working group its
      // gateway was incomplete.
      const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
      const cookies = await signIn(admin.phone);
      await prisma.groupIntegrationConfig.deleteMany({
        where: { groupId: otherGroupId, provider: "MPESA_DARAJA" }
      });

      const response = await request(app)
        .get(`/api/v1/groups/${otherGroupId}/payment-providers`)
        .set("Cookie", cookies)
        .expect(200);

      const mpesa = response.body.data.providers.find((p: any) => p.provider === "MPESA_DARAJA");
      expect(mpesa.missingKeys).not.toContain("MPESA_ENVIRONMENT");
    });
  });
});
