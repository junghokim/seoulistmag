<?php if ( ! defined('EXT') ) exit('No direct script access allowed');
 
 /**
 * Solspace - Tag
 *
 * @package		Solspace:Tag
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2011, Solspace, Inc.
 * @link		http://solspace.com/docs/addon/c/Tag/
 * @version		3.0.5
 * @filesource 	./system/modules/tag/
 * 
 */
 
 /**
 * Tag Module Class - Install/Uninstall/Update class
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/modules/tag/upd.tag.php
 */

require_once 'upd.tag.base.php';

if (APP_VER < 2.0)
{
	eval('class Tag_updater extends Tag_updater_base { }');
}
else
{
	eval('class Tag_upd extends Tag_updater_base { }');
}

?>