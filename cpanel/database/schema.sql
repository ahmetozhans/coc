CREATE TABLE IF NOT EXISTS public_content (
  kind ENUM('layout', 'guide') NOT NULL,
  id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  sort_order INT UNSIGNED NOT NULL,
  payload JSON NOT NULL,
  PRIMARY KEY (kind, id),
  KEY content_order (kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
