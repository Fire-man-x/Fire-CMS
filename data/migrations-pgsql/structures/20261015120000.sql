-- PostgreSQL varianta ../../migrations/structures/20261015120000.sql (stejné schéma): globální e-mail
-- v `firecms_settings` (naplní se e-mailem výchozího jazyka) a telefon pro jazyk v `firecms_settingDescriptions`.

ALTER TABLE "firecms_settings" ADD COLUMN "mainEmail" varchar(100);

ALTER TABLE "firecms_settingDescriptions" ADD COLUMN "contactPhone" varchar(50);

UPDATE "firecms_settings" "s"
SET "mainEmail" = (
	SELECT "d"."mainEmail"
	FROM "firecms_settingDescriptions" "d"
	INNER JOIN "firecms_languages" "l" ON "l"."languageId" = "d"."languageId"
	WHERE "d"."settingId" = "s"."id" AND "l"."default" = true
	LIMIT 1
);
