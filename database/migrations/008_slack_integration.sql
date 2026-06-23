ALTER TABLE `webhook_endpoints`
  ADD COLUMN IF NOT EXISTS `slack_channel` VARCHAR(64) NULL AFTER `slack_format`,
  ADD COLUMN IF NOT EXISTS `slack_username` VARCHAR(64) NULL DEFAULT 'PatchDashboard' AFTER `slack_channel`;
