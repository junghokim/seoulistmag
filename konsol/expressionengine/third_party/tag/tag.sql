CREATE TABLE IF NOT EXISTS `exp_tag_tags` (
`tag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`tag_alpha` char(3) NOT NULL,
`tag_name` varchar(200) NOT NULL,
`site_id` smallint(3) unsigned NOT NULL DEFAULT '1',
`author_id` int(10) unsigned NOT NULL,
`entry_date` int(10) NOT NULL,
`edit_date` int(10) NOT NULL DEFAULT '0',
`clicks` int(10) NOT NULL DEFAULT '0',
`total_entries` int(10) NOT NULL DEFAULT '0',
`channel_entries` int(10) NOT NULL DEFAULT '0',
`gallery_entries` int(10) NOT NULL DEFAULT '0',
PRIMARY KEY (`tag_id`),
KEY `tag_name` (`tag_name`),
KEY `tag_alpha` (`tag_alpha`),
KEY `author_id` (`author_id`),
KEY `site_id` (`site_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;


CREATE TABLE IF NOT EXISTS `exp_tag_bad_tags` (
`tag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`tag_name` varchar(150) NOT NULL,
`site_id` smallint(3) unsigned NOT NULL DEFAULT '1',
`author_id` int(10) unsigned NOT NULL,
`edit_date` int(10) NOT NULL DEFAULT '0',
PRIMARY KEY (`tag_id`),
KEY `tag_name` (`tag_name`),
KEY `site_id` (`site_id`),
KEY `author_id` (`author_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_tag_entries` (
`entry_id` int(10) unsigned NOT NULL,
`tag_id` int(10) unsigned NOT NULL,
`channel_id` smallint(3) unsigned NOT NULL,
`site_id` smallint(3) unsigned NOT NULL DEFAULT '1',
`author_id` int(10) unsigned NOT NULL,
`ip_address` varchar(16) NOT NULL DEFAULT '0',
`type` varchar(16) NOT NULL DEFAULT 'weblog',
`remote` char(1) NOT NULL DEFAULT 'n',
KEY `entry_id` (`entry_id`),
KEY `tag_id` (`tag_id`),
KEY `channel_id` (`channel_id`),
KEY `site_id` (`site_id`),
KEY `author_id` (`author_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_tag_preferences` (
`tag_preference_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`tag_preference_name` varchar(100) NOT NULL,
`tag_preference_value` varchar(100) NOT NULL,
`site_id` int(5) unsigned NOT NULL DEFAULT '1',
PRIMARY KEY (`tag_preference_id`),
KEY `site_id` (`site_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;

CREATE TABLE IF NOT EXISTS `exp_tag_subscriptions` (
`tag_id` int(10) unsigned NOT NULL,
`member_id` int(10) unsigned NOT NULL,
`site_id` int(10) unsigned NOT NULL,
PRIMARY KEY (`tag_id`,`member_id`,`site_id`),
KEY `site_id` (`site_id`),
KEY `member_id` (`member_id`),
KEY `tag_id` (`tag_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci ;;