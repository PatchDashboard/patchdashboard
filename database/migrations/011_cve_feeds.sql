-- Per-distro CVE feed integration (NVD, GitHub/OSV, vendor trackers)

CREATE TABLE IF NOT EXISTS cve_feed_providers (
  id VARCHAR(32) NOT NULL PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  docs_url VARCHAR(512) DEFAULT NULL,
  sync_mode ENUM('bulk', 'on_demand', 'both') NOT NULL DEFAULT 'bulk',
  default_config JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS distro_cve_feeds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  distro_id MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  provider_id VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 100,
  config JSON DEFAULT NULL,
  last_sync_at DATETIME DEFAULT NULL,
  last_sync_status ENUM('never', 'running', 'success', 'error') NOT NULL DEFAULT 'never',
  last_sync_message TEXT,
  records_synced INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uk_distro_provider (distro_id, provider_id),
  KEY ix_distro (distro_id),
  KEY ix_provider (provider_id),
  CONSTRAINT fk_distro_cve_feeds_distro FOREIGN KEY (distro_id) REFERENCES distro (id) ON DELETE CASCADE,
  CONSTRAINT fk_distro_cve_feeds_provider FOREIGN KEY (provider_id) REFERENCES cve_feed_providers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cve_catalog (
  cve_id VARCHAR(32) NOT NULL PRIMARY KEY,
  summary VARCHAR(1024) DEFAULT NULL,
  severity VARCHAR(32) DEFAULT NULL,
  cvss_score DECIMAL(4, 1) DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  modified_at DATETIME DEFAULT NULL,
  sources JSON DEFAULT NULL,
  synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cve_advisories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_id VARCHAR(32) NOT NULL,
  distro_id MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  cve_id VARCHAR(32) NOT NULL,
  package_name VARCHAR(255) NOT NULL,
  fixed_version VARCHAR(128) DEFAULT NULL,
  severity VARCHAR(32) DEFAULT NULL,
  title VARCHAR(512) DEFAULT NULL,
  advisory_url VARCHAR(512) DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  raw_ref VARCHAR(128) DEFAULT NULL,
  synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_advisory (provider_id, distro_id, cve_id, package_name),
  KEY ix_lookup (distro_id, package_name),
  KEY ix_cve (cve_id),
  KEY ix_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cve_feed_sync_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  distro_feed_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  status ENUM('running', 'success', 'error') NOT NULL,
  records_added INT UNSIGNED NOT NULL DEFAULT 0,
  records_updated INT UNSIGNED NOT NULL DEFAULT 0,
  message TEXT,
  KEY ix_feed (distro_feed_id),
  CONSTRAINT fk_cve_feed_sync_log_feed FOREIGN KEY (distro_feed_id) REFERENCES distro_cve_feeds (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cve_feed_providers (id, name, description, docs_url, sync_mode, default_config) VALUES
  ('nvd', 'NVD (NIST)', 'National Vulnerability Database CVE metadata and CVSS scores.', 'https://nvd.nist.gov/developers/vulnerabilities', 'bulk', '{"lookback_days":30}'),
  ('github', 'GitHub Advisory Database', 'GitHub Security Advisory Database (OSV/GHSA) cross-reference.', 'https://github.com/github/advisory-database', 'bulk', '{"ecosystem":"GHSA"}'),
  ('debian', 'Debian Security Tracker', 'Debian package security advisories via OSV bulk export.', 'https://security-tracker.debian.org/tracker/', 'bulk', '{"ecosystem":"Debian"}'),
  ('ubuntu_usn', 'Ubuntu Security Notices', 'Ubuntu USN advisories via OSV bulk export.', 'https://ubuntu.com/security/notices', 'bulk', '{"ecosystem":"Ubuntu"}'),
  ('redhat', 'Red Hat Security Data', 'Red Hat / RHEL security advisories via OSV bulk export.', 'https://access.redhat.com/security/security-data', 'bulk', '{"ecosystem":"Red Hat"}'),
  ('rocky', 'Rocky Linux Errata', 'Rocky Linux errata via OSV bulk export.', 'https://errata.rockylinux.org/', 'bulk', '{"ecosystem":"Rocky Linux"}'),
  ('almalinux', 'AlmaLinux Errata', 'AlmaLinux errata via OSV bulk export.', 'https://errata.almalinux.org/', 'bulk', '{"ecosystem":"AlmaLinux"}'),
  ('fedora', 'Fedora Security', 'Fedora security advisories via OSV bulk export.', 'https://fedoraproject.org/wiki/Security', 'bulk', '{"ecosystem":"Fedora"}'),
  ('ubuntuupdates', 'UbuntuUpdates.org', 'Live patch urgency lookup (legacy parity).', 'https://www.ubuntuupdates.org/', 'on_demand', '{}');

-- Global feeds (distro_id NULL)
INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config) VALUES
  (NULL, 'nvd', 1, 10, '{"lookback_days":30}'),
  (NULL, 'github', 1, 20, '{"ecosystem":"GHSA"}');

-- Per-distro feeds
INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'debian', 1, 30, '{"ecosystem":"Debian"}'
FROM distro d
WHERE d.distro_name IN ('Debian', 'Devuan', 'Raspian', 'Ubuntu');

INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'ubuntu_usn', 1, 40, '{"ecosystem":"Ubuntu"}'
FROM distro d
WHERE d.distro_name = 'Ubuntu';

INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'ubuntuupdates', 1, 50, '{}'
FROM distro d
WHERE d.distro_name IN ('Ubuntu', 'Debian', 'Devuan', 'Raspian');

-- RPM family
INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'redhat', 1, 30, '{"ecosystem":"Red Hat"}'
FROM distro d
WHERE d.distro_name IN ('RHEL', 'CentOS', 'Oracle', 'CentOS Stream', 'Rocky Linux', 'AlmaLinux');

INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'rocky', 1, 35, '{"ecosystem":"Rocky Linux"}'
FROM distro d
WHERE d.distro_name = 'Rocky Linux';

INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'almalinux', 1, 35, '{"ecosystem":"AlmaLinux"}'
FROM distro d
WHERE d.distro_name = 'AlmaLinux';

INSERT IGNORE INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
SELECT d.id, 'fedora', 1, 30, '{"ecosystem":"Fedora"}'
FROM distro d
WHERE d.distro_name = 'Fedora';
