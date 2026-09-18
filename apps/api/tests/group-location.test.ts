import request from "supertest";
import { beforeAll, describe, expect, it } from "vitest";
import { demoAccounts, demoPassword } from "@intellicash/shared";

import { createApp } from "../src/app";
import { prisma } from "../src/lib/prisma";
import { seedDatabase } from "../prisma/seed";

const app = createApp();

/**
 * Setting where a group meets.
 *
 * The console sends `null` for every box left empty. The rules used to accept
 * only text or a number, so saving any group with one blank field — most
 * imported groups have no GPS — failed, and a location could not be set at all.
 */

async function signIn(identifier: string) {
  const response = await request(app).post("/api/v1/auth/login").send({ phone: identifier, password: demoPassword }).expect(200);
  const cookie = response.headers["set-cookie"];
  return Array.isArray(cookie) ? cookie : [cookie as unknown as string];
}

describe("saving a group's location", () => {
  let admin: string[];
  let groupId: string;
  let programmeIds: string[];

  beforeAll(async () => {
    await seedDatabase();
    admin = await signIn(demoAccounts.find((entry) => entry.role === "IWL_ADMIN")!.phone);
    const group = await prisma.group.findFirstOrThrow({
      select: { id: true, programmeLinks: { select: { programmeId: true } } }
    });
    groupId = group.id;
    programmeIds = group.programmeLinks.map((link) => link.programmeId);
  }, 120000);

  it("saves the form exactly as the console sends it, blanks included", async () => {
    const response = await request(app)
      .patch(`/api/v1/groups/${groupId}`)
      .set("Cookie", admin)
      .send({
        location: "Kagaari market, behind the chief's office",
        subCounty: null,
        objective: null,
        contactPhone: null,
        meetingDay: null,
        gpsLatitude: -0.538912,
        gpsLongitude: 37.459601,
        programmeIds
      })
      .expect(200);

    expect(response.body.data.location).toBe("Kagaari market, behind the chief's office");
    expect(response.body.data.gpsLatitude).toBeCloseTo(-0.538912, 6);
    expect(response.body.data.gpsLongitude).toBeCloseTo(37.459601, 6);
  });

  it("clears GPS when the boxes are emptied", async () => {
    const response = await request(app)
      .patch(`/api/v1/groups/${groupId}`)
      .set("Cookie", admin)
      .send({ gpsLatitude: null, gpsLongitude: null })
      .expect(200);
    expect(response.body.data.gpsLatitude).toBeNull();
    expect(response.body.data.gpsLongitude).toBeNull();
  });

  it("refuses a coordinate that cannot be on Earth, and says which", async () => {
    const response = await request(app)
      .patch(`/api/v1/groups/${groupId}`)
      .set("Cookie", admin)
      .send({ gpsLatitude: 537.4 })
      .expect(400);
    expect(response.body.error.message).toMatch(/GPS latitude must be at most 90/);
  });
});
