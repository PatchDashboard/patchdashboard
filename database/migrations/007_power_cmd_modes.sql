ALTER TABLE `servers`
  ADD COLUMN IF NOT EXISTS `power_cmd_action` VARCHAR(10) NULL AFTER `shutdown_cmd_sent`,
  ADD COLUMN IF NOT EXISTS `power_cmd_mode` VARCHAR(12) NULL AFTER `power_cmd_action`,
  ADD COLUMN IF NOT EXISTS `power_cmd_scheduled_at` DATETIME NULL AFTER `power_cmd_mode`;

UPDATE `servers`
SET `power_cmd_action` = 'shutdown',
    `power_cmd_mode` = 'graceful',
    `power_cmd_scheduled_at` = NULL
WHERE `shutdown_cmd_sent` = 1
  AND `power_cmd_action` IS NULL;

UPDATE `servers`
SET `power_cmd_action` = 'reboot',
    `power_cmd_mode` = 'graceful',
    `power_cmd_scheduled_at` = NULL
WHERE `reboot_cmd_sent` = 1
  AND COALESCE(`shutdown_cmd_sent`, 0) = 0
  AND `power_cmd_action` IS NULL;
