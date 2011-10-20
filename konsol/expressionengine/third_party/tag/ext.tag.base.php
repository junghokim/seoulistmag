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
 * Main extension class for all functionality
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/modules/tag/ext.tag.base.php
 */
 
if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/module_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
}

class Tag_extension_base extends Module_builder_bridge
{
	var $name			= "Tag";
	var $version		= "";
	var $description	= "";
	var $settings_exist	= "n";
	var $docs_url		= "http://solspace.com/docs/";
	
	// --------------------------------------------------------------------

	/**
	 *	Constructor
	 *
	 *	@access		public
	 *	@param		array
	 *	@return		null
	 */
	 
    function Tag_extension_base( $settings = '' )
    {
    	/** --------------------------------------------
        /**  Required During 1.x to 2.x Upgrade
        /** --------------------------------------------*/
        
    	if (get_class($this) == 'Tag_submit')
        {
        	return;
        }
    
    	/** --------------------------------------------
        /**  Load Parent Constructor
        /** --------------------------------------------*/
        
        //$this->theme = 'flow_ui';
        
        parent::Module_builder_bridge();
        
        /** --------------------------------------------
        /**  Settings!
        /** --------------------------------------------*/
        
		$this->settings = $settings;
		
		/** --------------------------------------------
        /**  Set Required Extension Variables
        /** --------------------------------------------*/
        
        if ( is_object(ee()->lang))
        {
        	ee()->lang->loadfile('tag');
        
        	$this->name			= ee()->lang->line('tag_module_name');
        	$this->description	= ee()->lang->line('tag_module_description');
        }
        
        $this->docs_url		= TAG_DOCS_URL;
        $this->version		= TAG_VERSION;
	}
	
	/**	END constructor */
	
	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension enabled, they have to install the module.
	 *
	 * In EE 2.x, all Add-Ons are "packages", so they will be prompted to try and install the extension
	 * and module at the same time.  So, we only output a message for them in EE 1.x and in EE 2.x 
	 * we just ignore the request.
	 *
	 * @access	public
	 * @return	null
	 */
    
	function activate_extension()
    {
    	if (APP_VER < 2.0)
    	{
    		return ee()->output->show_user_error('general', str_replace('%url%', 
    															BASE.AMP.'C=modules',
    															ee()->lang->line('enable_module_to_enable_extension')));
		}
	}
	/* END activate_extension() */
	
	// --------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension disabled, they have to uninstall the module.
	 *
	 * In EE 2.x, all Add-Ons are "packages", so they will be prompted to try and install the extension
	 * and module at the same time.  So, we only output a message for them in EE 1.x and in EE 2.x 
	 * we just ignore the request.
	 *
	 * @access	public
	 * @return	null
	 */
    
	function disable_extension()
    {
    	if (APP_VER < 2.0)
    	{
    		return ee()->output->show_user_error('general', str_replace('%url%', 
    															BASE.AMP.'C=modules',
    															ee()->lang->line('disable_module_to_disable_extension')));
		}					
	}
	/* END disable_extension() */
	
	// --------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * A required method that we actually ignore because this extension is updated by its module
	 * and no other place.  We cannot redirect to the module upgrade script because we require a 
	 * confirmation dialog, whereas extensions were designed to update automatically as they will try
	 * to call the update script on both the User and CP side.
	 *
	 * @access	public
	 * @return	null
	 */
    
	function update_extension()
    {
    
	}
	/* END update_extension() */
	

    /** ----------------------------------------
    /**	Execute?
    /** ----------------------------------------*/

    function execute( $channel_id = '' )
	{
		/** ----------------------------------------
		/**	Branch for no Channel id
		/** ----------------------------------------*/
		
		if ( $channel_id == '' )
		{
			if ( ee()->input->get_post($this->sc->db->channel_id) !== FALSE )
			{
				$channel_id = ee()->input->get_post($this->sc->db->channel_id);
			}
			else
			{
				/** ----------------------------------------
				/**	Is there only one channel for this site?
				/** ----------------------------------------*/
				
				$query	= ee()->db->query( "SELECT {$this->sc->db->channel_id}
											FROM {$this->sc->db->channels} WHERE site_id = '".ee()->db->escape_str( ee()->config->item('site_id') )."'" );
				
				if ( $query->num_rows() == 1 )
				{
					$channel_id = $query->row($this->sc->db->channel_id);
				}
				else
				{
					return FALSE;
				}
			}
		}
	
		/** ----------------------------------------
		/**	Check group permission
		/** ----------------------------------------*/
		
		if ( isset($this->cache['publish_tabs_execute'][$channel_id]))
		{
			return $this->cache['publish_tabs_execute'][$channel_id];
		}
		
		/** ----------------------------------------
		/**	Check group permission
		/** ----------------------------------------*/
		
		if ( ee()->session->userdata('group_id') != '1' )
		{
			$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_module_member_groups mg 
									  LEFT JOIN exp_modules m ON m.module_id = mg.module_id 
									  WHERE m.module_name = 'Tag' 
									  AND mg.group_id = '".ee()->db->escape_str( ee()->session->userdata('group_id') )."'" );

			if ( $query->row('count') == 0 )
			{
				return $this->cache['publish_tabs_execute'][$channel_id] = FALSE;
			}
		}
		
		/** ----------------------------------------
		/**	 Get Preference Value
		/** ----------------------------------------*/
		
		$query	= ee()->db->query( "SELECT tag_preference_value FROM exp_tag_preferences
									WHERE tag_preference_name = '".ee()->db->escape_str($channel_id)."_publish_tab_label'
									AND tag_preference_value != '' LIMIT 1" );
									
		if ($query->num_rows() == 1)
		{
			return $this->cache['publish_tabs_execute'][$channel_id] = $query->row('tag_preference_value');
		}
		
		return $this->cache['publish_tabs_execute'][$channel_id] = FALSE;
	}
	
	/**	END execute */
	
	
	/** ----------------------------------------
	/**	Tag tab
	/** ----------------------------------------*/

	function tag_tab( $publish_tabs, $channel_id, $entry_id )
	{
		if ( is_array( ee()->extensions->last_call ) )
		{
			$publish_tabs = ee()->extensions->last_call;
		}
		
		if ( ($label = $this->execute($channel_id)) === FALSE )
		{
			return $publish_tabs;
		}
		
		/** ----------------------------------------
		/**	Add tag tab to tab array
		/** ----------------------------------------*/

		$publish_tabs['tag']	= htmlspecialchars($label);
		
		/** ----------------------------------------
		/**	Return tabs array
		/** ----------------------------------------*/

		return $publish_tabs;
	}
	
	/**	END tag tab */
	
	
	/** ----------------------------------------
	/**	Tag tab block
	/** ----------------------------------------*/

	function tag_tab_block( $channel_id )
	{
		$r = ( ee()->extensions->last_call !== FALSE ) ? ee()->extensions->last_call : '';
		
		if ( $this->execute($channel_id) === FALSE )
		{
			return $r;
		}
		
		/** --------------------------------------------
        /**  Begin Tag Processing
        /** --------------------------------------------*/
		
		ee()->lang->loadfile('tag');

		$this->actions()->db_charset_switch('UTF-8');
		
		$convert_ascii = (ee()->config->item('auto_convert_high_ascii') == 'y') ? TRUE : FALSE;

		/**	----------------------------------------
		/**	No entry id?
		/**	----------------------------------------*/

		$entry_id	= '';

		if ( ee()->input->get_post('entry_id') !== FALSE AND ctype_digit( ee()->input->get_post('entry_id') ) === TRUE )
		{
			$entry_id = ee()->input->get_post('entry_id');
		}

		switch ( $this->preference('separator') )
		{
			case 'comma'		: $delim = ", ";
				break;
			case 'space'		: $delim = " ";
				break;
			case 'semicolon'	: $delim = "; ";
				break;
			case 'colon'		: $delim = ": ";
				break;
			default				: $delim = "\n"; // Extra slash for JS
				break;
		}
		
		$this->cached_vars['delimiter'] = $delim;
		
		/** --------------------------------------------
        /**  Current Tags for Entry
        /** --------------------------------------------*/

		$query = ee()->db->query(  "SELECT t.tag_id, t.tag_name FROM exp_tag_tags t
									LEFT JOIN exp_tag_entries e ON t.tag_id = e.tag_id
									WHERE e.entry_id = '".ee()->db->escape_str($entry_id)."'
									AND type = 'channel'
									ORDER BY t.tag_name" );
		
		$this->cached_vars['existing_tags']		= array();
		$this->cached_vars['existing_tag_ids']	= array();

		foreach ( $query->result_array() as $row )
		{
			/**	----------------------------------------
			/*	We have a space so add quotes around it, if the delimiter is a space too
			/**	----------------------------------------*/

			if ($delim == " " AND strpos($row['tag_name'], ' ') !== FALSE)
			{
				$this->cached_vars['existing_tags'][] = '"'.$row['tag_name'].'"';
			}
			else
			{
				$this->cached_vars['existing_tags'][] = $row['tag_name'];
			}
			
			$this->cached_vars['existing_tag_ids'][] = $row['tag_id'];
		}

		if ( ee()->input->get_post('tag_f') !== FALSE && ee()->input->get_post('tag_f') != '')
		{
			$this->cached_vars['existing_tags'] = explode($delim, ee()->input->get_post('tag_f'));
		}
		
		/** --------------------------------------------
        /**  Most Popular Tags
        /** --------------------------------------------*/
        
		$this->cached_vars['popular_tags'] = array();
        
        if ( ! empty($this->cached_vars['existing_tag_ids']))
        {
        	$query = ee()->db->query("SELECT tag_name, total_entries FROM exp_tag_tags
									  WHERE tag_id NOT IN (".implode(',', $this->cached_vars['existing_tag_ids']) .") 
									  ORDER BY total_entries DESC, tag_name ASC LIMIT 50");
        }
        else
        {
			$query = ee()->db->query("SELECT tag_name, total_entries FROM exp_tag_tags
									  ORDER BY total_entries DESC, tag_name ASC LIMIT 50");
		}
		
		if ($query->num_rows() > 0)
		{
			$this->cached_vars['popular_tags'] = $query->result_array();
		}
		
		/**	----------------------------------------
		/**	 Add Tag JS and CSS to CP Headers
		/**	----------------------------------------*/

		ee()->cp->extra_header .= "\n".'<script type="text/javascript" src="'.$this->base."&method=tag_publish_javascript&ajax=solspace_tag_module&".$this->sc->db->channel_id.'='.$channel_id.'"></script>';
		ee()->cp->extra_header .= "\n".'<link rel="stylesheet" type="text/css" href="'.$this->base.'&method=tag_publish_css&ajax=solspace_tag_module"; media="screen"/>';

		/**	----------------------------------------
		/**	 Search All Sites
		/**	----------------------------------------*/

		if (ee()->config->item('multiple_sites_enabled') == 'y')
		{
			$domain = ( ! ee()->config->item('cookie_domain')) ? ''     : ee()->config->item('cookie_domain');
			
			$this->cached_vars['cookie_prefix'] = ( ! ee()->config->item('cookie_prefix')) ? 'exp_' : ee()->config->item('cookie_prefix').'_';
			$this->cached_vars['cookie_path']   = ( ! ee()->config->item('cookie_path'))   ? '/'    : ee()->config->item('cookie_path');
			$this->cached_vars['cookie_domain'] = ($domain == '') ? '' : 'domain='.$domain.';';
		}

		/**	----------------------------------------
		/**	Begin tag field
		/**	----------------------------------------*/
		
		$this->cached_vars['tag_field_instructions'] = ee()->lang->line('tag_field_instructions_'.$this->preference('separator'));

		/**	----------------------------------------
		/**	Return block
		/**	----------------------------------------*/
		
		//exit($this->view('publish_tab_block.html', array(), TRUE));

		return $r.$this->view('publish_tab_block.html', array(), TRUE);
	}
	
	/**	END tag tab block */
	
	
	/** ----------------------------------------
	/**	Gallery block
	/** ----------------------------------------*/

	function gallery_block( $entry_id, $r )
	{
		ee()->extensions->end_script = FALSE;
							
		return $this->_gallery_block( $entry_id );
	}
	/**	END gallery block */
	
	
	/** ----------------------------------------
	/**	Gallery extended block
	/** ----------------------------------------*/

	function gallery_extended_block( $r )
	{
		ee()->extensions->end_script = FALSE;
	
		/** ----------------------------------------
		/**	Execute? 
		/** ----------------------------------------
		/*	If we already have a method to extend the fields for the native photo gallery
		/*	module, we shouldn't execute this. Because GX2 builds upon what the native
		/*	module does and the tag field will already be present.
		/** ----------------------------------------*/
		
		$query	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_extensions 
								   WHERE class = '".ee()->db->escape_str($this->extension_name)."' 
								   AND method = 'gallery_block' 
								   AND enabled = 'y'" );
		
		if ( $query->row('count') > 0 )
		{
			return;
		}
		
		/** ----------------------------------------
		/**	 Run
		/** ----------------------------------------*/
							
		return $this->_gallery_block( '' );
	}
	/**	END gallery extended block */

    /**	----------------------------------------
    /**	Gallery block
    /**	----------------------------------------
    /*	This method is called by the gallery
    /*	module and modifies the CP.
    /**	----------------------------------------*/

    function _gallery_block( $entry_id )
    {
		/**	----------------------------------------
		/**	Try to get entry id if empty
		/**	----------------------------------------*/

		if ( $entry_id == '' )
		{
			$entry_id	= ee()->security->xss_clean( ee()->input->get_post('entry_id') );
		}
		
		if ( empty ($entry_id))
		{
			return $this->view('gallery_block_row.html', array('existing_tags' => ''), TRUE);
		}

		/** --------------------------------------------
        /**  Fetch Tag Separator
        /** --------------------------------------------*/

		switch ( $this->preference('separator') )
		{
			case 'comma'		: $delim = ", ";
				break;
			case 'space'		: $delim = " ";
				break;
			case 'semicolon'	: $delim = "; ";
				break;
			case 'colon'		: $delim = ": ";
				break;
			default				: $delim = "\n";
				break;
		}
		
		/**	----------------------------------------
		/**	Query for tags
		/**	----------------------------------------*/

		$query = ee()->db->query( "SELECT t.tag_id, t.tag_name
								   FROM exp_tag_tags t
								   LEFT JOIN exp_tag_entries e ON t.tag_id = e.tag_id
								   WHERE e.site_id = '".ee()->db->escape_str( ee()->config->item('site_id') )."'
								   AND e.entry_id = '".ee()->db->escape_str($entry_id)."'
								   AND e.type = 'gallery'
								   ORDER BY t.tag_name" );
		
		$this->cached_vars['delimiter'] = $delim;

		$tags = '';
		$temp = array();

		foreach ( $query->result_array() as $row )
		{
			$pos = strpos($row['tag_name'], ' '); // Look for a space in the tag

			/**	----------------------------------------
			/*	We have a space so add quotes around it
			/*	if the delimiter is a space too
			/**	----------------------------------------*/

			if ($delim == " " AND $pos !== FALSE)
			{
				$qtag = '"'.$row['tag_name'].'"';
				array_push($temp, $qtag);
			}
			else
			{
				array_push($temp, $row['tag_name']);
			}
		}

		/**	----------------------------------------
		/**	Join the temp array
		/**	----------------------------------------*/

		$this->cached_vars['existing_tags'] = join($delim, $temp);

		/**	----------------------------------------
		/**	Prep row
		/**	----------------------------------------*/

    	return $this->view('gallery_block_row.html', array(), TRUE);
    }

    /**	END gallery block */

	// --------------------------------------------------------------------

	/**
	 *	Parse Channel/Weblog Entry Submissions
	 *
	 *	@access		public
	 *	@param		integer
	 *	@param		array
	 *	@param		string
	 *	@return		string
	 */

 	public function parse( $entry_id, $data, $ping_message )
	{
		ee()->extensions->end_script = FALSE;
		
		//we dont want this running in the CP because the tab already does this work in 2.x
		if (APP_VER >= 2.0 AND REQ == 'CP') 
		{			
			return (ee()->extensions->last_call !== FALSE) ? ee()->extensions->last_call : '';
		}

		//in 2.x, the second argument is meta info and the third argument is data
		//so we will just merge those arrays with the data taking precedence.
		if (APP_VER >= 2.0) 
		{
			$data = array_merge($data, $ping_message);
		}
				
		/** ----------------------------------------
		/**	Instantiate class
		/** ----------------------------------------*/
		
		require_once $this->addon_path.'mod.tag'.EXT;
		
		$TAG = new Tag();
							
		$x = $TAG->_parse_from_cp( $entry_id, $data );
		
		$this->actions()->db_charset_switch('default');
		
		return $x;
	}
	/* END parse() */
		
	
    /** ----------------------------------------
    /**	Ajax
    /** ----------------------------------------*/

    function ajax( $incoming )
	{
		if (ee()->extensions->last_call !== FALSE)
		{
			$incoming = ee()->extensions->last_call;
		}
		
		if ( ee()->input->get('ajax') === FALSE OR ee()->input->get('ajax') != 'solspace_tag_module')
		{
			return $incoming;
		}
		
		ee()->extensions->end_script = TRUE;
		
		/** ----------------------------------------
		/**	Instantiate class
		/** ----------------------------------------*/
		
		if ( class_exists('Tag_cp_base') === FALSE )
		{
			require $this->addon_path.'mcp.tag.base'.EXT;
		}
				
		$Tag_CP = new Tag_cp_base();
							
		return $Tag_CP->ajax();
	}
	
	/**	END ajax */
	
	
    /** ----------------------------------------
    /**	Gallery parse
    /** ----------------------------------------*/
    
    function gallery_parse_add( $entry_id = '' ) { $this->gallery_parse( $entry_id ); }
    
    function gallery_parse_edit( $entry_id = '' ) { $this->gallery_parse( $entry_id ); }

    function gallery_parse( $entry_id = '' )
	{
		ee()->extensions->end_script = FALSE;
		
		if ( $entry_id == '' ) return; 
		
		/** ----------------------------------------
		/**	Instantiate class
		/** ----------------------------------------*/
		
		require_once $this->addon_path.'mod.tag'.EXT;
		
		$TAG = new Tag();
							
		$TAG->_parse_from_gallery_cp( $entry_id );
	}
	
	/**	END gallery parse */
	
	
    /** ----------------------------------------
    /**	Gallery extended parse
    /** ----------------------------------------*/

    function gallery_extended_parse( $entry_id )
	{
		ee()->extensions->end_script	= FALSE;
		
		/** ----------------------------------------
		/**	Instantiate class
		/** ----------------------------------------*/
		
		require_once $this->addon_path.'mod.tag'.EXT;
		
		$TAG = new Tag();
							
		return $TAG->_parse_from_gallery_extended_cp( $entry_id );
	}
	
	/**	END gallery extended parse */
	
	
    /** ----------------------------------------
    /**	Delete
    /** ----------------------------------------*/

    function delete()
	{
		if ( ! isset($_POST['delete']) OR ! is_array($_POST['delete'])) return;

		require_once $this->addon_path.'mod.tag'.EXT;
		
		$TAG = new Tag();
							
		return $TAG->delete( $_POST['delete'] );
	}
	
	/*	END delete */
}

/**	END class */
?>