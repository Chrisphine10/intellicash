import { prisma } from "../lib/prisma";
import { smsSegments } from "../domain/member-sms-messages";
import { findSmsIntegration } from "./sms-provider";
import { isSendableSmsPhone, sendSms } from "./sms-service";

/**
 * Everything the platform texts on its own initiative.
 *
 * Share-purchase confirmations, end-of-meeting summaries, and every system
 * notification that also appears in the console bell. One dispatcher, so a
 * message the system sends can never disagree with a broadcast an admin types
 * about which account is live or how a failure is recorded.
 *
 * Three rules hold everywhere in this file.
 *
 * 1. **It never throws.** Every caller has already committed the thing that
 *    matters - a ledger entry, a sealed meeting, an approved join request. An
 *    SMS provider timing out must not turn a recorded share purchase into a 500
 *    the treasurer retries, because the retry would double the entry.
 * 2. **It is never awaited by the request.** Bonga takes one recipient per
 *    request; thirty members is thirty round trips. `dispatchAfterResponse`
 *    starts the work once the response is already out.
 * 3. **Every send is recorded**, in the same `SmsBroadcast` table the console
 *    already shows, so "did they get it" is answerable afterwards rather than a
 *    matter of belief.
 */

/**
 * What raised a send. Recorded on the broadcast so an operator can tell an
 * automatic message from one an admin typed, and so cost can be attributed.
 */
export type OutboundSmsKind = "SHARE_PURCHASE" | "MEETING_SUMMARY" | "SYSTEM_NOTIFICATION";

export interface OutboundSmsRecipient {
  /** Null for a recipient who is a platform user rather than a group member. */
  memberId?: string | null;
  /** Whose name appears in the console against this row. */
  memberName: string;
  phone: string;
  /** This recipient's own body. Per-recipient by design; see the domain module. */
  message: string;
}

export interface DispatchSmsInput {
  kind: OutboundSmsKind;
  /** Null for a send that is not about one group, such as a store notification. */
  groupId?: string | null;
  meetingId?: string | null;
  requestedByUserId?: string | null;
  /** Shown in the console for a send whose bodies differ per recipient. */
  label: string;
  recipients: OutboundSmsRecipient[];
}

interface Dependencies {
  fetch?: typeof fetch;
  networkEnabled?: boolean;
}

export interface DispatchSmsResult {
  broadcastId: string | null;
  attempted: number;
  sent: number;
  failed: number;
  queued: number;
  skipped: number;
  /** Total 160-character segments billed, so the cost is a number not a guess. */
  segments: number;
  reason?: string;
}

const NOTHING: DispatchSmsResult = {
  broadcastId: null,
  attempted: 0,
  sent: 0,
  failed: 0,
  queued: 0,
  skipped: 0,
  segments: 0
};

function broadcastStatus(result: { attempted: number; sent: number; failed: number }) {
  if (result.attempted === 0) return "FAILED";
  if (result.sent === result.attempted) return "SENT";
  if (result.failed === result.attempted) return "FAILED";
  if (result.sent > 0 || result.failed > 0) return "PARTIAL";
  return "QUEUED";
}

function unusablePhoneReason(phone: string) {
  return phone.trim()
    ? "That phone number is not a Kenyan mobile number."
    : "No phone number is on record for this recipient.";
}

/**
 * Send one SMS each to a list of people.
 *
 * Recipients whose stored phone cannot be a Kenyan mobile are written as FAILED
 * with the reason rather than dropped: a group where half the members never get
 * their summary should be able to see why, and blank phone numbers are the
 * commonest cause in imported data.
 *
 * Rows are created one at a time, in step with their send, so each row's id is
 * known for certain. A nested `create` returns them in no guaranteed order,
 * which is a quiet way to attribute one member's message to another.
 */
export async function dispatchSms(
  input: DispatchSmsInput,
  dependencies: Dependencies = {}
): Promise<DispatchSmsResult> {
  try {
    if (input.recipients.length === 0) return { ...NOTHING, reason: "No recipients." };

    const integration = await findSmsIntegration();
    if (!integration) {
      return { ...NOTHING, reason: "No SMS provider is configured." };
    }

    const broadcast = await prisma.smsBroadcast.create({
      data: {
        requestedByUserId: input.requestedByUserId ?? null,
        kind: input.kind,
        meetingId: input.meetingId ?? null,
        targetType: "GROUP",
        targetGroupId: input.groupId ?? null,
        provider: integration.provider,
        // One shared body when there is one; the label otherwise. The text a
        // given person received is always on their own row.
        message: input.recipients.length === 1 ? input.recipients[0]!.message : input.label,
        status: "QUEUED",
        recipientCount: input.recipients.length,
        queuedCount: input.recipients.length
      }
    });

    const counts = { attempted: 0, sent: 0, failed: 0, queued: 0, skipped: 0 };
    let segments = 0;

    for (const recipient of input.recipients) {
      const sendable = isSendableSmsPhone(recipient.phone);

      const row = await prisma.smsBroadcastRecipient.create({
        data: {
          broadcastId: broadcast.id,
          memberId: recipient.memberId ?? null,
          groupId: input.groupId ?? null,
          memberName: recipient.memberName,
          phone: recipient.phone,
          message: recipient.message,
          provider: integration.provider,
          status: "QUEUED"
        }
      });

      if (!sendable) {
        counts.skipped += 1;
        await prisma.smsBroadcastRecipient.update({
          where: { id: row.id },
          data: {
            status: "FAILED",
            providerStatus: "NO_PHONE",
            providerMessage: unusablePhoneReason(recipient.phone)
          }
        });
        continue;
      }

      counts.attempted += 1;
      segments += smsSegments(recipient.message);

      const result = await sendSms(
        {
          provider: integration.provider,
          phone: recipient.phone,
          message: recipient.message,
          credentials: integration.credentials
        },
        dependencies
      );

      if (result.status === "SENT") counts.sent += 1;
      else if (result.status === "FAILED") counts.failed += 1;
      else counts.queued += 1;

      await prisma.smsBroadcastRecipient.update({
        where: { id: row.id },
        data: {
          status: result.status,
          providerReference: result.providerReference ?? null,
          providerStatus:
            result.providerStatus === undefined || result.providerStatus === null
              ? null
              : String(result.providerStatus),
          providerMessage: result.providerMessage ?? null,
          sentAt: result.status === "SENT" ? new Date() : null
        }
      });
    }

    await prisma.smsBroadcast.update({
      where: { id: broadcast.id },
      data: {
        status: broadcastStatus(counts),
        sentCount: counts.sent,
        failedCount: counts.failed + counts.skipped,
        queuedCount: counts.queued
      }
    });

    return {
      broadcastId: broadcast.id,
      attempted: counts.attempted,
      sent: counts.sent,
      failed: counts.failed,
      queued: counts.queued,
      skipped: counts.skipped,
      segments
    };
  } catch (error) {
    // Deliberately swallowed. The money, the meeting or the approval is already
    // recorded; a notification failure is not a reason to fail the operation
    // that produced it.
    console.error("[outbound-sms] dispatch failed", error);
    return { ...NOTHING, reason: error instanceof Error ? error.message : "SMS dispatch failed." };
  }
}

/**
 * Run a dispatch after the HTTP response has gone out.
 *
 * Thirty sequential provider calls is fifteen seconds a treasurer would
 * otherwise spend watching a spinner after sealing a meeting. Awaiting nothing
 * here is the point; the promise handles its own failure so an unhandled
 * rejection cannot take the process down.
 */
export function dispatchAfterResponse(work: () => Promise<unknown>) {
  void Promise.resolve()
    .then(work)
    .catch((error) => {
      console.error("[outbound-sms] background dispatch failed", error);
    });
}
