import { existsSync } from "node:fs";
import { createHash } from "node:crypto";
import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";
import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";
import { MAX_ATTACHMENTS_PER_VISIT } from "../src/routes/attachments";
import { resolveAttachmentPath } from "../src/services/attachment-storage";

const app = createApp();

/**
 * Field evidence.
 *
 * The properties worth the most here are that a retry cannot duplicate a
 * photograph, that a photo is never anonymous, and that an agent cannot delete
 * the evidence that contradicts their own report.
 */

/** The smallest thing multer will accept as a real JPEG upload. */
function jpegBytes(marker: number) {
  // A JPEG SOI/EOI wrapper with a distinguishing byte, so two calls with
  // different markers hash differently and one with the same marker does not.
  return Buffer.from([0xff, 0xd8, 0xff, 0xe0, marker, 0x00, 0xff, 0xd9]);
}

function sha256Of(buffer: Buffer) {
  return createHash("sha256").update(buffer).digest("hex");
}

async function signIn(identifier: string, password = demoPassword) {
  const response = await request(app)
    .post("/api/v1/auth/login")
    .send({ phone: identifier, password })
    .expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("visit attachments", () => {
  let adminCookies: string[];
  let agentCookies: string[];
  let visitId: string;
  let groupId: string;

  beforeAll(async () => {
    await seedDatabase();
    await prisma.attachment.deleteMany({});
    await prisma.groupVisit.deleteMany({});

    const admin = demoAccounts.find((account) => account.role === "IWL_ADMIN")!;
    const agent = demoAccounts.find((account) => account.role === "VILLAGE_AGENT")!;
    adminCookies = await signIn(admin.phone);
    agentCookies = await signIn(agent.phone);

    const agentUser = await prisma.user.findFirst({
      where: { role: "VILLAGE_AGENT" },
      select: { id: true, villageAgentId: true }
    });
    const group = await prisma.group.findFirst({
      where: { villageAgentId: agentUser!.villageAgentId! },
      select: { id: true }
    });
    groupId = group!.id;

    const visit = await prisma.groupVisit.create({
      data: {
        groupId,
        clientRequestId: "visit-attachment-fixture",
        visitType: "FOLLOW_UP",
        startedAt: new Date(),
        villageAgentId: agentUser!.villageAgentId!,
        submittedByUserId: agentUser!.id
      }
    });
    visitId = visit.id;
  }, 180000);

  /** Step one: push the bytes. */
  async function upload(cookies: string[], marker = 0x01) {
    const bytes = jpegBytes(marker);
    const response = await request(app)
      .post("/api/v1/uploads/visit-photo")
      .set("Cookie", cookies)
      .attach("file", bytes, { filename: "evidence.jpg", contentType: "image/jpeg" })
      .expect(201);
    return { ...response.body.data, bytes } as {
      storagePath: string;
      fileName: string;
      mimeType: string;
      size: number;
      sha256: string;
      bytes: Buffer;
    };
  }

  /** Step two: say what it is evidence of. */
  function bind(
    cookies: string[],
    uploaded: { storagePath: string; fileName: string; mimeType: string; size: number; sha256: string },
    overrides: Record<string, unknown> = {}
  ) {
    return request(app)
      .post(`/api/v1/visits/${visitId}/attachments`)
      .set("Cookie", cookies)
      .send({
        storagePath: uploaded.storagePath,
        fileName: uploaded.fileName,
        mimeType: uploaded.mimeType,
        size: uploaded.size,
        sha256: uploaded.sha256,
        sectionKey: "governance",
        questionKey: "constitution_written",
        capturedAt: new Date().toISOString(),
        clientRequestId: `att-${Math.random().toString(36).slice(2)}`,
        ...overrides
      });
  }

  describe("the two-step upload", () => {
    it("writes the file and hashes it without creating a row", async () => {
      const before = await prisma.attachment.count();
      const uploaded = await upload(agentCookies);

      expect(uploaded.sha256).toBe(sha256Of(uploaded.bytes));
      expect(uploaded.storagePath).toMatch(/^visit-photo\/\d{4}\/\d{2}\//);
      expect(existsSync(resolveAttachmentPath(uploaded.storagePath))).toBe(true);

      // Step one alone must not record anything: a phone that dies here leaves
      // an orphan file, which is invisible, rather than a row pointing at a
      // half-written image, which is not.
      expect(await prisma.attachment.count()).toBe(before);
    });

    it("binds the file to the visit, section and question", async () => {
      const uploaded = await upload(agentCookies, 0x02);
      const response = await bind(agentCookies, uploaded).expect(201);

      expect(response.body.data.visitId).toBe(visitId);
      expect(response.body.data.sectionKey).toBe("governance");
      expect(response.body.data.questionKey).toBe("constitution_written");
      // The url addresses the attachment, not the file. Where the bytes sit is
      // the server's business — see "who can fetch the bytes" below.
      expect(response.body.data.url).toContain(`/attachments/${response.body.data.id}/file`);
      expect(uploaded.storagePath).toMatch(/^visit-photo\/\d{4}\/\d{2}\//);
    });

    it("refuses a photo that does not say what it is evidence of", async () => {
      // "Never an anonymous gallery" — a picture with no claim attached to it
      // is not evidence of anything.
      const uploaded = await upload(agentCookies, 0x03);
      await bind(agentCookies, uploaded, { sectionKey: undefined }).expect(400);
    });

    it("refuses a file that is not an image", async () => {
      await request(app)
        .post("/api/v1/uploads/visit-photo")
        .set("Cookie", agentCookies)
        .attach("file", Buffer.from("%PDF-1.4 not a photo"), {
          filename: "report.pdf",
          contentType: "application/pdf"
        })
        .expect(400);
    });
  });

  describe("a retry cannot duplicate a photograph", () => {
    it("returns the existing attachment for a repeated clientRequestId", async () => {
      const uploaded = await upload(agentCookies, 0x10);
      const clientRequestId = "att-retry-same-id";

      const first = await bind(agentCookies, uploaded, { clientRequestId }).expect(201);
      // 200, not 409: a client that treats an error as failure retries forever.
      const second = await bind(agentCookies, uploaded, { clientRequestId }).expect(200);

      expect(second.body.data.id).toBe(first.body.data.id);
      expect(
        await prisma.attachment.count({ where: { visitId, sha256: uploaded.sha256 } })
      ).toBe(1);
    });

    it("recognises the same image sent again under a new request id", async () => {
      // A retry that lost its original id — a reinstall, or a client bug. The
      // content hash still identifies it as the same photograph.
      const uploaded = await upload(agentCookies, 0x11);
      const first = await bind(agentCookies, uploaded, {
        clientRequestId: "att-hash-a"
      }).expect(201);

      const reuploaded = await upload(agentCookies, 0x11);
      expect(reuploaded.sha256).toBe(uploaded.sha256);
      expect(reuploaded.storagePath).not.toBe(uploaded.storagePath);

      const second = await bind(agentCookies, reuploaded, {
        clientRequestId: "att-hash-b"
      }).expect(200);

      expect(second.body.data.id).toBe(first.body.data.id);
      expect(
        await prisma.attachment.count({ where: { visitId, sha256: uploaded.sha256 } })
      ).toBe(1);
      // And the now-redundant second file is cleaned up rather than left to rot.
      expect(existsSync(resolveAttachmentPath(reuploaded.storagePath))).toBe(false);
    });

    it("keeps genuinely different photographs apart", async () => {
      const a = await upload(agentCookies, 0x20);
      const b = await upload(agentCookies, 0x21);
      expect(a.sha256).not.toBe(b.sha256);

      const first = await bind(agentCookies, a, { clientRequestId: "att-diff-a" }).expect(201);
      const second = await bind(agentCookies, b, { clientRequestId: "att-diff-b" }).expect(201);

      expect(second.body.data.id).not.toBe(first.body.data.id);
    });
  });

  describe("access", () => {
    it("refuses to attach to a visit outside the agent's caseload", async () => {
      const detached = await prisma.group.findFirst({
        where: { villageAgentId: null },
        select: { id: true }
      });
      const otherGroupId =
        detached?.id ??
        (
          await prisma.group.update({
            where: {
              id: (
                await prisma.group.findFirstOrThrow({
                  where: { id: { not: groupId } },
                  select: { id: true }
                })
              ).id
            },
            data: { villageAgentId: null },
            select: { id: true }
          })
        ).id;

      const foreignVisit = await prisma.groupVisit.create({
        data: {
          groupId: otherGroupId,
          clientRequestId: "visit-foreign-attachment",
          visitType: "FOLLOW_UP",
          startedAt: new Date()
        }
      });

      const uploaded = await upload(agentCookies, 0x30);
      // 404, not 403 — "forbidden" would confirm the visit exists.
      await request(app)
        .post(`/api/v1/visits/${foreignVisit.id}/attachments`)
        .set("Cookie", agentCookies)
        .send({
          storagePath: uploaded.storagePath,
          fileName: uploaded.fileName,
          mimeType: uploaded.mimeType,
          size: uploaded.size,
          sha256: uploaded.sha256,
          sectionKey: "governance",
          capturedAt: new Date().toISOString(),
          clientRequestId: "att-foreign"
        })
        .expect(404);
    });

    it("refuses to let the agent delete their own evidence", async () => {
      // An agent who could delete photographs could remove the ones that
      // contradict their report — which is the whole reason for collecting it.
      const uploaded = await upload(agentCookies, 0x40);
      const created = await bind(agentCookies, uploaded, {
        clientRequestId: "att-delete-guard"
      }).expect(201);

      await request(app)
        .delete(`/api/v1/attachments/${created.body.data.id}`)
        .set("Cookie", agentCookies)
        .expect(403);

      expect(await prisma.attachment.findUnique({ where: { id: created.body.data.id } })).not.toBeNull();
    });

    it("lets an admin remove one, and takes the file with it", async () => {
      const uploaded = await upload(agentCookies, 0x41);
      const created = await bind(agentCookies, uploaded, {
        clientRequestId: "att-admin-delete"
      }).expect(201);

      await request(app)
        .delete(`/api/v1/attachments/${created.body.data.id}`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(await prisma.attachment.findUnique({ where: { id: created.body.data.id } })).toBeNull();
      expect(existsSync(resolveAttachmentPath(uploaded.storagePath))).toBe(false);
    });
  });

  describe("limits", () => {
    it("caps the number of photos on one visit", async () => {
      const capVisit = await prisma.groupVisit.create({
        data: {
          groupId,
          clientRequestId: "visit-attachment-cap",
          visitType: "FOLLOW_UP",
          startedAt: new Date()
        }
      });

      // Fill to the cap directly — going through the endpoint twenty times
      // would test multer, not the limit.
      await prisma.attachment.createMany({
        data: Array.from({ length: MAX_ATTACHMENTS_PER_VISIT }, (_, index) => ({
          kind: "VISIT_PHOTO",
          groupId,
          visitId: capVisit.id,
          sectionKey: "governance",
          storagePath: `visit-photo/2026/08/filler-${index}.jpg`,
          fileName: `filler-${index}.jpg`,
          mimeType: "image/jpeg",
          sizeBytes: 1024,
          sha256: createHash("sha256").update(`filler-${index}`).digest("hex"),
          clientRequestId: `att-filler-${index}`
        }))
      });

      const uploaded = await upload(agentCookies, 0x50);
      const response = await request(app)
        .post(`/api/v1/visits/${capVisit.id}/attachments`)
        .set("Cookie", agentCookies)
        .send({
          storagePath: uploaded.storagePath,
          fileName: uploaded.fileName,
          mimeType: uploaded.mimeType,
          size: uploaded.size,
          sha256: uploaded.sha256,
          sectionKey: "governance",
          capturedAt: new Date().toISOString(),
          clientRequestId: "att-over-cap"
        })
        .expect(400);

      expect(response.body.error.code).toBe("ATTACHMENT_LIMIT_REACHED");
    });
  });

  describe("listing", () => {
    it("returns a visit's evidence grouped by section", async () => {
      const response = await request(app)
        .get(`/api/v1/visits/${visitId}/attachments`)
        .set("Cookie", adminCookies)
        .expect(200);

      expect(Array.isArray(response.body.data)).toBe(true);
      expect(response.body.data.length).toBeGreaterThan(0);
      for (const attachment of response.body.data) {
        // Every stored photo says what it is evidence of.
        expect(attachment.sectionKey).toBeTruthy();
        // Same-origin and relative: see attachmentUrl for why.
        expect(attachment.url).toMatch(/^\/api\/v1\/attachments\/[^/]+\/file$/);
      }
    });
  });

  /**
   * Serving the bytes.
   *
   * The whole upload root used to be mounted as static files, so a photograph
   * of a group's premises or its books was fetchable by anyone holding the URL
   * — no session, no scope check. The metadata was scoped and the image was
   * not. These are the tests that keep it that way round.
   */
  describe("who can fetch the bytes", () => {
    // Its own visit: the cap test above fills the shared one to the limit, and
    // its own byte markers, because binding the same content hash twice is a
    // deliberate no-op that returns 200 rather than 201.
    let servingVisitId: string;
    let marker = 0x40;

    beforeAll(async () => {
      const agentUser = await prisma.user.findFirst({
        where: { role: "VILLAGE_AGENT" },
        select: { id: true, villageAgentId: true }
      });
      const visit = await prisma.groupVisit.create({
        data: {
          groupId,
          clientRequestId: "visit-attachment-serving-fixture",
          visitType: "FOLLOW_UP",
          startedAt: new Date(),
          villageAgentId: agentUser!.villageAgentId!,
          submittedByUserId: agentUser!.id
        }
      });
      servingVisitId = visit.id;
    }, 60000);

    async function anAttachment() {
      const uploaded = await upload(agentCookies, (marker += 1));
      const created = await request(app)
        .post(`/api/v1/visits/${servingVisitId}/attachments`)
        .set("Cookie", agentCookies)
        .send({
          storagePath: uploaded.storagePath,
          fileName: uploaded.fileName,
          mimeType: uploaded.mimeType,
          size: uploaded.size,
          sha256: uploaded.sha256,
          sectionKey: "governance",
          questionKey: "constitution_written",
          capturedAt: new Date().toISOString(),
          clientRequestId: `att-serving-${marker}`
        })
        .expect(201);

      return { id: created.body.data.id as string, url: created.body.data.url as string, uploaded };
    }

    it("hands out an API url, never a path under /uploads", async () => {
      const { id, url } = await anAttachment();

      expect(url).toContain(`/api/v1/attachments/${id}/file`);
      expect(url).not.toContain("/uploads/");
    });

    it("no longer serves visit photographs as public static files", async () => {
      const uploaded = await upload(agentCookies, (marker += 1));

      // The path that used to work, verbatim.
      await request(app).get(`/uploads/${uploaded.storagePath}`).expect(404);
    });

    it("refuses a caller with no session", async () => {
      const { id } = await anAttachment();

      await request(app).get(`/api/v1/attachments/${id}/file`).expect(401);
    });

    it("returns the exact bytes to an agent whose caseload covers the group", async () => {
      const { id, uploaded } = await anAttachment();

      const response = await request(app)
        .get(`/api/v1/attachments/${id}/file`)
        .set("Cookie", agentCookies)
        .buffer(true)
        .parse((res, callback) => {
          const chunks: Buffer[] = [];
          res.on("data", (chunk: Buffer) => chunks.push(chunk));
          res.on("end", () => callback(null, Buffer.concat(chunks)));
        })
        .expect(200);

      expect(response.headers["content-type"]).toContain("image/jpeg");
      // A shared cache must not keep a copy of somebody's evidence.
      expect(response.headers["cache-control"]).toContain("private");
      expect(sha256Of(response.body as Buffer)).toBe(uploaded.sha256);
    });

    it("is a 404, not a 403, for a group that is not yours", async () => {
      const { id } = await anAttachment();

      const otherGroup = await prisma.group.findFirst({
        where: { id: { not: groupId } },
        select: { id: true }
      });
      const outsider = otherGroup
        ? await prisma.user.findFirst({
            where: { role: "GROUP_ACCOUNT", groupId: otherGroup.id },
            select: { phone: true }
          })
        : null;

      // The seed does not guarantee a second group with its own account; when
      // there is none there is nothing to prove here, and inventing one would
      // test the fixture rather than the scope rule.
      if (!outsider?.phone) return;

      const cookies = await signIn(outsider.phone);
      const response = await request(app)
        .get(`/api/v1/attachments/${id}/file`)
        .set("Cookie", cookies);

      // 404 rather than 403: confirming an id exists is itself a disclosure.
      expect(response.status).toBe(404);
    });
  });
});