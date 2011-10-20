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
 * Shortcut - User Side
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/mod.shortcut.php
 */
 
if ( ! defined('APP_VER')) define('APP_VER', '2.0'); // EE 2.0's Wizard doesn't like CONSTANTs

if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/module_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
}

class Shortcut extends Module_builder_bridge {

	var $return_data	= '';
	
	var $disabled		= FALSE;

    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
	 
	function Shortcut()
	{	
		parent::Module_builder_bridge('shortcut');
        
        /** -------------------------------------
		/**  Module Installed and Up to Date?
		/** -------------------------------------*/
		
		if ($this->database_version() == FALSE OR $this->version_compare($this->database_version(), '<', SHORTCUT_VERSION))
		{
			$this->disabled = TRUE;
			
			trigger_error($this->EE->lang->line('shortcut_module_disabled'), E_USER_NOTICE);
		}
	}
	/* END Shortcut() */
	
}
// END CLASS Shortcut