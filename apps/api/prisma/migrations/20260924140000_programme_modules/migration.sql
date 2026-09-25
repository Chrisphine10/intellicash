-- Optional modules, switched on per programme from admin Settings.
--
-- A group has a module when any programme it belongs to has it on. Both start
-- OFF on every existing programme: Intelli-Store is being held back until it is
-- ready, and voting is opt-in. IWL admins can still prepare a module (products,
-- suppliers) while it is off; everyone else is refused by the API.

ALTER TABLE "Programme" ADD COLUMN "storeEnabled" BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE "Programme" ADD COLUMN "votingEnabled" BOOLEAN NOT NULL DEFAULT false;
