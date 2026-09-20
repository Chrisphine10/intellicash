-- A share-out done on a phone is recorded on the server together with the close
-- of the cycle it ended, in one step. The phone gives the share-out an id; it is
-- kept on the cycle it closed so that sending the same share-out again (the
-- reply was lost, the phone retried) is recognised and answered with what was
-- already recorded, rather than paying members twice or closing another cycle.
--
-- Nullable: every cycle closed any other way (from the console, by hand) has
-- none. NULLs do not collide in a unique index, so those rows are unaffected.
ALTER TABLE "Cycle" ADD COLUMN "closedByShareOutId" TEXT;

CREATE UNIQUE INDEX "Cycle_groupId_closedByShareOutId_key" ON "Cycle"("groupId", "closedByShareOutId");
