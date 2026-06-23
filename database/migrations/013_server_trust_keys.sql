-- 013: Server trust revocation and SSH enrollment tracking

CREATE TABLE IF NOT EXISTS revoked_client_keys (
  client_key VARCHAR(255) NOT NULL,
  server_id MEDIUMINT UNSIGNED NOT NULL,
  revoked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (client_key),
  INDEX idx_revoked_server (server_id)
);

ALTER TABLE servers
  ADD COLUMN IF NOT EXISTS client_key_revoked_at DATETIME NULL AFTER client_key,
  ADD COLUMN IF NOT EXISTS dashboard_ssh_fingerprint VARCHAR(128) NULL AFTER ssh_user;
