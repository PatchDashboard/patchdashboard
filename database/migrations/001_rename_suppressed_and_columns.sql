-- 001: Rename supressed table and add modern schema columns

RENAME TABLE `supressed` TO `suppressed`;

ALTER TABLE `distro`
  ADD COLUMN IF NOT EXISTS `package_manager` VARCHAR(10) NOT NULL DEFAULT 'apt' AFTER `upgrade_command`;

ALTER TABLE `distro_version`
  ADD COLUMN IF NOT EXISTS `codename` VARCHAR(32) NULL AFTER `eol_date`;

ALTER TABLE `servers`
  ADD COLUMN IF NOT EXISTS `host_capabilities` JSON NULL AFTER `reboot_cmd_sent`,
  ADD COLUMN IF NOT EXISTS `scan_containers` TINYINT(1) NOT NULL DEFAULT 1 AFTER `host_capabilities`,
  ADD COLUMN IF NOT EXISTS `ssh_user` VARCHAR(64) NOT NULL DEFAULT 'root' AFTER `scan_containers`;

UPDATE `distro` SET `package_manager` = 'yum' WHERE `distro_name` IN ('Fedora', 'CentOS', 'RHEL', 'Oracle');
UPDATE `distro` SET `package_manager` = 'dnf' WHERE `distro_name` IN ('Rocky Linux', 'AlmaLinux', 'CentOS Stream');
UPDATE `distro` SET `distro_name` = 'Raspbian' WHERE `distro_name` = 'Raspian';
