CREATE TABLE `content_reviews` (
 `proposal_id` text PRIMARY KEY NOT NULL,
 `fingerprint` text NOT NULL,
 `decision` text NOT NULL,
 `reviewer_id` text NOT NULL,
 `reviewed_at` text NOT NULL
);
