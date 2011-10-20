<?php if ( ! defined('EXT') ) exit('No direct script access allowed');
 
 /**
 * Solspace - Shortcut
 *
 * @package		Solspace:Shortcut
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Shortcut/
 * @version		1.1.1
 * @filesource 	./system/modules/shortcut/
 * 
 */
 
 /**
 * Shortcut Module Class - Install/Uninstall/Update class
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/upd.shortcut.php
 */

require_once 'upd.shortcut.base.php';

if (APP_VER < 2.0)
{
	eval('class Shortcut_updater extends Shortcut_updater_base { }');
}
else
{
	eval('class Shortcut_upd extends Shortcut_updater_base { }');
}

?>