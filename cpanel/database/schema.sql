CREATE TABLE IF NOT EXISTS public_content (
  kind ENUM('layout', 'guide') NOT NULL,
  id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  sort_order INT UNSIGNED NOT NULL,
  payload JSON NOT NULL,
  PRIMARY KEY (kind, id),
  KEY content_order (kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS layout_events (
  layout_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  visitor_hash CHAR(64) CHARACTER SET ascii NOT NULL,
  kind ENUM('view', 'copy') NOT NULL,
  bucket VARCHAR(20) CHARACTER SET ascii NOT NULL,
  created_at BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (layout_id, visitor_hash, kind, bucket),
  KEY visitor_recent (visitor_hash, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS layout_favorites (
  layout_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  visitor_hash CHAR(64) CHARACTER SET ascii NOT NULL,
  active TINYINT UNSIGNED NOT NULL,
  updated_at BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (layout_id, visitor_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS content_reviews (
  proposal_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
  decision ENUM('approved', 'rejected') NOT NULL,
  reviewer_id VARCHAR(100) NOT NULL,
  reviewed_at VARCHAR(30) CHARACTER SET ascii NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
