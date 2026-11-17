CREATE TABLE IF NOT EXISTS admin_credentials (
  id TINYINT UNSIGNED PRIMARY KEY,
  password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL
) ENGINE=InnoDB;

INSERT INTO admin_credentials (id, password_hash) VALUES (1, NULL)
ON DUPLICATE KEY UPDATE id = 1;
