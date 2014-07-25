DROP TABLE IF EXISTS `patch_allpackages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patch_allpackages` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(20) NOT NULL,
  `package_name` varchar(60) NOT NULL,
  `package_version` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_server_name` (`server_name`),
  KEY `ix_package_name` (`package_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13895 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
DROP TABLE IF EXISTS `ram_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ram_usage` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(40) NOT NULL,
  `total` int(11) unsigned NOT NULL,
  `free` int(11) NOT NULL,
  `buffers` int(11) NOT NULL,
  `cached` int(11) DEFAULT NULL,
  `swapcached` int(11) DEFAULT NULL,
  `swaptotal` int(11) DEFAULT NULL,
  `date_added` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_server_name` (`server_name`)
) ENGINE=InnoDB AUTO_INCREMENT=79146 DEFAULT CHARSET=utf8;