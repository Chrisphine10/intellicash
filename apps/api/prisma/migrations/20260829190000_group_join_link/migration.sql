-- The secret half of a group's public invite link and QR code.
--
-- Deliberately not the group code: codes read IWL-KBU-0001 and are trivially
-- enumerable, so a link built on one would let anybody walk the platform's
-- group list by counting upwards. This is random and rotatable, so a poster
-- that ends up somewhere it should not be can be revoked without changing the
-- code the group is known by.
--
-- Nullable: a group gets one the first time somebody asks to share a link, so
-- a group that never invites anyone has no public surface at all.

ALTER TABLE "Group" ADD COLUMN "joinToken" TEXT;
ALTER TABLE "Group" ADD COLUMN "joinTokenIssuedAt" DATETIME;

CREATE UNIQUE INDEX "Group_joinToken_key" ON "Group"("joinToken");
