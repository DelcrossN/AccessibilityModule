-- SQL script to manually create accessibility module tables
-- Run these commands in your database to create the required tables

-- Create accessibility_reports table
CREATE TABLE IF NOT EXISTS `accessibility_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(2048) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `violation_count` int(10) unsigned NOT NULL DEFAULT '0',
  `critical_count` int(10) unsigned NOT NULL DEFAULT '0',
  `serious_count` int(10) unsigned NOT NULL DEFAULT '0',
  `moderate_count` int(10) unsigned NOT NULL DEFAULT '0',
  `minor_count` int(10) unsigned NOT NULL DEFAULT '0',
  `last_scanned` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`(191)),
  KEY `last_scanned` (`last_scanned`),
  KEY `violation_count` (`violation_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores summary data for accessibility reports by URL.';

-- Update accessibility_violations table with new fields
ALTER TABLE `accessibility_violations` 
ADD COLUMN IF NOT EXISTS `url` varchar(2048) NOT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `rule_id` varchar(255) NOT NULL DEFAULT '' AFTER `url`,
ADD COLUMN IF NOT EXISTS `impact_weight` int(10) unsigned NOT NULL DEFAULT '4' AFTER `impact`,
ADD COLUMN IF NOT EXISTS `help` text AFTER `description`,
ADD COLUMN IF NOT EXISTS `tags` text AFTER `help_url`,
ADD COLUMN IF NOT EXISTS `nodes_count` int(10) unsigned NOT NULL DEFAULT '0' AFTER `tags`,
ADD COLUMN IF NOT EXISTS `nodes_data` longtext AFTER `nodes_count`;

-- Add new indexes
ALTER TABLE `accessibility_violations` 
ADD INDEX IF NOT EXISTS `url` (`url`(191)),
ADD INDEX IF NOT EXISTS `impact_weight` (`impact_weight`);

-- Alternative: If you want to completely recreate accessibility_violations table with new structure
-- (Uncomment the lines below and comment out the ALTER statements above)

/*
DROP TABLE IF EXISTS `accessibility_violations`;

CREATE TABLE `accessibility_violations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(2048) NOT NULL,
  `rule_id` varchar(255) NOT NULL DEFAULT '',
  `impact` varchar(50) NOT NULL,
  `impact_weight` int(10) unsigned NOT NULL DEFAULT '4',
  `description` varchar(255) NOT NULL,
  `help` text,
  `help_url` varchar(2048) DEFAULT NULL,
  `tags` text,
  `nodes_count` int(10) unsigned NOT NULL DEFAULT '0',
  `nodes_data` longtext,
  `scanned_url` varchar(2048) NOT NULL,
  `nodes` longtext NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `url` (`url`(191)),
  KEY `impact_weight` (`impact_weight`),
  KEY `timestamp_impact` (`timestamp`,`impact`),
  KEY `timestamp_description` (`timestamp`,`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores accessibility violation data from axe-core scans.';
*/
