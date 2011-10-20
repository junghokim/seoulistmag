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
 * Tag Module Class - Control Panel
 *
 * The handler class for all control panel requests
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/modules/tag/mcp.tag.php
 */
 
require_once 'mcp.tag.base.php';

if (APP_VER < 2.0)
{
	eval('class Tag_CP extends Tag_cp_base { }');
}
else
{
	eval('class Tag_mcp extends Tag_cp_base { }');
}
?>