/**
 * Append-only audit trail.
 *
 * Every state-changing operation writes one `AuditEvent` here: who did what,
 * to which entity, with a hash of the payload so the record can be verified
 * later. Nothing in this file ever updates or deletes — the audit trail is the
 * one table where immutability is the point, not a limitation.
 */

import type { AuditEventType } from "@intellicash/shared";
import { hashPayload } from "../lib/crypto";
import { prisma } from "../lib/prisma";

export async function appendAuditEvent(input: {
  actorUserId?: string | null;
  entityType: string;
  entityId: string;
  type: AuditEventType;
  payload: unknown;
}) {
  return prisma.auditEvent.create({
    data: {
      actorUserId: input.actorUserId ?? null,
      entityType: input.entityType,
      entityId: input.entityId,
      type: input.type,
      payloadJson: JSON.stringify(input.payload),
      hash: hashPayload(input.payload)
    }
  });
}
