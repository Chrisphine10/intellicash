-- Demo data is for demo.
--
-- `Programme.isDemo` already kept the sample programme off the public project
-- list. It was not enough: a demo group, its partner and its agent each reach
-- the outside world by a route of their own.
--
--   * the landing page lists PARTNERS
--   * the public store lists VILLAGE AGENTS
--   * the portfolio and foundation reports count GROUPS, members and savings
--
-- The last one matters most. Demo figures on a marketing page look wrong to
-- anyone who reads them; demo figures inside a partner's impact numbers look
-- exactly like real ones.
--
-- Additive and defaulted to false, so every existing row is treated as real.
-- That is the safe direction: a real group wrongly hidden is a visible bug
-- somebody reports, whereas demo data wrongly counted is the failure being
-- fixed here and nobody notices it from the inside.

ALTER TABLE "Partner"      ADD COLUMN "isDemo" BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE "VillageAgent" ADD COLUMN "isDemo" BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE "Group"        ADD COLUMN "isDemo" BOOLEAN NOT NULL DEFAULT false;

-- Backfill what the demo seed has already created, by the markers it uses.
-- These are the seed's own constants, not a guess at what looks demo-ish:
-- naming a real group "Demo Farmers" must not hide it from its own partner.

-- The demo group, by the code the seed pins.
UPDATE "Group" SET "isDemo" = true WHERE "code" = 'IWL-DEMO-0001';

-- The demo agent, by the address the seed signs in with.
UPDATE "VillageAgent" SET "isDemo" = true
 WHERE "email" = 'demo.agent@intellicash.co.ke';

-- The scaffolding programme the seed creates when a database has none.
UPDATE "Programme" SET "isDemo" = true
 WHERE "publicSlug" = 'demo-programme';

-- Its partner, reached through that programme rather than by name, so a real
-- partner that happens to be called "Demo Programme Partner" is left alone.
UPDATE "Partner" SET "isDemo" = true
 WHERE "id" IN (
   SELECT "partnerId" FROM "Programme"
    WHERE "publicSlug" = 'demo-programme' AND "isDemo" = true
 );

-- Anything hanging off a programme already marked demo. This is what catches a
-- database where the demo seed reused a real programme before the seed was
-- fixed to make its own — those rows are demo whatever they are called.
UPDATE "Group" SET "isDemo" = true
 WHERE "programmeId" IN (SELECT "id" FROM "Programme" WHERE "isDemo" = true);
