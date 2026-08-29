-- Per-category control over which system notifications are also texted.
--
-- A missing row means enabled. That is deliberate: a notification type added
-- later reaches people by default instead of being quietly console-only until
-- somebody notices nobody was told.

CREATE TABLE "NotificationSmsSetting" (
  "type"            TEXT NOT NULL PRIMARY KEY,
  "smsEnabled"      BOOLEAN NOT NULL DEFAULT true,
  "updatedByUserId" TEXT,
  "updatedAt"       DATETIME NOT NULL
);
