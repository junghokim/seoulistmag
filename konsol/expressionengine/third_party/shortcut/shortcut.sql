CREATE TABLE IF NOT EXISTS `exp_shortcut_preferences` (
`pref_id` int(10) unsigned NOT NULL auto_increment,
`site_id` smallint(3) unsigned NOT NULL default '1',
`pref_name` varchar(132) NOT NULL,
`pref_value` varchar(255) NOT NULL,
PRIMARY KEY (pref_id),
KEY `site_id` (site_id)
) ;;

CREATE TABLE IF NOT EXISTS `exp_shortcut_shortcuts` (
`shortcut_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`site_id` smallint(3) unsigned NOT NULL default '1',
`autogenerated` char(1) NOT NULL default 'y',
`shortcut` varchar(100) NOT NULL,
`full_url` varchar(255) NOT NULL,
`entry_date` int(11) unsigned NOT NULL,
`edit_date` int(11) unsigned NOT NULL,
PRIMARY KEY (`shortcut_id`),
INDEX (`shortcut`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_shortcut_hits` (
`hit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`shortcut_id` int(10) unsigned NOT NULL,
`hits` int(11) unsigned NOT NULL default '0',
PRIMARY KEY (hit_id),
KEY `shortcut_id` (shortcut_id)
) ;;