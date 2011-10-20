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
 * Shortcut Module Class - Control Panel
 *
 * The handler class for all control panel requests
 *
 * @package 	Solspace:Shortcut module
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/mcp.shortcut.php
 */
 
require_once 'mcp.shortcut.base.php';

if (APP_VER < 2.0)
{
	eval('class Shortcut_CP extends Shortcut_cp_base { }');
}
else
{
	eval('class Shortcut_mcp extends Shortcut_cp_base { }');
}
?>