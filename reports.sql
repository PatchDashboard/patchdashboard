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
  `upgrade_command` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_distro_name` (`distro_name`),
  KEY `ix_icon_path` (`icon_path`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
INSERT INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(1,'Ubuntu','img/icon/ubuntu.png','apt-get -y install');
INSERT INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(2,'Debian','img/icon/debian.png','apt-get -y install');
INSERT INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(3,'Fedora','img/icon/fedora.png','yum -y install');
INSERT INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(4,'CentOS','img/icon/centos.png','yum -y install');
INSERT INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(5,'RHEL','img/icon/rhel.png','yum -y install');
INSERT INTO distro(id,distro_name,icon_path,upgrade_command) VALUES(6,'Oracle','img/icon/oracle.png','yum -y install');

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
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('10.04_Desktop',1,'2013-05-31');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('10.04_Server',1,'2015-04-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('12.04_Desktop',1,'2017-04-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('12.04_Server',1,'2017-04-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('14.04_Desktop',1,'2019-04-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('14.04_Server',1,'2019-04-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('6',2,'2016-02-28');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('7',2,'2024-07-26');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('15',3,'2012-06-26');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('16',3,'2013-02-12');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('17',3,'2013-07-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('18',3,'2014-01-14');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('19',3,'2014-07-26');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('20',3,'2015-01-26');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('5',4,'2017-03-31');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('6',4,'2020-11-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('5',5,'2017-03-31');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('6',5,'2020-11-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('7',5,'2024-06-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('5',6,'2017-03-31');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('6',6,'2020-11-30');
INSERT INTO distro_version(version_num,distro_id,eol_date) VALUES('7',6,'2024-06-30');


DROP TABLE IF EXISTS `patches`;
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
  `upgraded` tinyint(1),
  PRIMARY KEY (`id`),
  KEY `server_name` (`server_name`),
  KEY `ix_package_name` (`package_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1450 DEFAULT CHARSET=utf8;
