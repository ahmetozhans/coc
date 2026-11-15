CREATE TABLE `layout_events` (
  `layout_id` text NOT NULL,
  `visitor_hash` text NOT NULL,
  `kind` text NOT NULL,
  `bucket` text NOT NULL,
  `created_at` integer NOT NULL,
  PRIMARY KEY (`layout_id`, `visitor_hash`, `kind`, `bucket`)
);
--> statement-breakpoint
CREATE INDEX `events_layout_kind` ON `layout_events` (`layout_id`, `kind`);
--> statement-breakpoint
CREATE TABLE `layout_favorites` (
  `layout_id` text NOT NULL,
  `visitor_hash` text NOT NULL,
  `active` integer DEFAULT 0 NOT NULL,
  `updated_at` integer NOT NULL,
  PRIMARY KEY (`layout_id`, `visitor_hash`)
);
