ALTER TABLE `webhook_endpoints`
  ADD COLUMN IF NOT EXISTS `slack_icon_url` VARCHAR(512) NULL AFTER `slack_username`;
