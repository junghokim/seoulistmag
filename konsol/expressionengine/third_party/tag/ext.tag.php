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
 * Tag Module Class - Extension Class
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/modules/tag/ext.tag.php
 */
 
require_once 'ext.tag.base.php';

if (APP_VER < 2.0)
{
	eval('class Tag_extension extends Tag_extension_base { }');
}
else
{
	eval('class Tag_ext extends Tag_extension_base { }');
}
?>