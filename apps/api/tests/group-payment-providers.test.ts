import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { credentialsFor } from "../src/services/payment-service";
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
});
