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
 * Shortcut - Control Panel
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/mcp.shortcut.php
 */

if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/module_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
}

class Shortcut_cp_base extends Module_builder_bridge
{
	var $per_page	= 50;

    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	bool		Enable calling of methods based on URI string
	 * @return	string
	 */
    
	function Shortcut_cp_base( $switch = TRUE )
    {
    	//$this->theme = 'flow_ui';
     
     	parent::Module_builder_bridge('shortcut');
        
         if ((bool) $switch === FALSE) return; // Install or Uninstall Request
        
		/** --------------------------------------------
        /**  Module Menu Items
        /** --------------------------------------------*/
        
        $menu	= array(
        	'module_shortcuts'	=> array(
				'link'  => $this->base . AMP . 'method=shortcuts',
				'title' => ee()->lang->line('shortcuts')
			),
        	'module_preferences'	=> array(
				'link'  => $this->base . AMP . 'method=preferences',
				'title' => ee()->lang->line('preferences')
			),
			'module_documentation'	=> array(
				'link'  => SHORTCUT_DOCS_URL,
				'title' => ee()->lang->line('online_documentation')  . ((APP_VER < 2.0) ? ' (' . SHORTCUT_VERSION . ')' : '')
			),
		);

		$this->cached_vars['lang_module_version'] 	= ee()->lang->line('shortcut_module_version');        
		$this->cached_vars['module_version'] 		= SHORTCUT_VERSION;        
        $this->cached_vars['module_menu_highlight'] = 'module_shortcuts';
        $this->cached_vars['module_menu'] 			= $menu;

        
		/** -------------------------------------
		/**  Module Installed and What Version?
		/** -------------------------------------*/
			
		if ($this->database_version() == FALSE)
		{
			return;
		}
		elseif($this->version_compare($this->database_version(), '<', SHORTCUT_VERSION))
		{
			if (APP_VER < 2.0)
			{
				if ($this->shortcut_module_update() === FALSE)
				{
					return;
				}
			}
			else
			{
				// For EE 2.x, we need to redirect the request to Update Routine
				$_GET['method'] = 'shortcut_module_update';
			}
		}
        
        /** -------------------------------------
		/**  Request and View Builder
		/** -------------------------------------*/
        
        if (APP_VER < 2.0 && $switch !== FALSE)
        {	
        	if (ee()->input->get('method') === FALSE)
        	{
        		$this->index();
        	}
        	elseif( ! method_exists($this, ee()->input->get('method')))
        	{
        		$this->add_crumb(ee()->lang->line('invalid_request'));
        		$this->cached_vars['error_message'] = ee()->lang->line('invalid_request');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        	else
        	{
        		$this->{ee()->input->get('method')}();
        	}
        }
    }
    /* END */
	
	// --------------------------------------------------------------------

	/**
	 * Module's Main Homepage
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	function index($message='')
    {
    	return $this->shortcuts();
	}
	/* END home() */
	
	// --------------------------------------------------------------------

	/**
	 * Preferences
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	function preferences( $message='' )
    {
		/** -------------------------------------
		/**	Get vars
		/** -------------------------------------*/
		
		$this->cached_vars['prefs']	= $this->data->get_preferences( ee()->config->item('site_id') );
        
		/** -------------------------------------
		/**	Are we updating / inserting?
		/** -------------------------------------*/
		
		if ( ee()->input->post('shortcut_prefix') !== FALSE )
		{
			/** -------------------------------------
			/**	Prep vars
			/** -------------------------------------*/
			
			foreach ( $this->cached_vars['prefs'] as $key => $val )
			{
				if ( ee()->input->post($key) !== FALSE )
				{
					if ( $key == 'shortcut_prefix' )
					{
						$this->cached_vars['prefs'][$key]	= urlencode( ee()->input->post($key) );
					}
					elseif ( $key == 'shortcut_base' AND ee()->input->post($key) != '' )
					{						
						$this->cached_vars['prefs'][$key]	= rtrim( ee()->input->post($key), '/' ) . '/';
					}
					else
					{
						$this->cached_vars['prefs'][$key]	= ee()->input->post($key);
					}
				}
			}
				
			/** -------------------------------------
			/**	Validate prefix
			/** -------------------------------------*/
			
			if ( $this->cached_vars['prefs']['shortcut_prefix'] == '' )
			{
				return ee()->output->show_user_error('submission', ee()->lang->line('prefix_required'));
			}
			
			/** -------------------------------------
			/**	Set prefs
			/** -------------------------------------*/
			
			$this->data->set_preferences( ee()->config->item('site_id'), $this->cached_vars['prefs'] );
			
			/** -------------------------------------
			/**	Redirect
			/** -------------------------------------*/
		
			ee()->functions->redirect( $this->cached_vars['base_uri'] . AMP . 'method=preferences' . AMP . 'msg=' . $message );
		}
        
		/** -------------------------------------
		/**	Prep message
		/** -------------------------------------*/
		
		$this->_prep_message( $message );
        
		/** -------------------------------------
		/**  Title and Crumbs
		/** -------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('preferences'));
		$this->build_crumbs();
		
		/** --------------------------------------------
        /**  Load Homepage
        /** --------------------------------------------*/
        
		$this->cached_vars['module_menu_highlight'] = 'module_preferences';
		return $this->ee_cp_view('preferences.html');
	}
	
	/* End preferences */
	
	// --------------------------------------------------------------------

	/**
	 * Prep message
	 
	 * @access	private
	 * @param	message
	 * @return	boolean
	 */
	
	function _prep_message( $message = '' )
	{
        if ( $message == '' AND isset( $_GET['msg'] ) )
        {
        	$message = ee()->lang->line( $_GET['msg'] );
        }
		
		$this->cached_vars['message']	= $message;
		
		return TRUE;
	}
	
	/*	End prep message */
	
	// --------------------------------------------------------------------

	/**
	 * Shortcuts
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	function shortcuts( $message='' )
    {
		/** -------------------------------------
		/**	Defaults
		/** -------------------------------------*/
		
		$this->cached_vars['shortcut']			= $this->data->get_next_shortcut();
		$this->cached_vars['shortcut_id']		= $this->data->cached['get_next_shortcut_id'];
		$this->cached_vars['custom_shortcut']	= '';
		$this->cached_vars['full_url']			= 'http://';
        
		/** -------------------------------------
		/**	Editing?
		/** -------------------------------------*/
		
		$this->cached_vars['edit_mode']	= 'n';
		
		if ( is_numeric( ee()->input->get('edit') ) === TRUE )
		{
			$this->cached_vars['edit_mode']	= 'y';
		
			$sql	= "SELECT * FROM exp_shortcut_shortcuts WHERE site_id = " . ee()->db->escape_str( ee()->config->item('site_id') ) . " AND shortcut_id = " . ee()->db->escape_str( ee()->input->get('edit') );
			
			$query	= ee()->db->query( $sql );
			
			foreach ( $query->row_array() as $key => $val )
			{
				if ( $key == 'autogenerated' AND $val == 'n' )
				{					
					$this->cached_vars['custom_shortcut']	= $query->row('shortcut');
				}
				
				$this->cached_vars[ $key ]	= $val;
			}
		}
        
		/** -------------------------------------
		/**	Deleting?
		/** -------------------------------------*/
		
		if ( is_numeric( ee()->input->get('delete') ) === TRUE )
		{
			$sql		= "DELETE FROM exp_shortcut_shortcuts WHERE shortcut_id = " . ee()->db->escape_str( ee()->input->get('delete') );
			
			$query		= ee()->db->query( $sql );
			
			$message	= ee()->lang->line('shortcut_deleted');
		}
        
		/** -------------------------------------
		/**	Are we updating / inserting?
		/** -------------------------------------*/
		
		if ( ee()->input->post('shortcut') !== FALSE )
		{
			$edit_mode			= is_numeric( ee()->input->post('edit') );
			$shortcut_id		= ee()->input->post('shortcut_id');
			$shortcut			= ee()->input->post('shortcut');
			$custom_shortcut	= '';
			$autogenerated		= 'y';
		
			/** -------------------------------------
			/**	Custom shortcut?
			/** -------------------------------------*/
			
			if ( ee()->input->post('custom_shortcut') !== FALSE AND ee()->input->post('custom_shortcut') != '' )
			{
				$custom_shortcut	= ee()->input->post('custom_shortcut');
				$autogenerated		= 'n';
				
				/** -------------------------------------
				/**	Shortcut exists?
				/** -------------------------------------*/
				
				if ( $edit_mode === FALSE AND $this->data->shortcut_exists( $custom_shortcut ) == 'y' )
				{
					return ee()->output->show_user_error('submission', ee()->lang->line('shortcut_exists'));
				}
				
				/** -------------------------------------
				/**	Validate shortcut
				/** -------------------------------------*/
				
				if ( strpos( urlencode( $custom_shortcut ), '%' ) !== FALSE )
				{
					return ee()->output->show_user_error('submission', ee()->lang->line('shortcut_invalid'));
				}
				
				/** -------------------------------------
				/**	Shortcut missing prefix?
				/** -------------------------------------*/
				
				if ( strpos( $custom_shortcut, $this->data->get_preference( ee()->config->item('site_id'), 'shortcut_prefix' ) ) !== 0 )
				{
					return ee()->output->show_user_error( 'submission', str_replace( '%prefix%', $this->data->get_preference( ee()->config->item('site_id'), 'shortcut_prefix' ), ee()->lang->line('shortcut_missing_prefix') ) );
				}
			}
				
			/** -------------------------------------
			/**	Validate URL
			/** -------------------------------------*/
			
			if ( preg_match( '/[http(s)?|localhost]:\/\/[a-z0-9\-]+\.[a-z]{2,5}/i', ee()->input->post('full_url') ) == 0 )
			{
				return ee()->output->show_user_error('submission', ee()->lang->line('url_invalid'));
			}
			
			/** -------------------------------------
			/**	Save shortcut
			/** -------------------------------------*/
		
			$message	= str_replace( '%shortcut%', $this->data->set_shortcut( $shortcut_id, $shortcut, $custom_shortcut, ee()->input->post('full_url'), $autogenerated, $edit_mode ), ee()->lang->line('shortcut_saved') );
			
			/** -------------------------------------
			/**	Update shortcut
			/** -------------------------------------*/
		
			$this->cached_vars['shortcut']	= $this->data->get_next_shortcut();
			
			/** -------------------------------------
			/**	Redirect
			/** -------------------------------------*/
		
			ee()->functions->redirect( $this->cached_vars['base_uri'] . AMP . 'msg=' . $message );
		}
		
		/**	----------------------------------------
		/**	Pagination
		/**	----------------------------------------*/
		
		$sql	= "SELECT COUNT(*) AS count FROM exp_shortcut_shortcuts WHERE site_id = " . ee()->config->item('site_id') . " AND full_url != ''";
		
		$query	= ee()->db->query( $sql );
		
		$config['base_url']				= $this->cached_vars['base_uri'] . AMP . 'method=shortcuts';
		$config['total_rows']			= $query->row('count');
		$config['per_page']				= $this->per_page;
		$config['page_query_string']	= TRUE;
		$config['full_tag_open']		= '<p>';
		$config['full_tag_close']		= '</p>';
		
		ee()->load->library('pagination');
		ee()->pagination->initialize($config);
		
		$this->cached_vars['pagination']	= ee()->pagination->create_links();
		
		/** --------------------------------------------
        /**  Is there a shortcut base URL that overrides?
        /** --------------------------------------------*/
        
        $shortcut_base	= $this->data->get_preference( ee()->config->item('site_id'), 'shortcut_base' );
		
		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/
		
		$sql	= "SELECT * FROM exp_shortcut_shortcuts WHERE site_id = " . ee()->config->item('site_id');
		$sql	.= " AND full_url != ''";
		$sql	.= " ORDER BY entry_date DESC";
		$sql	.= " LIMIT " . $this->per_page;
		
		if ( is_numeric( ee()->input->get_post('per_page') ) === TRUE )
		{
			$sql	.= ", " . ee()->input->get_post('per_page');
		}
		
		$query	= ee()->db->query( $sql );
		
		$this->cached_vars['shortcuts']	= array();
		
		foreach ( $query->result_array() as $row )
		{
			$shortcut_msg	= ( $row['shortcut'] == '' ) ? 'null': $row['shortcut'];
			$row['shortcut_name']	= ( $row['shortcut'] == '' ) ? 'null': $row['shortcut'];
			$row['truncated_url']	= ( empty( $row['full_url'] ) ) ? 'null': substr( $row['full_url'], 0, 100 );
			
			$row['custom_shortcut']	= '';
			
			if ( $shortcut_base == '' )
			{
				$row['shortcut']		= ee()->functions->create_url( $row['shortcut'] );
			}
			else
			{
				$row['shortcut']		= $shortcut_base . $row['shortcut'];
			}
				
			if ( $row['autogenerated'] == 'n' )
			{
				$row['custom_shortcut']	= $row['shortcut'];
				$row['shortcut']	= '';
			}
			
			$row['msg']		= str_replace( '%shortcut_msg%', $shortcut_msg, ee()->lang->line('edit_this') );
			
			$row['hits']	= $this->data->get_hits( $row['shortcut_id'] );
		
			$this->cached_vars['shortcuts'][ $row['shortcut_id'] ]	= $row;
		}
        
		/** -------------------------------------
		/**	Prep message
		/** -------------------------------------*/
		
		$this->_prep_message( $message );
        
		/** -------------------------------------
		/**  Title and Crumbs
		/** -------------------------------------*/
		
		$this->add_crumb( ee()->lang->line('shortcuts') );
		$this->build_crumbs();
		
		/** --------------------------------------------
        /**  Load Homepage
        /** --------------------------------------------*/
        
		$this->cached_vars['module_menu_highlight'] = 'module_shortcuts';
		return $this->ee_cp_view('shortcuts.html');
	}
	
	/* End shortcuts */

	// --------------------------------------------------------------------

	/**
	 * Module Installation
	 *
	 * Due to the nature of the 1.x branch of ExpressionEngine, this function is always required.
	 * However, because of the large size of the module the actual code for installing, uninstalling,
	 * and upgrading is located in a separate file to make coding easier
	 *
	 * @access	public
	 * @return	bool
	 */

    function shortcut_module_install()
    {
       require_once $this->addon_path.'upd.shortcut.php';
    	
    	$U = new Shortcut_updater();
    	return $U->install();
    }
	/* END shortcut_module_install() */    
    
	// --------------------------------------------------------------------

	/**
	 * Module Uninstallation
	 *
	 * Due to the nature of the 1.x branch of ExpressionEngine, this function is always required.
	 * However, because of the large size of the module the actual code for installing, uninstalling,
	 * and upgrading is located in a separate file to make coding easier
	 *
	 * @access	public
	 * @return	bool
	 */

    function shortcut_module_deinstall()
    {
       require_once $this->addon_path.'upd.shortcut.php';
    	
    	$U = new Shortcut_updater();
    	return $U->uninstall();
    }
    /* END shortcut_module_deinstall() */


	// --------------------------------------------------------------------

	/**
	 * Module Upgrading
	 *
	 * This function is not required by the 1.x branch of ExpressionEngine by default.  However,
	 * as the install and deinstall ones are, we are just going to keep the habit and include it
	 * anyhow.
	 *		- Originally, the $current variable was going to be passed via parameter, but as there might
	 *		  be a further use for such a variable throughout the module at a later date we made it
	 *		  a class variable.
	 *		
	 *
	 * @access	public
	 * @return	bool
	 */
    
    function shortcut_module_update()
    {
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		$this->add_crumb(ee()->lang->line('update_shortcut'));
    		$this->build_crumbs();
			$this->cached_vars['form_url'] = $this->base.'&msg=update_successful';
			return $this->ee_cp_view('update_module.html');
		}
		
    	require_once 'upd.shortcut.base.php';
    	
    	$U = new Shortcut_updater_base();
    	if ($U->update() !== TRUE)
    	{
    		return $this->index(ee()->lang->line('update_failure'));
    	}
    	else
    	{
    		return $this->index(ee()->lang->line('update_successful'));
    	}
    }
    /* END shortcut_module_update() */

	// --------------------------------------------------------------------
	
}
// END CLASS Shortcut