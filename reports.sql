DROP TABLE IF EXISTS `patch_allpackages`;
CREATE TABLE `patch_allpackages` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(20) NOT NULL,
  `package_name` varchar(60) NOT NULL,
  `package_version` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_server_name` (`server_name`),
  KEY `ix_package_name` (`package_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `patch_allpackages`;

DROP TABLE IF EXISTS `servers`;
CREATE TABLE `servers` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(20) NOT NULL,
  `distro_id` mediumint(8) NOT NULL,
  `server_ip` varchar(60) NOT NULL,
  `distro_version` mediumint(8) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_server_name` (`server_name`),
  KEY `ix_server_ip` (`server_ip`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `supressed`;
CREATE TABLE `supressed` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `package_name` varchar(40) NOT NULL,
  `server_name` varchar(20) NOT NULL, /* 0 for global */
  PRIMARY KEY (`id`),
  KEY `ix_package_name` (`package_name`),
  KEY `ix_server_name` (`server_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `distro`;
CREATE TABLE `distro` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `distro_name` varchar(20) NOT NULL,
  `icon_path` varchar(255) NOT NULL,
  `upgrade_command` varchar(50) NOT NULL, /* Example: "apt-get -y install" (minus the space at the end */
  PRIMARY KEY (`id`),
  KEY `ix_distro_name` (`distro_name`),
  KEY `ix_icon_path` (`icon_path`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `distro_version`;
CREATE TABLE `distro_version` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `version_num` varchar(20) NOT NULL,
  `distro_id` mediumint(8) NOT NULL,
  `eol_date` datetime() NOT NULL, /* Example: "apt-get -y install" (minus the space at the end */
  PRIMARY KEY (`id`),
  KEY `ix_version_num` (`distro_name`),
  KEY `ix_distro_id` (`icon_path`),
  KEY `ix_eol_date` (`eol_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `patches`;
CREATE TABLE `patches` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(40) NOT NULL,
  `package_name` varchar(60) NOT NULL,
  `current` varchar(60) NOT NULL,
  `new` varchar(60) NOT NULL,
  `urgency` varchar(20) DEFAULT NULL,
  `bug_url` varchar(512) DEFAULT NULL,
  `distro` varchar(20) DEFAULT NULL,
  `to_upgrade` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `server_name` (`server_name`),
  KEY `ix_package_name` (`package_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1450 DEFAULT CHARSET=utf8;