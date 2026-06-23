-- 003: Container vulnerability and alerting tables

CREATE TABLE IF NOT EXISTS `containers` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `server_id` MEDIUMINT UNSIGNED NOT NULL,
  `runtime` ENUM('docker', 'podman', 'lxc') NOT NULL,
  `runtime_id` VARCHAR(128) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `image_ref` VARCHAR(512) DEFAULT NULL,
  `digest` VARCHAR(128) DEFAULT NULL,
  `status` VARCHAR(32) DEFAULT 'running',
  `last_scanned_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_server_runtime` (`server_id`, `runtime`, `runtime_id`),
  KEY `ix_server_id` (`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `container_vulnerabilities` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `container_id` MEDIUMINT UNSIGNED NOT NULL,
  `cve_id` VARCHAR(32) NOT NULL,
  `severity` VARCHAR(16) NOT NULL,
  `package_name` VARCHAR(128) DEFAULT NULL,
  `installed_version` VARCHAR(64) DEFAULT NULL,
  `fixed_version` VARCHAR(64) DEFAULT NULL,
  `title` VARCHAR(512) DEFAULT NULL,
  `first_seen` DATETIME NOT NULL,
  `last_seen` DATETIME NOT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_container_cve` (`container_id`, `cve_id`),
  KEY `ix_severity` (`severity`),
  KEY `ix_resolved_at` (`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `container_vuln_suppressions` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cve_id` VARCHAR(32) DEFAULT NULL,
  `package_name` VARCHAR(128) DEFAULT NULL,
  `container_id` MEDIUMINT UNSIGNED DEFAULT NULL,
  `server_id` MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alert_rules` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `min_severity` VARCHAR(16) NOT NULL DEFAULT 'HIGH',
  `alert_on` VARCHAR(16) NOT NULL DEFAULT 'new',
  `cooldown_minutes` INT NOT NULL DEFAULT 60,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO alert_rules (id, min_severity, alert_on, cooldown_minutes) VALUES (1, 'HIGH', 'new', 60);

CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `url` VARCHAR(512) NOT NULL,
  `secret` VARCHAR(255) DEFAULT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `slack_format` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alert_events` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel` VARCHAR(16) NOT NULL,
  `reference_id` MEDIUMINT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL,
  `summary` TEXT NOT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` VARCHAR(64) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
