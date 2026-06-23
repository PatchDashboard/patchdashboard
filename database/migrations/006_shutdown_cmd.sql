ALTER TABLE `servers`
  ADD COLUMN IF NOT EXISTS `shutdown_cmd_sent` tinyint(1) NOT NULL DEFAULT 0 AFTER `reboot_cmd_sent`;
