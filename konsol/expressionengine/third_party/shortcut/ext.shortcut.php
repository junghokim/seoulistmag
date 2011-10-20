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
 * Shortcut Module Class - Extension Class
 *
 * If you don't know what an extension is, I am not going to tell you...loser...
 *
 * @package 	Solspace:Shortcut module
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/ext.shortcut.php
 */
 
require_once 'ext.shortcut.base.php';

if (APP_VER < 2.0)
{
	eval('class Shortcut_extension extends Shortcut_extension_base { }');
}
else
{
	eval('class Shortcut_ext extends Shortcut_extension_base { }');
}
?>