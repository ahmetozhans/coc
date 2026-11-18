-- Additive migration. Existing content, accounts, decisions and counters are untouched.
CREATE TABLE IF NOT EXISTS collector_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  revision INT UNSIGNED NOT NULL DEFAULT 0,
  document JSON NOT NULL,
  last_discovery DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO collector_settings (id, document) VALUES
(1, '{"automatic":false,"hour":4,"maxPerSource":20,"sources":{"finder":true,"loot":true,"clashly":true}}');

CREATE TABLE IF NOT EXISTS collector_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  started_at VARCHAR(30) NOT NULL,
  finished_at VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL,
  trigger_kind VARCHAR(10) NOT NULL,
  summary JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collector_proposals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_key VARCHAR(160) CHARACTER SET ascii NOT NULL,
  content_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
  game_id VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  image_sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
  image_dhash CHAR(16) CHARACTER SET ascii NOT NULL,
  source_hash CHAR(64) CHARACTER SET ascii NOT NULL,
  payload JSON NOT NULL,
  baseline_hash CHAR(64) CHARACTER SET ascii NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  issue VARCHAR(500) NULL,
  first_seen VARCHAR(30) NOT NULL,
  last_checked VARCHAR(30) NOT NULL,
  reviewed_at VARCHAR(30) NULL,
  reviewer VARCHAR(100) NULL,
  UNIQUE KEY source_version (source_id, source_key, source_hash),
  KEY proposal_state (status, id),
  KEY game_duplicate (game_id),
  KEY image_duplicate (image_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collector_issues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  source_id VARCHAR(16) NOT NULL,
  source_key VARCHAR(160) NOT NULL,
  kind VARCHAR(30) NOT NULL,
  message VARCHAR(500) NOT NULL,
  checked_at VARCHAR(30) NOT NULL,
  KEY run_issues (run_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collector_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(32) NOT NULL,
  content_id VARCHAR(100) NOT NULL,
  fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
  actor VARCHAR(100) NOT NULL,
  created_at VARCHAR(30) NOT NULL,
  details JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
