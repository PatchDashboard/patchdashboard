-- TOTP multi-factor authentication for dashboard users

ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(512) DEFAULT NULL AFTER password,
  ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret,
  ADD COLUMN totp_confirmed_at DATETIME DEFAULT NULL AFTER totp_enabled;
