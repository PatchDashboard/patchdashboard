ALTER TABLE `alert_rules`
  ADD COLUMN IF NOT EXISTS `slack_header` VARCHAR(150) NULL AFTER `cooldown_minutes`,
  ADD COLUMN IF NOT EXISTS `slack_body_template` TEXT NULL AFTER `slack_header`,
  ADD COLUMN IF NOT EXISTS `slack_footer_template` VARCHAR(512) NULL AFTER `slack_body_template`;
