-- Outbound SMS to members: share purchases and end-of-meeting summaries.
--
-- Automatic sends reuse SmsBroadcast so there is one place to answer "what did
-- we text this group and did it arrive", but they need two things a manual
-- broadcast never did: a way to tell them apart, and a per-recipient body,
-- because a meeting summary tells each member what THEY transacted.
--
-- Additive only. Every column is nullable or defaulted, so existing rows and
-- the current mobile clients are untouched.

ALTER TABLE "SmsBroadcast" ADD COLUMN "kind" TEXT NOT NULL DEFAULT 'MANUAL';
ALTER TABLE "SmsBroadcast" ADD COLUMN "meetingId" TEXT;

CREATE INDEX "SmsBroadcast_kind_createdAt_idx" ON "SmsBroadcast"("kind", "createdAt");
CREATE INDEX "SmsBroadcast_meetingId_idx" ON "SmsBroadcast"("meetingId");

ALTER TABLE "SmsBroadcastRecipient" ADD COLUMN "message" TEXT;

-- Opt-in, per group. These cost money on every meeting and carry a member's
-- own financial position to a handset that may be shared, so a group turns
-- them on deliberately rather than inheriting them.
ALTER TABLE "GroupPolicy" ADD COLUMN "smsSharePurchaseEnabled" BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE "GroupPolicy" ADD COLUMN "smsMeetingSummaryEnabled" BOOLEAN NOT NULL DEFAULT false;
