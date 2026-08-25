-- Catch the demo programme that has no publicSlug.
--
-- The previous migration marked it by `publicSlug = 'demo-programme'`, which is
-- what the seed sets today. Production's row has no slug at all — it predates
-- that, or the slug was cleared — so it was missed and stayed on the public
-- project list under "Demo Programme / Demo Programme Partner". Found by
-- reading the live endpoint after deploying, not by reasoning about the seed.
--
-- Naming alone is deliberately NOT enough to hide something. A real programme
-- called "Demo Programme" hidden from its own partner would be a worse bug than
-- the one being fixed, so all three of these must hold:
--
--   1. the exact name the seed uses;
--   2. a partner with the exact name the seed uses;
--   3. no real groups attached — the seed's scaffolding never has any.
--
-- A genuine programme would have to match all three to be caught, which in
-- practice it cannot: the third alone excludes any programme doing real work.

-- Re-assert the group marker first, so this migration stands on its own.
--
-- The "no real groups attached" test below is only meaningful once the demo
-- group is known to be a demo group. Leaning on the previous migration having
-- done it makes this one silently do nothing when replayed against a database
-- that reached this point another way — which is exactly how the original bug
-- got through. Same exact marker as before, so it stays idempotent.
UPDATE "Group" SET "isDemo" = true WHERE "code" = 'IWL-DEMO-0001';

UPDATE "Programme" SET "isDemo" = true
 WHERE "isDemo" = false
   AND "name" = 'Demo Programme'
   AND "partnerId" IN (SELECT "id" FROM "Partner" WHERE "name" = 'Demo Programme Partner')
   AND NOT EXISTS (
       SELECT 1 FROM "Group"
        WHERE "Group"."programmeId" = "Programme"."id"
          AND "Group"."isDemo" = false
   );

-- And its partner, reached through the programme rather than by name, so a real
-- partner that shares the name is left alone.
UPDATE "Partner" SET "isDemo" = true
 WHERE "isDemo" = false
   AND "id" IN (SELECT "partnerId" FROM "Programme" WHERE "isDemo" = true)
   AND NOT EXISTS (
       SELECT 1 FROM "Programme"
        WHERE "Programme"."partnerId" = "Partner"."id"
          AND "Programme"."isDemo" = false
   );

-- Anything hanging off a programme this has just marked.
UPDATE "Group" SET "isDemo" = true
 WHERE "programmeId" IN (SELECT "id" FROM "Programme" WHERE "isDemo" = true);

UPDATE "VillageAgent" SET "isDemo" = true
 WHERE "isDemo" = false
   AND "id" IN (
       SELECT "villageAgentId" FROM "VillageAgentProgramme"
        WHERE "programmeId" IN (SELECT "id" FROM "Programme" WHERE "isDemo" = true)
   )
   AND NOT EXISTS (
       SELECT 1 FROM "VillageAgentProgramme" vap
        JOIN "Programme" p ON p."id" = vap."programmeId"
       WHERE vap."villageAgentId" = "VillageAgent"."id" AND p."isDemo" = false
   );

-- Give it back the slug the seed looks it up by.
--
-- Without this the row stays unfindable: `seed-demo.ts` does
-- `findFirst({ where: { publicSlug: 'demo-programme' } }) ?? create(...)`, so the
-- next seed run against production would not find this programme and would
-- create a SECOND demo programme beside it — which, being new, would not be
-- flagged and would appear on the public list all over again.
--
-- `publicSlug` is @unique, hence the guard: claim it only if nothing holds it.
UPDATE "Programme" SET "publicSlug" = 'demo-programme'
 WHERE "isDemo" = true
   AND "publicSlug" IS NULL
   AND "name" = 'Demo Programme'
   AND NOT EXISTS (SELECT 1 FROM "Programme" p WHERE p."publicSlug" = 'demo-programme')
   AND "id" = (
       SELECT "id" FROM "Programme"
        WHERE "isDemo" = true AND "publicSlug" IS NULL AND "name" = 'Demo Programme'
        ORDER BY "createdAt" LIMIT 1
   );
