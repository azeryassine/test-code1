/*DROP TABLE IF EXISTS `search_backend_data`;
CREATE TABLE `search_backend_data` (*/
CREATE TABLE IF NOT EXISTS `search_backend_data` (
   `id` int(11) NOT NULL,
   `key` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin default '',
   `index` int(11) unsigned DEFAULT '0',
   `fullpath` varchar(765) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL, /* path in utf8 (3-byte) using the full key length of 3072 bytes */
   `maintype` varchar(8) NOT NULL DEFAULT '',
   `type` varchar(20) DEFAULT NULL,
   `subtype` varchar(190) DEFAULT NULL,
   `published` tinyint(1) unsigned DEFAULT NULL,
   `creationDate` int(11) unsigned DEFAULT NULL,
   `modificationDate` int(11) unsigned DEFAULT NULL,
   `userOwner` int(11) DEFAULT NULL,
   `userModification` int(11) DEFAULT NULL,
   `data` longtext,
   `properties` text,
   PRIMARY KEY (`id`,`maintype`),
   KEY `key` (`key`),
   KEY `index` (`index`),
   KEY `fullpath` (`fullpath`),
   KEY `maintype` (`maintype`),
   KEY `type` (`type`),
   KEY `subtype` (`subtype`),
   KEY `published` (`published`),
   FULLTEXT KEY `fulltext` (`data`,`properties`)
) DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;