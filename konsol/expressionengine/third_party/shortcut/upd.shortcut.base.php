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
 * Shortcut - Updater
 *
 * In charge of the install, uninstall, and updating of the module
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/upd.shortcut.php
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

class Shortcut_updater_base extends Module_builder_bridge
{
    
    var $module_actions		= array();
    var $hooks				= array();
    
	// --------------------------------------------------------------------

	/**
	 * Contructor
	 
	 * @access	public
	 * @return	null
	 */
    
	function Shortcut_updater_base( )
    {
    	if ( isset($GLOBALS['CI']) && get_class($GLOBALS['CI']) == 'Wizard')
    	{
    		return;
    	}
    	
    	parent::Module_builder_bridge('shortcut');
    	
		/** --------------------------------------------
        /**  Module Actions
        /** --------------------------------------------*/
        
        $this->module_actions = array();
		
		/** --------------------------------------------
        /**  Extension Hooks
        /** --------------------------------------------*/
        
        $this->default_settings = array();
        
        $default = array(	'class'        => $this->extension_name,
							'settings'     => '', 								// NEVER!
							'priority'     => 2,
							'version'      => SHORTCUT_VERSION,
							'enabled'      => 'y'
							);
        
        $this->hooks = array(
			array_merge($default,
						array(	'method'		=> 'redirect_short_url',
								'hook'  		=> 'sessions_start')),
																		
			array_merge($default,
						array(	'method'		=> 'create_js_link',
								'hook'  		=> 'show_full_control_panel_end')),
																		
			array_merge($default,
						array(	'method'		=> 'ajax_request',
								'hook'  		=> 'sessions_end')),
								
			array_merge($default,
						array(	'method'		=> 'add_extra_header',
								'hook'  		=> 'show_full_control_panel_end',
								'priority'		=> 5)),
		);
    }
    /* END*/
	
	// --------------------------------------------------------------------

	/**
	 * Add prefs
	 *
	 * @access	public
	 * @return	array
	 */
    
    private function _add_prefs()
    {
    	foreach ( $this->data->get_sites() as $site_id => $name )
    	{
			foreach ( $this->data->default_prefs as $key => $val )
			{			
				ee()->db->query(
					ee()->db->insert_string(
						'exp_shortcut_preferences',
						array(
							'site_id'		=> $site_id,
							'pref_name'		=> $key,
							'pref_value'	=> $val
						)
					)
				);
			}
    	}

		return;
    }
    
    /*	End add prefs */
	
	// --------------------------------------------------------------------

	/**
	 * Module Installer
	 *
	 * @access	public
	 * @return	bool
	 */

    function install()
    {
        // Already installed, let's not install again.
        if ($this->database_version() !== FALSE)
        {
        	return FALSE;
        }
        
        /** --------------------------------------------
        /**  Our Default Install
        /** --------------------------------------------*/
        
        if ($this->default_module_install() == FALSE)
        {
        	return FALSE;
        }
        
        /** --------------------------------------------
        /**  Add prefs
        /** --------------------------------------------*/
        
        $this->_add_prefs();
		
		/** ----------------------------------------
		/**	 Give All Current Members a Shorten URL Quicklink
		/** ----------------------------------------*/
		
		if (APP_VER < 2.0)
		{
			ee()->db->query("UPDATE exp_members
						LEFT JOIN exp_member_groups ON exp_member_groups.group_id = exp_members.group_id
						SET quick_links = TRIM(CONCAT(quick_links, '".ee()->db->escape_str("\nShortcutURL|#|1")."'))
						WHERE exp_member_groups.can_access_cp = 'y'
						AND quick_links NOT LIKE '%".ee()->db->escape_str("ShortcutURL")."%'");
		}		
		
		/** --------------------------------------------
        /**  Module Install
        /** --------------------------------------------*/
        
        $sql[] = ee()->db->insert_string(
        	'exp_modules', array(
        		'module_name'		=> $this->class_name,
        		'module_version'	=> SHORTCUT_VERSION,
        		'has_cp_backend'	=> 'y'
			)
		);
		
        foreach ( $sql as $query )
        {
            ee()->db->query( $query );
        }
        
        return TRUE;
    }
	/* END install() */
    
	// --------------------------------------------------------------------

	/**
	 * Module Uninstaller
	 
	 * @access	public
	 * @return	bool
	 */

    function uninstall()
    {
        // Cannot uninstall what does not exist, right?
        if ($this->database_version() === FALSE)
        {
        	return FALSE;
        }
		
		/** ----------------------------------------
		/**	 Remove All Current Members Shorten URL Quicklink
		/** ----------------------------------------*/
		
		$sql	= "SELECT member_id, quick_links FROM exp_members WHERE quick_links LIKE '%ShortcutURL%'";
		
		$query	= ee()->db->query( $sql );
		
		foreach ( $query->result_array() as $row )
		{
			ee()->db->query(
				ee()->db->update_string(
					'exp_members',
					array(
						'quick_links'	=> preg_replace( '/\nShortcutURL\|\#\|\d+/s', '', $row['quick_links'] )
					),
					array(
						'member_id'	=> $row['member_id']
					)
				)
			);
		}
		
		ee()->db->query("UPDATE exp_members
					LEFT JOIN exp_member_groups ON exp_member_groups.group_id = exp_members.group_id
					SET quick_links = TRIM(CONCAT(quick_links, '".ee()->db->escape_str("\nShortcutURL|#|1")."'))
					WHERE exp_member_groups.can_access_cp = 'y'
					AND quick_links LIKE '%".ee()->db->escape_str("ShortcutURL")."%'");
        
		/** --------------------------------------------
        /**  Default Module Uninstall
        /** --------------------------------------------*/
        
        if ($this->default_module_uninstall() == FALSE)
        {
        	return FALSE;
        }
        
        return TRUE;
    }
    /* END */


	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 
	 * @access	public
	 * @return	bool
	 */
    
    function update()
    {
    	/** --------------------------------------------
        /**  ExpressionEngine 2.x attempts to do automatic updates.  
        /**		- Mitchell questioned clients/customers and discovered that the majority preferred to update
        /**		themselves, especially on higher traffic sites. So, we forbid EE 2.x from doing updates
        /**		unless it comes through our update form.
        /** --------------------------------------------*/
        
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		return FALSE;
    	}
    	
    	/** --------------------------------------------
        /**  Default Module Update
        /** --------------------------------------------*/
    
    	$this->default_module_update();
    	
    	$this->actions();
    	
    	/** --------------------------------------------
        /**  Database Change
        /**  - Added: 1.0.0.b3
        /** --------------------------------------------*/
        
        if ($this->version_compare($this->database_version(), '<', '1.0.0.b3'))
        {
        	$sql	= "CREATE TABLE IF NOT EXISTS exp_shortcut_hits (
				`hit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`shortcut_id` int(10) unsigned NOT NULL,
				`hits` int(11) unsigned NOT NULL default '0',
				PRIMARY KEY (hit_id),
				KEY `shortcut_id` (shortcut_id)        		
        	)";
        	
        	ee()->db->query( $sql );
        	
        	unset( $sql );
        }
        
        /** --------------------------------------------
        /**  Version Number Update - LAST!
        /** --------------------------------------------*/
    	
    	ee()->db->query(ee()->db->update_string(	'exp_modules',
															array('module_version'	=> SHORTCUT_VERSION), 
															array('module_name'		=> $this->class_name)));
    									
    									
    	return TRUE;
    }
    /* END update() */
    


}
/* END Class */
?>