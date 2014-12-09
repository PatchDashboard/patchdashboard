CREATE TABLE patch_allpackages_old LIKE patch_allpackages;
INSERT INTO patch_allpackages_old SELECT * FROM patch_allpackages;
DROP TABLE patch_allpackages;
CREATE TABLE `patch_allpackages` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(20) NOT NULL,
  `package_name` varchar(60) NOT NULL,
  `package_version` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_server_name` (`server_name`),
  KEY `ix_package_name` (`package_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
INSERT INTO patch_allpackages SELECT * from patch_allpackages_old;
DROP table patch_allpackages_old;

CREATE TABLE servers_old LIKE servers;
INSERT INTO servers_old SELECT * FROM servers;
DROP TABLE servers;
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
INSERT INTO servers SELECT * from servers_old;
DROP table servers_old;

CREATE TABLE supressed_old LIKE supressed;
INSERT INTO supressed_old SELECT * FROM supressed;
DROP TABLE supressed;
CREATE TABLE IF NOT EXISTS `supressed` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `package_name` varchar(40) NOT NULL,
  `server_name` varchar(20) NOT NULL, /* 0 for global */
  PRIMARY KEY (`id`),
  KEY `ix_package_name` (`package_name`),
  KEY `ix_server_name` (`server_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
INSERT INTO supressed SELECT * from supressed_old;
DROP table supressed_old;

CREATE TABLE distro_old LIKE distro;
INSERT INTO distro_old SELECT * FROM distro;
DROP TABLE distro;
CREATE TABLE `distro` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `distro_name` varchar(20) NOT NULL,
  `icon_path` varchar(255) NOT NULL,
  `upgrade_command` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_distro_name` (`distro_name`),
  KEY `ix_icon_path` (`icon_path`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
INSERT INTO distro SELECT * from distro_old;
INSERT IGNORE INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(1,'Ubuntu','img/icon/ubuntu.png','apt-get -y install');
INSERT IGNORE INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(2,'Debian','img/icon/debian.png','apt-get -y install');
INSERT IGNORE INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(3,'Fedora','img/icon/fedora.png','yum -y install');
INSERT IGNORE INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(4,'CentOS','img/icon/centos.png','yum -y install');
INSERT IGNORE INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(5,'RHEL','img/icon/rhel.png','yum -y install');
INSERT IGNORE INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(6,'Oracle','img/icon/oracle.png','yum -y install');
DROP table distro_old;

CREATE TABLE distro_version_old LIKE distro_version;
INSERT INTO distro_version_old SELECT * FROM distro_version;
DROP TABLE distro_version;
DROP TABLE IF EXISTS `distro_version`;
CREATE TABLE `distro_version` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `version_num` varchar(20) NOT NULL,
  `distro_id` mediumint(8) NOT NULL,
  `eol_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_version_num` (`version_num`),
  KEY `ix_distro_id` (`distro_id`),
  KEY `ix_eol_date` (`eol_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
INSERT INTO distro_version SELECT * from distro_version_old;
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('10.04_Desktop',1,'2013-05-31');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('10.04_Server',1,'2015-04-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('12.04_Desktop',1,'2017-04-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('12.04_Server',1,'2017-04-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('14.04_Desktop',1,'2019-04-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('14.04_Server',1,'2019-04-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('6',2,'2016-02-28');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('7',2,'2024-07-26');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('15',3,'2012-06-26');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('16',3,'2013-02-12');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('17',3,'2013-07-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('18',3,'2014-01-14');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('19',3,'2014-07-26');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('20',3,'2015-01-26');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('5',4,'2017-03-31');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('6',4,'2020-11-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('5',5,'2017-03-31');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('6',5,'2020-11-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('7',5,'2024-06-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('5',6,'2017-03-31');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('6',6,'2020-11-30');
INSERT IGNORE INTO distro_version(version_num,distro_id,eol_date) VALUES('7',6,'2024-06-30');
DROP table distro_version_old;

CREATE TABLE users_old LIKE users;
INSERT INTO users_old SELECT * FROM users;
DROP TABLE users;
CREATE TABLE users (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(40) NOT NULL,
  `email` varchar(80) NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `display_name` varchar(50) DEFAULT NULL,
  `password` char(41) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
INSERT INTO users SELECT * from users_old;
DROP table users_old;

CREATE TABLE patches_old LIKE patches;
INSERT INTO patches_old SELECT * FROM patches;
DROP TABLE patches;
CREATE TABLE `patches` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(40) NOT NULL,
  `package_name` varchar(60) NOT NULL,
  `current` varchar(60) NOT NULL,
  `new` varchar(60) NOT NULL,
  `urgency` varchar(20) DEFAULT NULL,
  `bug_url` varchar(512) DEFAULT NULL,
  `distro` mediumint(8) DEFAULT NULL,
  `to_upgrade` tinyint(1) NOT NULL DEFAULT '0',
  `upgraded` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `server_name` (`server_name`),
  KEY `ix_package_name` (`package_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1450 DEFAULT CHARSET=utf8;
INSERT INTO patches SELECT * from patches_old;
DROP table patches_old;
