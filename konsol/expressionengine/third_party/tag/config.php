<?php if ( ! defined('EXT')) exit('No direct script access allowed');
/**
 * Solspace - Tag
 *
 * @package		Solspace:Tag
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2011, Solspace, Inc.
 * @link		http://solspace.com/docs/addon/c/Tag/
 * @version		3.0.5
 * @filesource 	./system/modules/tag/
 */

 /**
 * Tag - Config
 *
 * NSM Addon Updater Config File
 *
 * @package 	Solspace:Tag
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/tag/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (APP_VER >= 2.0)
{
	require_once PATH_THIRD . '/tag/constants.tag.php';

	$config['name']    								= 'Tag';
	$config['version'] 								= TAG_VERSION;
	$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/tag';
}