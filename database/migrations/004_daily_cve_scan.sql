-- 004: Daily full-application CVE scanning support

ALTER TABLE `servers`
  ADD COLUMN IF NOT EXISTS `last_cve_scan` DATETIME NULL DEFAULT NULL AFTER `last_checked`;

ALTER TABLE `containers`
  MODIFY `runtime` ENUM('docker', 'podman', 'lxc', 'host', 'image') NOT NULL;
