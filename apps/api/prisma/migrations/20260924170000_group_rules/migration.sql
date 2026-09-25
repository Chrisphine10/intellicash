-- The group's own rules reach the server (they used to live only on its phone),
-- a loan keeps the interest type it was lent under, and member sign-ins become
-- a per-group choice.
--
-- Every new GroupPolicy rule is nullable: null means "never set", so existing
-- groups are not suddenly checked against defaults nobody chose. Existing
-- loans were all computed flat, and stay flat.

ALTER TABLE "GroupPolicy" ADD COLUMN "interestType" TEXT;
ALTER TABLE "GroupPolicy" ADD COLUMN "shareValueCents" INTEGER;
ALTER TABLE "GroupPolicy" ADD COLUMN "maxSharesPerMeeting" INTEGER;
ALTER TABLE "GroupPolicy" ADD COLUMN "socialFundCents" INTEGER;
ALTER TABLE "GroupPolicy" ADD COLUMN "loanMultiplierBps" INTEGER;
-- Null = never decided: groups whose members already sign in stay open.
ALTER TABLE "GroupPolicy" ADD COLUMN "memberAccountsEnabled" BOOLEAN;
ALTER TABLE "Loan" ADD COLUMN "interestType" TEXT NOT NULL DEFAULT 'FLAT';
