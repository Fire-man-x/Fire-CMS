ALTER TABLE `firecms_categories`
CHANGE `status` `status` enum('publish','pending','draft','auto-draft','trash') COLLATE 'utf8_general_ci' NOT NULL DEFAULT 'draft' AFTER `active`;