<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
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
 * Shortcut - Actions
 *
 * Handles All Form Submissions and Action Requests Used on both User and CP areas of EE
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/act.shortcut.php
 */

if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/extension_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/extension_builder.php';
}

class Shortcut_actions extends Addon_builder_bridge {
    
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 
	 * @access	public
	 * @return	null
	 */
    
	function Shortcut_actions()
    {	
    	parent::Addon_builder_bridge('shortcut');
    	
    	/** -------------------------------------
		/**  Module Installed and What Version?
		/** -------------------------------------*/
			
		if ($this->database_version() == FALSE OR $this->version_compare($this->database_version(), '<', SHORTCUT_VERSION))
		{
			return;
		}
	}
	/* END */


}
/* END Shortcut_actions Class */


?>