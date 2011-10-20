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

if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/module_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
}

class Tag_cp_base extends Module_builder_bridge
{	
	private $row_limit		= 50;
	
    private $member_id		= 0;
    private $entry_id		= '';
    private $pref_id		= '';
    private $tag_id			= '';
    
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function __construct( $switch = TRUE )
    {
    	//$this->theme = 'flow_ui';
    
		parent::Module_builder_bridge('tag');
        
        if ((bool) $switch === FALSE) return; // Install or Uninstall Request
        
		/**	----------------------------------------
		/**	 UTF-8
		/**	----------------------------------------*/
		
		$this->_db_charset_switch('utf-8');
		
		if (function_exists ( 'mb_internal_encoding'))
		{
			mb_internal_encoding('UTF-8');
		}
    	
		/** --------------------------------------------
        /**  Module Menu Items
        /** --------------------------------------------*/
        
        $menu	= array(
			'module_manage_tags'		=> array(	
				'link'  => $this->base,
				'title' => ee()->lang->line('manage_tags')
			),
			'module_manage_bad_tags'	=> array(	
				'link'  => $this->base.'&method=manage_bad_tags',
				'title' => ee()->lang->line('manage_bad_tags')
			),
			'module_harvest'			=> array(	
				'link'  => $this->base.'&method=harvest',
				'title' => ee()->lang->line('tag_harvest')
			),
			'module_preferences'		=> array(	
				'link'  => $this->base.'&method=preferences',
				'title' => ee()->lang->line('tag_preferences')
			),
			'module_documentation'		=> array(	
				'link'  => TAG_DOCS_URL,
				'title' => ee()->lang->line('online_documentation') . ((APP_VER < 2.0) ? ' (' . TAG_VERSION . ')' : '')
			),
        );

		$this->cached_vars['lang_module_version'] 	= ee()->lang->line('tag_module_version');        
		$this->cached_vars['module_version'] 		= TAG_VERSION;        
        $this->cached_vars['module_menu_highlight'] = 'module_manage_tags';
        $this->cached_vars['module_menu'] 			= $menu;
        
		//needed for header.html file views
		$this->cached_vars['js_magic_checkboxes']	= $this->js_magic_checkboxes();

		if (isset(ee()->cp) AND file_exists($this->view_path.'/'.$this->lower_name.'.css'))
		{
			ee()->cp->add_to_head('<style type="text/css" media="screen">'.file_get_contents($this->view_path.'/'.$this->lower_name.'.css').'</style>');
		}
		
		/** --------------------------------------------
        /**  Sites
        /** --------------------------------------------*/
        
        $this->cached_vars['sites']	= array();
        
        foreach($this->data->get_sites() as $site_id => $site_label)
        {
        	$this->cached_vars['sites'][$site_id] = $site_label;
        }
			
		/** -------------------------------------
		/**  Module Installed and What Version?
		/** -------------------------------------*/
			
		if ($this->database_version() == FALSE)
		{
			return;
		}
		elseif($this->version_compare($this->database_version(), '<', TAG_VERSION) OR 
			   ! $this->extensions_enabled())
		{
			if (APP_VER < 2.0)
			{
				if ($this->tag_module_update() === FALSE)
				{
					return;
				}
			}
			else
			{
				// For EE 2.x, we need to redirect the request to Update Routine
				$_GET['method'] = 'tag_module_update';
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
        
		$this->_db_charset_switch('default');
    }
    
	/* END __construct() */
	
	// --------------------------------------------------------------------

	/**
	 * Destructor
	 *
	 * @access	public
	 * @return	null
	 */	public function __destruct( )
    {
    	$this->_db_charset_switch('default');
    }
    /* END __destruct() */
    

	// --------------------------------------------------------------------

	/**
	 *	The Main CP Index Page
	 *
	 *	@access		public
	 *	@param		string		$message - That little message display thingy
	 *	@return		string
	 */	
	public function index($message = '')
    {
    	if ($message == '' && ee()->input->get_post('msg') !== FALSE)
    	{
    		$message = ee()->lang->line(ee()->input->get_post('msg'));
    	}

    	/**	----------------------------------------
		/**  Queries for Weblog Entries Tagged
		/**	----------------------------------------*/
		
		$this->cached_vars['percent_weblog_entries_tagged'] = 0;
		
		$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_tag_tags 
								  WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'" ); 
		$query_row = $query->row_array();
		
		$this->cached_vars['total_tags'] = $query_row['count'];
		
		$query = ee()->db->query("SELECT COUNT(DISTINCT entry_id) AS count FROM exp_tag_entries
								  WHERE type = 'channel' AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'" ); 
		$query_row = $query->row_array();

		$this->cached_vars['total_weblog_entries_tagged'] = ($query->num_rows() == 0) ? 0 : $query_row['count'];
		
		$query = ee()->db->query("SELECT COUNT(*) AS count FROM {$this->sc->db->channel_titles}
								  WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'" ); 
		$query_row = $query->row_array();
		
		if ( $query_row['count'] != 0 )
		{
			$this->cached_vars['percent_weblog_entries_tagged'] = round( $this->cached_vars['total_weblog_entries_tagged'] / $query_row['count'] * 100, 2);
		}
					  
		/**	----------------------------------------
		/**	 Queries for Gallery Entries Tagged
		/**	----------------------------------------*/
		
		$this->cached_vars['total_gallery_entries_tagged']		= 0;
		$this->cached_vars['percent_gallery_entries_tagged']	= 0;
		
		if ( ee()->db->table_exists('exp_gallery_entries') )
		{
			$query	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_tag_entries 
											WHERE type = 'gallery' AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
											GROUP BY entry_id" ); $query_row = $query->row_array();
	
			$this->cached_vars['total_gallery_entries_tagged'] = ($query->num_rows() == 0) ? 0 : $query_row['count'];
			
			$query = ee()->db->query( "SELECT COUNT(*) AS count FROM exp_gallery_entries" ); $query_row = $query->row_array();
			
			if ( $query_row['count'] != 0 )
			{
				$this->cached_vars['percent_gallery_entries_tagged'] = round( $this->cached_vars['total_gallery_entries_tagged'] / $query_row['count'] * 100, 2);
			}
		}
		
		/** --------------------------------------------
        /**  Top 5 Tags
        /** --------------------------------------------*/
		
		$top5	= ee()->db->query("SELECT t.tag_name FROM exp_tag_tags t 
										WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
										ORDER BY t.total_entries DESC LIMIT 5" );

		$this->cached_vars['top_five_tags'] = array();

		foreach ( $top5->result_array() as $row )
		{
			$this->cached_vars['top_five_tags'][] = $row['tag_name'];
		}
	
		/**	----------------------------------------
		/**	Browse by First Character
		/**	----------------------------------------*/
		
		$query = ee()->db->query("SELECT tag_alpha, COUNT(tag_alpha) AS count 
									   FROM exp_tag_tags WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
									   GROUP BY tag_alpha" );
		
		$this->cached_vars['tags_by_alpha'] = array();
		
		if ( $query->num_rows() > 0 )
		{		
			foreach ( $query->result_array() as $row )
			{
				$this->cached_vars['tags_by_alpha'][$row['tag_alpha']]= $row['count'];
			}
		}
		
		/** --------------------------------------------
        /**  Tags Display
        /** --------------------------------------------*/
    
    	$this->_tags();
    
    	/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('manage_tags'));
		
		$this->cached_vars['message'] 			= $message;
		$this->cached_vars['lang_search_tags'] 	= ee()->lang->line('search_tags');
		$this->cached_vars['lang_all_tags'] 	= ee()->lang->line('all_tags');
		
		return $this->ee_cp_view('index.html');
	}
    
	/* END index() */
    
    
	/**	----------------------------------------
    /**	Tags
	/**	---------------------------------------*/
	
	public function _tags()
    {
    	$paginate		= '';
        $row_count		= 0;
					  
		/**	----------------------------------------
		/**	Bad tags array
		/**	----------------------------------------*/
        
        $this->cached_vars['bad_tags'] = array();
		
		$badq = ee()->db->query("SELECT tag_name FROM exp_tag_bad_tags 
							  	 WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'");
		
		foreach( $badq->result_array() as $b )
		{
			$this->cached_vars['bad_tags'][] = $b['tag_name'];
		}
		
		/** --------------------------------------------
        /**  Build Our Tags Query
        /** --------------------------------------------*/
		
		$sql	= " FROM exp_tag_tags t 
					LEFT JOIN exp_members m ON m.member_id = t.author_id 
					WHERE t.tag_name != '' 
					AND t.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'";
					
		
		if ( ee()->input->get_post('alpha') !== FALSE)
		{
			//$_POST['tag_search_keywords'] = $this->_clean_str( ee()->input->get_post('alpha'));
			$sql .=	" AND t.tag_name REGEXP '^".ee()->db->escape_str($this->_clean_str( ee()->input->get_post('alpha'), TRUE ))."'";
		}
		
		if (ee()->input->get_post('tag_search_keywords') !== FALSE)
		{
			$sql .=	" AND t.tag_name LIKE '%".ee()->db->escape_str($this->_clean_str( ee()->input->get_post('tag_search_keywords'), TRUE ))."%'";
		}
		
		$query	= ee()->db->query("SELECT COUNT(*) AS count ".$sql);
		
		$sql	.= " ORDER BY t.tag_name ASC";
		
		/**	----------------------------------------
		/**	Paginate
		/**	----------------------------------------*/
		
		$sql = "SELECT t.tag_name, t.tag_id, t.entry_date, t.edit_date, m.member_id ".$sql;
		
		$this->cached_vars['paginate'] = '';
    
		if ( $query->row()->count > $this->row_limit )
		{
			$row_count		= ( ee()->input->get_post('row') === FALSE OR ee()->input->get_post('row') == '' ) ? 0 : ee()->input->get_post('row');
			
			$alpha			= ( ee()->input->get_post('alpha') === FALSE ) ? '': AMP.'alpha='.ee()->input->get_post('alpha');

			ee()->load->library('pagination');
		
			$config['base_url']				= $this->base.'&method=index'.$alpha;
			$config['total_rows']			= $query->row()->count;
			$config['per_page']				= $this->row_limit;
			$config['page_query_string']	= TRUE;
			$config['query_string_segment']	= 'row';
				
			ee()->pagination->initialize($config);
	
			$this->cached_vars['paginate'] = ee()->pagination->create_links();
			 
			$sql .= " LIMIT ".$row_count.", ".$this->row_limit;
		}
		
		$query = ee()->db->query($sql);
		
		$this->cached_vars['tags'] = array();
		
		$member_ids = $tag_ids = array();
		
		if ($query->num_rows() > 0)
		{
			foreach($query->result_array() AS $row)
			{
				$tag_ids[]						= $row['tag_id'];
				$member_ids[$row['member_id']]	= '--';
			}
			
			/**	----------------------------------------
			/**	 Fetch Tag Count Data
			/**	----------------------------------------*/
			
			$tag_counts = array();
			
			$cquery = ee()->db->query("SELECT tag_id, COUNT(*) AS count FROM exp_tag_entries 
										WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
										AND tag_id IN (".implode(',', $tag_ids).")
										GROUP BY tag_id" );
	
			foreach( $cquery->result_array() as $row )
			{
				$tag_counts[$row['tag_id']] = $row['count'];
			}
			
			/** --------------------------------------------
			/**  Fetch Screen Names
			/** --------------------------------------------*/
			
			$this->_db_charset_switch('default');
			
			$squery = ee()->db->query("SELECT screen_name, member_id FROM exp_members
											WHERE member_id IN ('".implode("','", array_keys($member_ids))."')");
			
			foreach($squery->result_array() as $row)
			{
				$member_ids[$row['member_id']] = $row['screen_name'];
			}
			
			foreach($query->result_array() as $key => $row)
			{
				if ( empty($row['edit_date']))
				{
					$row['edit_date'] = $row['entry_date'];
				}
			
				$this->cached_vars['tags'][$row['tag_id']] = $row;
				$this->cached_vars['tags'][$row['tag_id']]['weblog_entries_count'] = ( ! isset($tag_counts[$row['tag_id']])) ? 0 : $tag_counts[$row['tag_id']];
				$this->cached_vars['tags'][$row['tag_id']]['screen_name'] = $member_ids[$row['member_id']];
			}
			
			$this->_db_charset_switch('UTF-8');
		}
    }
    
	/* END _tags */
	
	
	// --------------------------------------------------------------------

	/**
	 *	Manage Tags Processing
	 *
	 *	Redirects to either a mass-edit, mass-delete, or a simple search
	 *
	 *	@access		public
	 *	@return		string
	 */	public function manage_tags_process()
	{
		if ( isset($_POST['delete_tag_button']))
		{
			return $this->delete_tag_confirm();
		}
		elseif(isset($_POST['search_tags_button']))
		{
			return $this->index();
		}
		else
		{
			return $this->index();
		}
	}
	/* END manage_tags_proces() */
	
    
	/**	----------------------------------------
    /**	Entries by tag
	/**	----------------------------------------*/
	
	public function weblog_entries_by_tag($message = '')
    {	
		/**	----------------------------------------
		/**	 Fetch Tag Name
		/**	----------------------------------------*/
		
		$query = ee()->db->query("SELECT tag_name FROM exp_tag_tags 
								   WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
								   AND tag_id = '".ee()->db->escape_str( ee()->input->get_post('tag_id') )."'" );
									   
		if ($query->num_rows() == 0)
		{
			$this->add_crumb(ee()->lang->line('invalid_request'));
			$this->cached_vars['error_message'] = ee()->lang->line('invalid_request');
			
			return $this->ee_cp_view('error_page.html');
		}
		
		$this->cached_vars['tag_name']	= trim($query->row()->tag_name, '"');
		$this->cached_vars['tag_id']	= ee()->input->get_post('tag_id');
		
		/**	----------------------------------------
		/**	Gallery entries bar
		/**	----------------------------------------*/
		
		$this->cached_vars['has_gallery_entries'] = 'no';
		
		if (APP_VER < 2.0)
		{
			$gquery	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_tag_entries 
									   WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
									   AND type = 'gallery' 
									   AND tag_id = '".ee()->db->escape_str( ee()->input->get_post('tag_id') )."'" );
			
			if ( $gquery->row()->count > 0 )
			{
				$this->cached_vars['has_gallery_entries'] = 'yes';
			}
		}
		
		/**	----------------------------------------
		/**	Fetch Entries
		/**	----------------------------------------*/
		
		$this->_entries(ee()->input->get_post('tag_id'));
		
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('weblog_entries_by_tag'));
		
		return $this->ee_cp_view('weblog_entries_by_tag.html');
	}
	/* END weblog_entries_by_tag() */
    
    
	/**	----------------------------------------
    /**	Entries
	/**	---------------------------------------*/
	
	public function _entries( $tag_id = '' )
    {
		$this->cached_vars['entries'] = array();
        
        $this->_db_charset_switch('default');

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/
		
		$select = "SELECT wt.title, wt.entry_date, wt.entry_id, wt.{$this->sc->db->channel_id}, wt.author_id, te.type ";
		
		$sql	= "FROM {$this->sc->db->channel_titles} AS wt
				   LEFT JOIN exp_tag_entries te ON wt.entry_id = te.entry_id
				   WHERE te.type = 'channel' 
				   AND te.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
				   AND te.tag_id = '".ee()->db->escape_str( $tag_id )."'";
		
		$sql	.= " ORDER BY wt.entry_date ASC";
		
		$query	= ee()->db->query("SELECT COUNT(*) AS count ".$sql);
		
		/**	----------------------------------------
		/**	Paginate
		/**	----------------------------------------*/
		
		$this->cached_vars['paginate']	= '';
    
		if ( $query->row()->count > $this->row_limit )
		{
			$row_count = ( ee()->input->get_post('row') === FALSE OR ee()->input->get_post('row') == '' ) ? 0 : ee()->input->get_post('row');

			ee()->load->library('pagination');
		
			$config['base_url']				= $this->base.'&method=weblog_entries_by_tag&tag_id='.$tag_id;
			$config['total_rows']			= $query->row()->count;
			$config['per_page']				= $this->row_limit;
			$config['page_query_string']	= TRUE;
			$config['query_string_segment']	= 'row';
				
			ee()->pagination->initialize($config);
	
			$this->cached_vars['paginate'] = ee()->pagination->create_links();
			 
			$sql .= " LIMIT ".$row_count.", ".$this->row_limit;
		}
		
		$query = ee()->db->query($select.$sql); 
		
		/** --------------------------------------------
        /**  Screen Names - Done separately because of DB character set
        /** --------------------------------------------*/
        
        $this->_db_charset_switch('default');
        
        $author_ids		= array();
        $screen_names	= array();
        
        foreach($query->result_array() as $row)
        {
        	$author_ids[] = $row['author_id'];
        }
        
        $mquery = ee()->db->query("SELECT screen_name, member_id FROM exp_members
        					  			WHERE member_id IN ('".implode("','", array_unique($author_ids))."')");
        					  
        foreach($mquery->result_array() as $row)
        {
        	$screen_name[$row['member_id']] = $row['screen_name'];
        }
        
        foreach($query->result_array() as $key => $row)
        {
        	$this->cached_vars['entries'][$row['entry_id']] = $row;
        	$this->cached_vars['entries'][$row['entry_id']]['screen_name'] = ( ! isset($screen_name[$row['author_id']])) ? '--' : $screen_name[$row['author_id']];
        }
        
        $this->_db_charset_switch('UTF-8');
    }
    
	/* END _entries */
    
    
	/**	----------------------------------------
    /**	Gallery entries by tag
	/**	---------------------------------------*/
	
	public function gallery_entries_by_tag()
    {
    	/**	----------------------------------------
		/**	 Fetch Tag Name
		/**	----------------------------------------*/
		
		$query	= ee()->db->query("SELECT tag_name FROM exp_tag_tags 
										WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
										AND tag_id = '".ee()->db->escape_str( ee()->input->get_post('tag_id') )."'" );
										
		if (ee()->db->table_exists('exp_gallery_entries') == FALSE OR $query->num_rows() == 0)
		{
			$this->add_crumb(ee()->lang->line('invalid_request'));
			$this->cached_vars['error_message'] = ee()->lang->line('invalid_request');
			
			return $this->ee_cp_view('error_page.html');
		}
										
		$this->cached_vars['tag_name']	= trim($query->row()->tag_name, '"');
		$this->cached_vars['tag_id']	= ee()->input->get_post('tag_id');
    	
		/**	----------------------------------------
		/**	Gallery entries bar
		/**	----------------------------------------*/
		
		$gquery	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_tag_entries 
									   WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
									   AND type = 'channel'
									   AND tag_id = '".ee()->db->escape_str( ee()->input->get_post('tag_id') )."'" );
		
		$this->cached_vars['has_weblog_entries'] = 'no';
		
		if ( $gquery->row()->count > 0 )
		{
			$this->cached_vars['has_weblog_entries'] = 'yes';
		}
        
		/**	----------------------------------------
		/**	Fetch Entries
		/**	----------------------------------------*/
		
		$this->_gallery_entries(ee()->input->get_post('tag_id'));
		
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('gallery_entries_by_tag'));
		
		return $this->ee_cp_view('gallery_entries_by_tag.html');
	}
	
	/* END gallery entries by tag */
    
    
	/**	----------------------------------------
    /**	Gallery entries
	/**	---------------------------------------*/
	
	public function _gallery_entries( $tag_id )
    {
        
        $this->cached_vars['entries'] = array();
		
		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/
		
		$this->_db_charset_switch('default');
		
		$select = "SELECT ge.title, ge.entry_date, ge.entry_id, ge.gallery_id, g.gallery_full_name, ge.author_id, te.type ";
		
		$sql	= "FROM exp_gallery_entries ge
				   LEFT JOIN exp_tag_entries te ON ge.entry_id = te.entry_id
				   LEFT JOIN exp_galleries g ON g.gallery_id = ge.gallery_id 
				   WHERE te.type = 'gallery' 
				   AND te.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
				   AND te.tag_id = '".ee()->db->escape_str( $tag_id )."'";
		
		$sql	.= " ORDER BY ge.entry_date ASC";
		
		$query	= ee()->db->query("SELECT COUNT(*) AS count ".$sql);
		
		/**	----------------------------------------
		/**	Paginate
		/**	----------------------------------------*/
		
		$this->cached_vars['paginate'] = '';
		
		if ( $query->row()->count > $this->row_limit )
		{
			$row_count = ( ee()->input->get_post('row') === FALSE OR ee()->input->get_post('row') == '' ) ? 0 : ee()->input->get_post('row');

			ee()->load->library('pagination');
		
			$config['base_url']				= $this->base.'&method=gallery_entries_by_tag&tag_id='.$tag_id;
			$config['total_rows']			= $query->row()->count;
			$config['per_page']				= $this->row_limit;
			$config['page_query_string']	= TRUE;
			$config['query_string_segment']	= 'row';
				
			ee()->pagination->initialize($config);
	
			$this->cached_vars['paginate'] = ee()->pagination->create_links();
			 
			$sql .= " LIMIT ".$row_count.", ".$this->row_limit;
		}
		
		$query = ee()->db->query($select.$sql);
		
		/** --------------------------------------------
        /**  Screen Names - Done separately because of DB character set
        /** --------------------------------------------*/
        
        $this->_db_charset_switch('default');
        
        $author_ids		= array();
        $screen_names	= array();
        
        foreach($query->result_array() as $row)
        {
        	$author_ids[] = $row['author_id'];
        }
        
        $mquery = ee()->db->query("SELECT screen_name, member_id FROM exp_members
        					  			WHERE member_id IN ('".implode("','", array_unique($author_ids))."')");
        					  
        foreach($mquery->result_array() as $row)
        {
        	$screen_name[$row['member_id']] = $row['screen_name'];
        }
        
        foreach($query->result_array() as $key => $row)
        {
        	$this->cached_vars['entries'][$row['entry_id']] = $row;
        	$this->cached_vars['entries'][$row['entry_id']]['screen_name'] = ( ! isset($screen_name[$row['author_id']])) ? '--' : $screen_name[$row['author_id']];
        }
        
        $this->_db_charset_switch('UTF-8');
    }
    
	/* END gallery entries */
    
    
	// --------------------------------------------------------------------

	/**
	 *	Edit Tag Form
	 *
	 *	Come on, doofus, if it ain't obvious from the name, then you seriously need to just go 
	 *	somewhere else and bug anyone you find there.
	 *
	 *	@access		public
	 *	@return		string
	 */	public function edit_tag_form()
    {
    	if ( ee()->input->get_post('tag_id') === FALSE OR ! is_numeric(ee()->input->get_post('tag_id')))
    	{
    		return FALSE;
    	}
					  
		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/
		
		$query = ee()->db->query( "SELECT * FROM exp_tag_tags 
										WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
										AND tag_id = '".ee()->db->escape_str( ee()->input->get_post('tag_id') )."' LIMIT 1");
										
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
			
		$this->cached_vars = array_merge($this->cached_vars, $query->row_array());
		
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('edit_tag'));
		
		return $this->ee_cp_view('edit_tag_form.html');
	}
	
	/* END edit tag form */
    
    
    /**	----------------------------------------
    /**	Edit Tag
    /**	---------------------------------------*/
	
	public function edit_tag()
    {
    	if ( ee()->input->get_post('tag_id') === FALSE OR ! is_numeric(ee()->input->get_post('tag_id')))
    	{
    		return FALSE;
    	}
        
        $this->tag_id = ee()->db->escape_str( ee()->input->get_post('tag_id'));
        
        $combine = TRUE;
				
		/**	----------------------------------------
		/**	Validate
		/**	----------------------------------------*/
        
        if ( ( $tag_name = ee()->input->get_post('tag_name') ) === FALSE )
        {
			return $this->_error_message(ee()->lang->line('tag_name_required'));
        }
		
		$query = ee()->db->query( "SELECT tag_name FROM exp_tag_tags WHERE tag_id = '".$this->tag_id."' LIMIT 1" );
		
		if ($query->num_rows() == 0)
		{
			return $this->index();
		}
		
		$old_tag_name = $query->row('tag_name');
		
		unset($temp);
        
		/**	----------------------------------------
		/**	Clean tag
		/**	----------------------------------------*/
		
		$tag_name	= $this->_clean_str( $tag_name );
        
		/**	----------------------------------------
		/**	Check for duplicate
		/**	----------------------------------------*/
		
		$sql	= "SELECT tag_id, tag_name FROM exp_tag_tags
				   WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
				   AND tag_name = '".ee()->db->escape_str( $tag_name )."'";
		
		if ( $this->tag_id != '' )
		{
			$sql .= " AND tag_id != '".$this->tag_id."'";
		}
		
		$sql	.= " LIMIT 1";
		
		$query	= ee()->db->query( $sql );
		
		/**	----------------------------------------
		/*	If we find no matching tags we can't possibly combine tags.
		/**	----------------------------------------*/

		if ( $query->num_rows() == 0 )
		{
			$combine = FALSE;
		}
        
		/**	----------------------------------------
		/**	Are we combining?
		/**	----------------------------------------*/
		
        if ( $combine === TRUE )
        {	
			/** --------------------------------------------
			/**  Previously Tagged by New Tag
			/** --------------------------------------------*/
			
			$extra_sql			= '';
			
			$previous = ee()->db->query("SELECT entry_id FROM exp_tag_entries WHERE tag_id = '".$query->row('tag_id')."'");
			
			if ($query->num_rows() > 0)
			{
				$previous_entries	= array();
				
				foreach($previous->result_array() as $row)
				{
					$previous_entries[] = $row['entry_id'];
				}
				
				$extra_sql .= " AND entry_id NOT IN (".implode(',', $previous_entries).")";
			}
			
			/** --------------------------------------------
			/**  Update Tag Entries from Old to New, Except Where Already Tagged by New
			/** --------------------------------------------*/
			
			ee()->db->query("UPDATE exp_tag_entries SET tag_id = '".ee()->db->escape_str($query->row('tag_id'))."'
							  WHERE tag_id = '".ee()->db->escape_str($this->tag_id)."'".
							  $extra_sql);
			
			/**	----------------------------------------
			/**	Delete the old
			/**	----------------------------------------*/
			
			ee()->db->query( "DELETE FROM exp_tag_entries WHERE tag_id = '".$this->tag_id."'" );
			ee()->db->query( "DELETE FROM exp_tag_tags WHERE tag_id = '".$this->tag_id."'" );
			ee()->db->query( "DELETE FROM exp_tag_subscriptions WHERE tag_id = '".$this->tag_id."'" );
			
			/**	----------------------------------------
			/**	Recount stats
			/**	----------------------------------------*/
		
			$this->_recount( array( $query->row('tag_id') ) );

			$message	= str_replace( array( '%old_tag_name%', '%new_tag_name%' ), array( $old_tag_name, $tag_name ), ee()->lang->line('tags_combined') );
        }
				
		/**	----------------------------------------
		/**	 No Combining, Simply Updating
		/**	----------------------------------------*/
		
		if ( $combine === FALSE )
		{
			ee()->db->query( ee()->db->update_string('exp_tag_tags', array( 'tag_name' => $tag_name, 
																  'tag_alpha' => $this->_first_character($tag_name), 
																  'author_id' => ee()->session->userdata['member_id'], 
																  'edit_date' => ee()->localize->now ), 
																  array( 'tag_id' => $this->tag_id ) ) );

			$message	= ee()->lang->line('tag_updated');
		}

        return $this->index($message);
    }
    
	/* END edit tag */
    
    
    /**	----------------------------------------
    /**	Clean tag
    /**	---------------------------------------*/
	
	public function _clean_str( $str = '' )
    {
		$not_allowed = array('$', '?', ')', '(', '!', '<', '>', '/');
    	
    	$str = str_replace($not_allowed, '', $str);
		
		$str	= ( $this->preference('convert_case') != 'n') ? $this->_strtolower( $str ): $str;
		
		if (ee()->config->item('auto_convert_high_ascii') == 'y')
		{
			ee()->load->helper('text');
			
			$str =  ascii_to_entities( $str );
		}
		
		return $str	= ee()->security->xss_clean( $str );
    }
    
	/* END clean tag */
    
    
	/**	----------------------------------------
	/**	 Find First Character
	/**	---------------------------------------*/
	
	public function _first_character($str)
	{
		if (function_exists('mb_substr'))
		{
			return mb_substr($str, 0, 1);
		}
		elseif(function_exists('iconv_substr') AND ($iconvstr = @iconv('', 'UTF-8', $str)) !== FALSE)
		{
			return iconv_substr($iconvstr, 0, 1, 'UTF-8');
		}
		else
		{
			return substr( $str, 0, 1 );
		}
	}
	
	/* END _first_character() */
	
	/**	----------------------------------------
	/**	 String to Lower
	/**	---------------------------------------*/
	
	public function _strtolower($str)
	{
		if (function_exists('mb_strtolower'))
		{
			return mb_strtolower($str);
		}
		else
		{
			return strtolower( $str );
		}
	}
	
	/* END _strtolower() */

    
    /**	----------------------------------------
    /**	Delete Tag - Confirm
    /**	---------------------------------------*/
	
	public function delete_tag_confirm()
    {
        if ( ee()->input->post('toggle') === FALSE )
        { 
            return $this->index();
        }
		
		$this->cached_vars['tag_ids'] = array();
        
        foreach ( $_POST['toggle'] as $key => $val )
        {        
            $this->cached_vars['tag_ids'][] = $val;
        }
		
		if ( sizeof($this->cached_vars['tag_ids']) == 1 )
		{
			$replace[]	= 1;
			$replace[]	= 'tag';
		}
		else
		{
			$replace[]	= sizeof($this->cached_vars['tag_ids']);
			$replace[]	= 'tags';
		}
		
		$search	= array( '%i%', '%tags%' );
		
		$this->cached_vars['tag_delete_question'] = str_replace( $search, $replace, ee()->lang->line('tag_delete_question'));
		
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('tag_delete_confirm'));
		
		return $this->ee_cp_view('delete_tag_confirm.html');
    }
    
	/* END delete_tag_confirm() */
    
    
    /**	----------------------------------------
    /**	Delete Tag
    /**	---------------------------------------*/
	
	public function delete_tag()
    {
		$sql	= array();      
        
        if ( ee()->input->post('delete') === FALSE OR ! is_array(ee()->input->post('delete')))
        {
            return $this->index();
        }

        $ids	= array();
        
        foreach($_POST['delete'] as $key => $val)
        {
        	$ids[] = $val;
        }
        
        $query = ee()->db->query("SELECT tag_id FROM exp_tag_tags WHERE tag_id IN ('".implode("','", ee()->db->escape_str($ids))."')");
        
		/**	----------------------------------------
		/**	Delete Tags
		/**	----------------------------------------*/
		
		$ids = array();
		
		foreach ( $query->result_array() as $row )
		{			
			$ids[] = $row['tag_id'];
		}
		
		ee()->db->query("DELETE FROM exp_tag_tags WHERE tag_id IN ('".implode("','", ee()->db->escape_str($ids))."')");

		ee()->db->query("DELETE FROM exp_tag_entries WHERE tag_id IN ('".implode("','", ee()->db->escape_str($ids))."')");
		
		ee()->db->query("DELETE FROM exp_tag_subscriptions WHERE tag_id IN ('".implode("','", ee()->db->escape_str($ids))."')");
		
		foreach ( $sql as $q )
		{
			ee()->db->query($q);
		}
    
        $message = ($query->num_rows() == 1) ? str_replace( '%i%', $query->num_rows(), ee()->lang->line('tag_deleted') ) : str_replace( '%i%', $query->num_rows(), ee()->lang->line('tags_deleted') );

        return $this->index($message);
    }
    
	/* END delete_tag() */
    
    
    /**	----------------------------------------
    /**	Bad Tag quick submit
    /**	---------------------------------------*/
	
	public function bad_tag()
    {
    	
		/**	----------------------------------------
		/**	Validate
		/**	----------------------------------------*/
        
        if ( ( $tag_name_post = ee()->input->post('tag_name') ) === FALSE && ( $tag_name_get = base64_decode(urldecode(ee()->input->get('tag_name'))) ) === FALSE )
        {
			return $this->_error_message(ee()->lang->line('tag_name_required'));
        }
        
        $tag_name = ($tag_name_post !== FALSE) ? $tag_name_post : $tag_name_get;
        
        /** --------------------------------------------
        /**  The Past Messing with the Future
        /** --------------------------------------------*/
		
		// What we have here is an old tag that was not made lower cased when created.
		// Kelsey still wants the non-lowercased version entered, so we do this whole fun process twice!
		
		if ($this->preference('convert_case') != TRUE && $this->_strtolower($tag_name) != $tag_name)
		{	
			/**	----------------------------------------
			/**	Check for duplicate
			/**	----------------------------------------*/
			
			$binary	= "BINARY";
			
			$query	= ee()->db->query("SELECT tag_name FROM exp_tag_bad_tags 
								  WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
								  AND {$binary} tag_name = '".ee()->db->escape_str( $tag_name )."'");
	
			if ( $query->num_rows() > 0 )
			{
				$tag_name = trim($tag_name, '"');
				return $this->_error_message( str_replace( '%tag_name%', stripslashes( $tag_name ), ee()->lang->line('bad_tag_exists') ) );
			}
					
			/**	----------------------------------------
			/**	Add
			/**	----------------------------------------*/
			
			ee()->db->query( ee()->db->insert_string('exp_tag_bad_tags', array(	'tag_name' => $tag_name, 
																		'site_id' => ee()->config->item('site_id'), 
																		'author_id' => ee()->session->userdata['member_id'], 
																		'edit_date' => ee()->localize->now ) ) );

		}
		
		/**	----------------------------------------
		/**	Clean tag
		/**	----------------------------------------*/
        
        $tag_name = $this->_clean_str( $tag_name );
        
		/**	----------------------------------------
		/**	Check for duplicate
		/**	----------------------------------------*/
		
		$binary	= ( $this->preference('convert_case') != 'n' && ! isset($binary)) ? '' : "BINARY";
		
		$query	= ee()->db->query("SELECT tag_name FROM exp_tag_bad_tags 
							  WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
							  AND {$binary} tag_name = '".ee()->db->escape_str( $tag_name )."'");

		if ( $query->num_rows() > 0 )
		{
			$tag_name = trim($tag_name, '"');
			return $this->_error_message( str_replace( '%tag_name%', stripslashes( $tag_name ), ee()->lang->line('bad_tag_exists') ) );
		}
				
		/**	----------------------------------------
		/**	Add
		/**	----------------------------------------*/
		
		ee()->db->query( ee()->db->insert_string('exp_tag_bad_tags', array( 'tag_name' => $tag_name, 'site_id' => ee()->config->item('site_id'), 'author_id' => ee()->session->userdata['member_id'], 'edit_date' => ee()->localize->now ) ) );
		
		$tag_name = trim($tag_name, '"');
        return $this->index(str_replace( '%tag_name%', "'".stripslashes( $tag_name )."'", ee()->lang->line('bad_tag_added')));
    }
    
	/* END bad tag quick submit */
    
    
	/**	----------------------------------------
    /**	Manage Bad Tags
	/**	---------------------------------------*/
	
	public function manage_bad_tags($message = '')
    {
		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/
		
		$sql = "SELECT bt.*
				FROM exp_tag_bad_tags bt
				WHERE bt.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
				ORDER BY bt.tag_name ASC";
		
		$query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(*) AS count FROM ', $sql));
		
		/**	----------------------------------------
		/**	Paginate
		/**	----------------------------------------*/
		
		$this->cached_vars['paginate'] = '';
    
		if ( $query->row()->count > $this->row_limit )
		{
			$row_count		= ( ee()->input->get_post('row') === FALSE OR ee()->input->get_post('row') == '' ) ? 0 : ee()->input->get_post('row');
			
			ee()->load->library('pagination');
		
			$config['base_url']				= $this->base.'&method=manage_bad_tags';
			$config['total_rows']			= $query->row()->count;
			$config['per_page']				= $this->row_limit;
			$config['page_query_string']	= TRUE;
			$config['query_string_segment']	= 'row';
				
			ee()->pagination->initialize($config);
	
			$this->cached_vars['paginate'] = ee()->pagination->create_links();
			 
			$sql .= " LIMIT ".$row_count.", ".$this->row_limit;
		}
		
		$query = ee()->db->query($sql);  
		
		/** --------------------------------------------
        /**  Screen Names - Done separately because of DB character set
        /** --------------------------------------------*/
        
        $this->_db_charset_switch('default');
        
        $this->cached_vars['bad_tags'] = array();
        
        $author_ids		= array();
        $screen_names	= array();
        
        foreach($query->result_array() as $row)
        {
        	$author_ids[] = $row['author_id'];
        }
        
        $mquery = ee()->db->query("SELECT screen_name, member_id FROM exp_members
        					  		WHERE member_id IN ('".implode("','", ee()->db->escape_str(array_unique($author_ids)))."')");
        					  
        foreach($mquery->result_array() as $row)
        {
        	$screen_name[$row['member_id']] = $row['screen_name'];
        }
        
        foreach($query->result_array() as $key => $row)
        {
        	$this->cached_vars['bad_tags'][$row['tag_id']] = $row;
        	$this->cached_vars['bad_tags'][$row['tag_id']]['screen_name'] = ( isset($screen_name[$row['author_id']])) ? $screen_name[$row['author_id']] : '--';
        }
        
        $this->_db_charset_switch('UTF-8');
    
    	/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->cached_vars['right_crumb_link']	= $this->base.'&method=add_bad_tags_form';
		$this->cached_vars['right_crumb_title']	= ee()->lang->line('add_bad_tags');
		
		$this->cached_vars['module_menu_highlight'] = 'module_manage_bad_tags';
		
		$this->add_crumb(ee()->lang->line('manage_bad_tags'));
		
		$this->cached_vars['message'] = $message;
		
		return $this->ee_cp_view('bad_tags.html');
	}
	
	/* END manage_bad_tags() */
    
    
    // --------------------------------------------------------------------

	/**
	 *	Bad Tags Processing Method
	 *
	 *	Currently, this is a direct line to the Delete Confirm code. 
	 *
	 *	@access		public
	 *	@return		string
	 */
	public function bad_tags_process()
    {
	    if ( ee()->input->post('toggle') === FALSE )
        { 
            return $this->index();
        }
		
		$this->cached_vars['tag_ids'] = array();
        
        foreach ( $_POST['toggle'] as $key => $val )
        {        
            $this->cached_vars['tag_ids'][] = $val;
        }
		
		if ( sizeof($this->cached_vars['tag_ids']) == 1 )
		{
			$replace[]	= 1;
			$replace[]	= 'tag';
		}
		else
		{
			$replace[]	= sizeof($this->cached_vars['tag_ids']);
			$replace[]	= 'tags';
		}
		
		$search	= array( '%i%', '%tags%' );
		
		$this->cached_vars['bad_tag_delete_question'] = str_replace( $search, $replace, ee()->lang->line('bad_tag_delete_question'));
		
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->add_crumb(ee()->lang->line('bad_tag_delete_confirm'));
		
		return $this->ee_cp_view('delete_bad_tag_confirm.html');
    }
    /* END bad_tags_process() */
  
    
    // --------------------------------------------------------------------

	/**
	 *	Delete Bad Tag
	 *
	 *	Removes Bad Tags from the database.
	 *
	 *	@access		public
	 *	@return		string
	 */	public function delete_bad_tag()
    {
        $sql	= array();      
        
        if ( ee()->input->post('delete') === FALSE OR ! is_array(ee()->input->post('delete')))
        {
            return $this->manage_bad_tags();
        }

        $ids	= array();
                
        foreach ($_POST['delete'] as $key => $val)
        {	
            $ids[] = $val;      
        }
        
        $query = ee()->db->query("SELECT tag_id FROM exp_tag_bad_tags WHERE tag_id IN ('".implode("','", ee()->db->escape_str($ids))."')");
        
		/**	----------------------------------------
		/**	Delete Bad Tags!
		/**	----------------------------------------*/
		
		$ids = array();
		
		foreach ( $query->result_array() as $row )
		{			
			$ids[] = $row['tag_id'];
		}
		
		ee()->db->query("DELETE FROM exp_tag_bad_tags WHERE tag_id IN ('".implode("','", ee()->db->escape_str($ids))."')");
    
        $message = ($query->num_rows() == 1) ? str_replace( '%i%', $query->num_rows(), ee()->lang->line('bad_tag_deleted') ) : str_replace( '%i%', $query->num_rows(), ee()->lang->line('bad_tags_deleted') );

        return $this->manage_bad_tags($message);
    }
    
	/* END delete_bad_tag() */
    
    
    /**	----------------------------------------
    /**	Edit bad tag form
    /**	---------------------------------------*/
	
	public function add_bad_tags_form()
    {	
		$this->cached_vars['module_menu_highlight'] = 'module_manage_bad_tags';

		$this->add_crumb(ee()->lang->line('add_bad_tags'));
		
		return $this->ee_cp_view('add_bad_tags_form.html');	
	}
	/* END add_bad_tags_form() */
    
    
    /**	----------------------------------------
    /**	Edit Bad Tag
    /**	---------------------------------------*/
	
	public function add_bad_tags()
    {
		/**	----------------------------------------
		/**	Validate
		/**	----------------------------------------*/
        
        if ( ( $tag_name = ee()->input->get_post('tag_name') ) === FALSE )
        {
			return $this->_error_message(ee()->lang->line('tag_name_required'));
        }
				
		/**	----------------------------------------
		/**	Clean tag
		/**	----------------------------------------*/
		
		$tag_name = $this->_clean_str( $tag_name );
		
		if ($tag_name == '')
		{
			return $this->_error_message(ee()->lang->line('tag_name_required'));
		}
		
		$tag_array = preg_split( "/\n|\r/", $tag_name, -1, PREG_SPLIT_NO_EMPTY);
        
		/**	----------------------------------------
		/**	Check for duplicate
		/**	----------------------------------------*/
		
		$inserts = array();
		
        foreach($tag_array as $tag_name)
		{
			$query	= ee()->db->query("SELECT tag_name FROM exp_tag_bad_tags 
									  WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
									  AND tag_name = '".ee()->db->escape_str( $tag_name )."' 
									  LIMIT 1");

			if ( $query->num_rows() == 0 )
			{
				$inserts[] = $tag_name;
			}
		}
				
		/**	----------------------------------------
		/**	 Add New Bad Tags to Database
		/**	----------------------------------------*/
		
		foreach($inserts as $tag_name)
		{
			ee()->db->query( ee()->db->insert_string('exp_tag_bad_tags', 
													array(	'tag_name'	=> $tag_name, 
															'site_id'	=> ee()->config->item('site_id'), 
															'author_id'	=> ee()->session->userdata['member_id'], 
															'edit_date'	=> ee()->localize->now ) ) );
		}
			
		$message = str_replace( '%tag_name%', implode(', ', $inserts), (sizeof($inserts) == 1) ? ee()->lang->line('bad_tag_added') : ee()->lang->line('bad_tags_added') );

        return $this->manage_bad_tags($message);
    }
    
	/* END add_bad_tags() */
    
	/**	----------------------------------------
    /**	Manage Prefs
	/**	----------------------------------------*/
	
	public function preferences($message = '')
    {
		/** --------------------------------------------
        /**  Current Values
        /** --------------------------------------------*/
        
        $prefs_query = ee()->db->query("SELECT * FROM exp_tag_preferences");
        						  		
        $preferences = array();
        
        foreach($prefs_query->result_array() as $row)
        {
        	$this->module_preferences[$row['site_id']][$row['tag_preference_name']]= $row['tag_preference_value'];
        }
    
		/**	----------------------------------------
		/**	 Build Form
		/**	----------------------------------------*/
		
		$this->cached_vars['form_fields']	= array();

		foreach($this->cached_vars['sites'] AS $site_id => $site_label)
		{
			$tag_separator				= ( ee()->input->get_post($site_id.'_separator') !== FALSE ) ? ee()->input->get_post($site_id.'_separator') : $this->preference($site_id, 'separator');
			
			$convert_case				= ( ee()->input->get_post($site_id.'_convert_case') !== FALSE ) ? ee()->input->get_post($site_id.'_convert_case'): $this->preference($site_id, 'convert_case');
			
			$enable_tag_form			= ( ee()->input->get_post($site_id.'_enable_tag_form') !== FALSE ) ? ee()->input->get_post($site_id.'_enable_tag_form'): $this->preference($site_id, 'enable_tag_form');
			
			$allow_tag_creation_publish	= ( ee()->input->get_post($site_id.'_allow_tag_creation_publish') !== FALSE ) ? ee()->input->get_post($site_id.'_allow_tag_creation_publish'): $this->preference($site_id, 'allow_tag_creation_publish');
			
			$publish_entry_tag_limit	= ( ee()->input->get_post($site_id.'_publish_entry_tag_limit') !== FALSE ) ? ee()->input->get_post($site_id.'_publish_entry_tag_limit'): $this->preference($site_id, 'publish_entry_tag_limit');
			
			/**	----------------------------------------
			/**	 Parsing Preferences - Separator
			/**	----------------------------------------*/
			
			$attributes = array('name'		=> $site_id.'_separator',
								'id'		=> $site_id.'_separator',
								'class'		=> 'select');
			
			$select = $this->document->createElement('select', $attributes);
			
			foreach( array( 'linebreak', 'comma', 'space' ) as $p )
			{
				$option  =& $this->document->createElement('option', array('value' => $p));
																		   
				if ( $p == $tag_separator )
				{
					$option->appendAttribute('selected', 'selected');
				}
				
				$option->innerHTML = $this->output(ucfirst($p));
				$select->appendChild($option);
			}
			
			$this->cached_vars['form_fields'][$site_id]['tag_module_separator'] = $select;
			
			/**	----------------------------------------
			/**	 Convert Case
			/**	----------------------------------------*/
			
			$attributes = array('name'		=> $site_id.'_convert_case',
								'id'		=> $site_id.'_convert_case',
								'class'		=> 'select');
			
			$select = $this->document->createElement('select', $attributes);
			
			foreach( array( 'y', 'n' ) as $p )
			{
				$option  =& $this->document->createElement('option', array('value' => $p));
				
				if ( $p == $convert_case )
				{
					$option->appendAttribute('selected', 'selected');
				}
				
				$option->innerHTML = $this->output(ee()->lang->line($p));
				$select->appendChild($option);
			}
			
			$this->cached_vars['form_fields'][$site_id]['tag_module_convert_case'] = $select;
			
			/**	----------------------------------------
			/**	Enabled Tag Form
			/**	----------------------------------------*/
			
			$attributes = array('name'		=> $site_id.'_enable_tag_form',
								'id'		=> $site_id.'_enable_tag_form',
								'class'		=> 'select');
			
			$select = $this->document->createElement('select', $attributes);
			
			foreach( array( 'y', 'n' ) as $p )
			{
				$option  =& $this->document->createElement('option', array('value' => $p));
				
				if ( $p == $enable_tag_form )
				{
					$option->appendAttribute('selected', 'selected');
				}
				
				$option->innerHTML = $this->output(ee()->lang->line($p));
				$select->appendChild($option);
			}
			
			$this->cached_vars['form_fields'][$site_id]['tag_module_enable_tag_form'] = $select;
			
			/**	----------------------------------------
			/**	 Allow New Tag Creation via Publish Tab
			/**	----------------------------------------*/
			
			$attributes = array('name'		=> $site_id.'_allow_tag_creation_publish',
								'id'		=> $site_id.'_allow_tag_creation_publish',
								'class'		=> 'select');
			
			$select = $this->document->createElement('select', $attributes);
			
			foreach( array( 'y', 'n' ) as $p )
			{
				$option  =& $this->document->createElement('option', array('value' => $p));
																		   
				if ( $p == $allow_tag_creation_publish )
				{
					$option->appendAttribute('selected', 'selected');
				}
																		   
				$option->innerHTML = $this->output(ee()->lang->line($p));
				$select->appendChild($option);
			}
			
			$this->cached_vars['form_fields'][$site_id]['tag_module_allow_tag_creation_publish'] = $select;
			
			/**	----------------------------------------
			/** Allow Creation of New Tags in Publish Form
			/**	----------------------------------------*/
			
			$attributes = array('type'		=> 'text',
								'dir'		=> 'ltr',
								'style'		=> 'width:50%',
								'name'		=> $site_id.'_publish_entry_tag_limit',
								'value'		=> $publish_entry_tag_limit,
								'size'		=> 5,
								'maxlength'	=> 5,
								'class'		=> 'input');
								
			$this->cached_vars['form_fields'][$site_id]['tag_module_publish_entry_tag_limit'] = $this->document->createElement('input', $attributes);
			
			/** --------------------------------------------
			/**  Create Site/Weblog/Publish Tab Fields - Default Data Arrays
			/** --------------------------------------------*/
			
			$this->cached_vars['channel_form_fields'][$site_id] = array();
			$this->cached_vars['channels'][$site_id] = array();
		}
        
        /** --------------------------------------------
        /**  List of Weblogs
        /** --------------------------------------------*/
        
        $this->_db_charset_switch('default');
        
		$channels_query = ee()->db->query("SELECT {$this->sc->db->channel_id} AS channel_id, site_id, {$this->sc->db->channel_title} AS channel_title, field_group
										  FROM {$this->sc->db->channels}
										  ORDER BY {$this->sc->db->channel_title}");
										  
		$fields_query	= ee()->db->query( "SELECT field_id, field_label, group_id FROM {$this->sc->db->channel_fields}
											ORDER BY field_order");

        
        foreach($channels_query->result_array() as $row)
        {
        	$this->cached_vars['channels'][$row['site_id']][$row['channel_id']] = $row['channel_title'];
        	$fields[$row['field_group']] = array();
        }
        
		$this->cached_vars['default_channel'] = key($this->cached_vars['channels'][ee()->config->item('site_id')]);
		
		foreach($fields_query->result_array() as $row)
		{
			$fields[$row['group_id']][$row['field_id']] = $row['field_label'];
		}
		
		/** --------------------------------------------
        /**  Build Fields
        /** --------------------------------------------*/
		
		foreach($channels_query->result_array() AS $row)
		{
			extract($row);
			
			$this->cached_vars['channel_form_fields'][$channel_id] = array();		
			
			/** --------------------------------------------
			/**  Create Input Field for Publish Tab Label
			/** --------------------------------------------*/
			
			$attributes = array('type'		=> 'text',
								'dir'		=> 'ltr',
								'name'		=> $channel_id.'_publish_tab_label',
								'value'		=> (isset($this->module_preferences[$row['site_id']][$channel_id.'_publish_tab_label'])) ? $this->module_preferences[$row['site_id']][$channel_id.'_publish_tab_label'] : '',
								'size'		=> 30,
								'maxlength'	=> 45,
								'class'		=> 'input');
								
			$this->cached_vars['channel_form_fields'][$channel_id]['publish_tab_label'] = $this->document->createElement('input', $attributes);
			
			/** --------------------------------------------
			/**  Create Select List for Channel's Tag Field
			/** --------------------------------------------*/
			
			$attributes = array('name'		=> $channel_id.'_tag_field',
								'id'		=> $channel_id.'_tag_field',
								'class'		=> 'select');
			
			$select = $this->document->createElement('select', $attributes);
			
			$option =& $this->document->createElement('option', array('value' => '0'));
			$option->innerHTML = $this->output(ee()->lang->line('choose_custom_field'));
			$select->appendChild($option);
			
			foreach( $fields[$field_group] as $p => $label )
			{
				$option =& $this->document->createElement('option', array('value' => $p));
																		   
				if ( isset($this->module_preferences[$row['site_id']][$channel_id.'_tag_field']) && $this->module_preferences[$row['site_id']][$channel_id.'_tag_field'] == $p )
				{
					$option->appendAttribute('selected', 'selected');
				}
				
				$option->innerHTML = $this->output($label);
				$select->appendChild($option);
			}
			
			$this->cached_vars['channel_form_fields'][$channel_id]['tag_harvest_field'] = $select;
		}
		
		$this->_db_charset_switch('UTF-8');
		
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->cached_vars['module_menu_highlight'] = 'module_preferences';
		
		$this->add_crumb(ee()->lang->line('tag_preferences'));
		
		$this->cached_vars['message'] = $message;
		
		return $this->ee_cp_view('preferences_form.html');
		
	}
	/* END preferences() */
    
    /**	----------------------------------------
    /**	Update parse pref
    /**	---------------------------------------*/
	
	public function update_preferences()
    {
    	if ( ! isset($_POST[ee()->config->item('site_id').'_separator']))
    	{
    		return $this->preferences();
    	}
    	
    	ee()->db->query("DELETE FROM exp_tag_preferences");
    
		/**	----------------------------------------
		/**	Update Preferences
		/**	----------------------------------------*/
		
		foreach($this->cached_vars['sites'] AS $site_id => $site_label)
		{	
			$prefs = array('separator', 'convert_case', 'enable_tag_form', 'allow_tag_creation_publish', 'publish_entry_tag_limit');
			
			foreach($prefs as $val)
			{
				ee()->db->query(ee()->db->insert_string('exp_tag_preferences', 
											  array( 'tag_preference_name' => $val, 
													 'tag_preference_value' => ($val == 'publish_entry_tag_limit') ? floor(ee()->input->post($site_id.'_'.$val)) : ee()->input->post($site_id.'_'.$val),
													 'site_id'				=> $site_id)));
			}
		}
		
		/** --------------------------------------------
        /**  Channel Specific Preferences
        /** --------------------------------------------*/
       
		$channels_query = ee()->db->query("SELECT {$this->sc->db->channel_id} AS channel_id, site_id
										  FROM {$this->sc->db->channels}
										  ORDER BY {$this->sc->db->channel_title}");		
		
		foreach($channels_query->result_array() as $row)
		{
			ee()->db->query(ee()->db->insert_string('exp_tag_preferences', 
										  array( 'tag_preference_name' => $row['channel_id'].'_publish_tab_label', 
										  		 'tag_preference_value' => ee()->input->post($row['channel_id'].'_publish_tab_label'),
										  		 'site_id'				=> $row['site_id'])));
										  		 
			ee()->db->query(ee()->db->insert_string('exp_tag_preferences', 
										  array( 'tag_preference_name' => $row['channel_id'].'_tag_field', 
										  		 'tag_preference_value' => ee()->input->post($row['channel_id'].'_tag_field'),
										  		 'site_id'				=> $row['site_id'])));
		}


		//remove old tab style from everything
		if (APP_VER >= 2.0)
		{		
			ee()->load->library('layout');		
			
			if ( ! class_exists('Tag_updater_base'))
			{
				require_once $this->addon_path.'tag.user.base.php';				
			}

	    	$T = new Tag_updater_base();
			
			//remove all old ones
			ee()->layout->delete_layout_tabs($T->tabs());
		
			//if we already have tabs named, we need to reinstall them
			//this starts by not using cache on these so if its true, we still only call it once
			if ($this->data->get_tab_channel_ids(FALSE) !== FALSE)
			{
				ee()->layout->add_layout_tabs($T->tabs(), '', array_keys($this->data->get_tab_channel_ids()));
			}
		}

        return $this->preferences(ee()->lang->line('tag_preferences_updated'));
    }
	/* END update_preferences() */
    
    
	/**	----------------------------------------
    /**	Harvest
	/**	---------------------------------------*/
	
	public function harvest($message = '')
    {
		/**	----------------------------------------
		/**	 Harvest from What Data Source?
		/**	----------------------------------------*/
		
		$groups = array('channel_categories'	=> ee()->lang->line((APP_VER < 2.0) ? 
							'harvest_from_weblog_categories' : 'harvest_from_channel_categories'),
						'gallery_categories'	=> ee()->lang->line('harvest_from_gallery_categories'),
						'tag_fields'			=> ee()->lang->line((APP_VER < 2.0) ? 
							'harvest_from_weblog_tag_field' : 'harvest_from_channel_tag_field'));
		
		if ( ee()->db->table_exists( 'exp_gallery_entries' ) === FALSE )
		{
			unset($groups['gallery_categories']);
		}
		
		foreach($groups as $group => $group_label)
		{
			$options[$group] = array();
		
			switch($group)
			{
				case 'channel_categories' :
				
					$channels_query = ee()->db->query(
						"SELECT 	{$this->sc->db->channel_id} AS channel_id, 
									site_label, 
									{$this->sc->db->channel_title} AS channel_title, 
									field_group
						 FROM 		{$this->sc->db->channels}, exp_sites
						 WHERE 		exp_sites.site_id = {$this->sc->db->channels}.site_id
						 ORDER BY 	{$this->sc->db->channel_title}");
								
					foreach($channels_query->result_array() as $row)
					{
						$options[$group][$row['channel_id']] = $row['site_label'].' - '.$row['channel_title'];
					}
					
				break;
				
				case 'gallery_categories' :
				
					$query = ee()->db->query( 
						"SELECT 	gallery_id, gallery_full_name 
						 FROM 		exp_galleries 
						 ORDER BY 	gallery_full_name ASC" );
					
					foreach($query->result_array() as $row)
					{
						$options[$group][$row['gallery_id']] = $row['gallery_full_name'];
					}
				
				break;
				
				case 'tag_fields'		  :
				
					$query = ee()->db->query(
						"SELECT tag_preference_name 
						 FROM 	exp_tag_preferences 
						 WHERE 	tag_preference_name 
						 LIKE 	'%_tag_field' 
						 AND 	tag_preference_value != '0'"
					);
        						  		
					foreach($query->result_array() AS $row)
					{
						$x = explode('_', $row['tag_preference_name'], 2);
						
						foreach($channels_query->result_array() AS $row)
						{
							if ($row['channel_id'] == $x[0])
							{
								$options[$group][$row['channel_id']] = $row['site_label'].' - '.$row['channel_title'];
							}
						}
					}
					
				break;
			}
		}
		
		/** --------------------------------------------
        /**  Build Harvest Location Field
        /** --------------------------------------------*/
		
		$attributes = array(
			'name'		=> 'harvest_sources[]',
			'id'		=> 'harvest_sources',
			'class'		=> 'select',
			'multiple'	=> 'multiple',
			'size'		=> 8
		);
		
		$select = $this->document->createElement('select', $attributes);
		
		foreach( $groups as $group => $group_label )
		{
			$optgroup  =& $this->document->createElement('optgroup', array('label' => $group_label));
			
			foreach($options[$group] as $p => $label)
			{
				$option  =& $this->document->createElement('option', array('value' => $group.'_'.$p));
				$option->innerHTML = $this->output($label);
				$optgroup->appendChild($option);
			}
					
			$select->appendChild($optgroup);
		}
		
		$this->cached_vars['form_fields']['harvest_sources'] = $select;
		
		/**	----------------------------------------
		/**	 Batch Size for Processing
		/**	----------------------------------------*/
		
		$options = array(1, 50, 100, 250, 500, 1000);
		
		$attributes = array('name'		=> 'per_batch',
							'id'		=> 'per_batch',
							'class'		=> 'select');
		
		$select = $this->document->createElement('select', $attributes);
		
		foreach( $options as $p )
		{
			$option  =& $this->document->createElement('option', array('value' => $p));
			
			if ( $p == 250 )
			{
				$option->appendAttribute('selected', 'selected');
			}
			
			$option->innerHTML = $this->output($p);
			$select->appendChild($option);
		}
		
		$this->cached_vars['form_fields']['per_batch'] = $select;
    
		/**	----------------------------------------
		/**	 Build page
		/**	----------------------------------------*/
		
		$this->cached_vars['module_menu_highlight'] = 'module_harvest';
		
		$this->add_crumb(ee()->lang->line('tag_harvest'));
		
		$this->cached_vars['message'] = $message;
		
		return $this->ee_cp_view('harvest_form.html');
	}
	
	/* END harvest() */
	
	// --------------------------------------------------------------------

	/**
	 *	Process Harvest Request or Refresh
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	public function process_harvest()
    {
    	$this->cached_vars['module_menu_highlight'] = 'module_harvest';
    	
    	/** --------------------------------------------
        /**  Do Our Harvesting
        /** --------------------------------------------*/
    	
		$return = $this->_harvest();
		
		/** --------------------------------------------
        /**  Are We Finished?
        /** --------------------------------------------*/
        
        if ( ! in_array(FALSE, $return['done']))
        {
        	return $this->harvest(ee()->lang->line('success_harvest_processing_is_complete'));
        }
        
		$this->cached_vars['hidden_fields'] = array();
		
		/** --------------------------------------------
        /**  Harvest Sources for this Batch
        /** --------------------------------------------*/

		foreach($return['done'] as $type => $finished)
		{
			if ($finished === TRUE) continue;
			
			foreach($return['harvest_sources'] as $harvest_source)
			{
				if ( ! stristr($harvest_source, $type)) continue;
			
				$attributes = array('type'		=> 'hidden',
									'name'		=> 'harvest_sources[]',
									'value'		=> $harvest_source);
									
				$this->cached_vars['hidden_fields'][] = $this->document->createElement('input', $attributes);
			}
		}
		
		/** --------------------------------------------
        /**  Batch Number and Per Batch Amount
        /** --------------------------------------------*/
        
        $return['batch']++; // Next Batch!
		
		$attributes = array('type'		=> 'hidden',
							'name'		=> 'batch',
							'value'		=> $return['batch']);
							
		$this->cached_vars['hidden_fields'][] = $this->document->createElement('input', $attributes);
		
		$attributes = array('type'		=> 'hidden',
							'name'		=> 'per_batch',
							'value'		=> $return['per_batch']);
							
		$this->cached_vars['hidden_fields'][] = $this->document->createElement('input', $attributes);
		
		/** --------------------------------------------
        /**  Set All Return Variables to View Variables and Call Batch Page
        /** --------------------------------------------*/
		
		$this->cached_vars = array_merge($this->cached_vars, $return);
		
		$this->add_crumb(ee()->lang->line('tag_harvest_batch_process'));
		
		return $this->ee_cp_view('harvest_batch_form.html');
	}
	/* END process_harvest() */
    
	// --------------------------------------------------------------------

	/**
	 *	The Harvest Processing Routine
	 *
	 *	The actual processing work is done here, and then we keep process_harvest() a bit cleaner
	 *	for displaying the page for more batches.
	 *
	 *	@access		private
	 *	@return		array
	 */
	
	private function _harvest( )
    {	
    	/** --------------------------------------------
        /**  Data Validation
        /** --------------------------------------------*/
    	
    	if (ee()->input->get_post('harvest_sources') === FALSE)
    	{
    		return $this->harvest();	
    	}
    	
    	if ( isset($_POST['harvest_sources']))
    	{
    		$harvest_sources = (! is_array($_POST['harvest_sources'])) ? 
				array($_POST['harvest_sources']) : $_POST['harvest_sources'];
    	}
    	
    	if ( isset($_GET['harvest_sources']))
    	{
    		$harvest_sources = explode('|', $_GET['harvest_sources']);
    	}
    	
    	if ( count($harvest_sources) == 0)
    	{
    		return $this->harvest();
    	}
    	
    	$per_batch	= ( ee()->input->get_post('per_batch') === FALSE OR 
						! is_numeric( ee()->input->get_post('per_batch'))) ? 
							250 : ee()->input->get_post('per_batch');
    	
    	$batch		=  ( is_numeric(ee()->input->get_post('batch'))) ? ee()->input->get_post('batch') : 1;
    	
    	$done		= array();
    	
    	/** --------------------------------------------
        /**  Find Out What We're Parsing
        /** --------------------------------------------*/
        
        $harvest_types = array();
        
        foreach($harvest_sources as $harvest_source)
        {
        	foreach(array('channel_categories', 'gallery_categories', 'tag_fields') as $type)
        	{
        		if (stristr($harvest_source, $type))
        		{
        			$harvest_types[$type][] = str_replace($type.'_', '', $harvest_source);
        		}
        	}
        }
        
        /** --------------------------------------------
        /**  Switch to the DB's Character Set from the Tag Character Set
        /** --------------------------------------------*/
		
		$this->_db_charset_switch('default');
		
		/** --------------------------------------------
        /**  Let's Prepare for Some Parsing!
        /** --------------------------------------------*/
		
		$data  = array();
		$total = 0;
		
		foreach($harvest_types as $harvest_type => $harvest_items)
		{	
			$done[$harvest_type] = FALSE;
			$data[$harvest_type] = array();
		
			/**	----------------------------------------
			/**	Query Channel Categories
			/**	----------------------------------------*/
			
			if ( $harvest_type == 'channel_categories')
			{
				$sql	= "SELECT 		%sql 
						   FROM 		{$this->sc->db->channel_titles} AS wt
						   LEFT JOIN 	exp_category_posts cp 
						   ON 			wt.entry_id = cp.entry_id
						   LEFT JOIN 	exp_categories c 
						   ON 			c.cat_id = cp.cat_id 
						   WHERE 		wt.{$this->sc->db->channel_id} 
						   IN 			('".implode( "','", ee()->db->escape_str($harvest_items) )."')";
			
				/**	----------------------------------------
				/**	 Check Total
				/**	----------------------------------------*/
				
				$query	= ee()->db->query( str_replace( "%sql", "COUNT(*) AS count", $sql ) ); 
				$query_row = $query->row_array();
				
				if ($query_row['count'] == 0)
				{
					$done[$harvest_type] = TRUE;
					continue;
				}
				
				if ($query_row['count'] > $total)
				{
					$total = $query_row['count'];
				}
			
				/**	----------------------------------------
				/**	Get data
				/**	----------------------------------------*/
				
				$sql	.= " ORDER BY entry_id ASC LIMIT " . ( ( $batch - 1 ) * $per_batch ).",".$per_batch;
				
				$query	= ee()->db->query( 
					str_replace( 
						"%sql", 
						"DISTINCT wt.entry_id, wt.site_id, wt.{$this->sc->db->channel_id}, c.cat_name", 
						$sql 
					) 
				);
				
				if ($query->num_rows() == 0)
				{
					$done[$harvest_type] = TRUE;
					continue;
				}
				elseif($query->num_rows() < $per_batch OR $query_row['count'] == ( $batch * $per_batch ))
				{
					$done[$harvest_type] = TRUE;
				}
			
				/**	----------------------------------------
				/**	Prep data
				/**	----------------------------------------*/
			
				$entries	= array();
				
				foreach ( $query->result_array() as $row )
				{
					if ( trim($row['cat_name']) == '' ) continue;
					$entries[ $row['entry_id'] ][ $this->sc->db->channel_id ]	= $row[$this->sc->db->channel_id];
					$entries[ $row['entry_id'] ][ 'site_id' ]	= $row['site_id'];
					$entries[ $row['entry_id'] ][ 'str' ][]		= stripslashes($row['cat_name']);
				}
				
				$data[$harvest_type] = $entries;
			}
			elseif ( $harvest_type == 'gallery_categories' )
			{
				$sql	= "SELECT	 	%sql 
						   FROM 		exp_gallery_entries ge
						   LEFT JOIN 	exp_gallery_categories gc 
						   ON 			ge.cat_id = gc.cat_id 
						   WHERE 		ge.gallery_id 
						   IN 			('".implode( "','", ee()->db->escape_str($harvest_items) )."')";
			
				/**	----------------------------------------
				/**	 Check Total
				/**	----------------------------------------*/
				
				$query	= ee()->db->query( str_replace( "%sql", "COUNT(*) AS count", $sql ) ); 
				$query_row = $query->row_array();
				
				if ($query_row['count'] == 0)
				{
					$done[$harvest_type] = TRUE;
					continue;
				}
				
				if ($query_row['count'] > $total)
				{
					$total = $query_row['count'];
				}
			
				/**	----------------------------------------
				/**	Get data
				/**	----------------------------------------*/
				
				$sql	.= " ORDER BY entry_id ASC LIMIT ".( ( $batch - 1 ) * $per_batch ).",".$per_batch;
				
				$query	= ee()->db->query( str_replace( "%sql", "DISTINCT ge.entry_id, ge.gallery_id, gc.cat_name", $sql ) );
				
				if ($query->num_rows() == 0)
				{
					$done[$harvest_type] = TRUE;
					continue;
				}
				elseif($query->num_rows() < $per_batch OR $query_row['count'] == ( $batch * $per_batch ))
				{
					$done[$harvest_type] = TRUE;
				}
			
				/**	----------------------------------------
				/**	Prep data
				/**	----------------------------------------*/
			
				$entries	= array();
				
				foreach ( $query->result_array() as $row )
				{
					if ( trim($row['cat_name']) == '' ) continue;
					$entries[ $row['entry_id'] ][ $this->sc->db->channel_id ]	= $row['gallery_id'];
					$entries[ $row['entry_id'] ][ 'str' ][]		= $row['cat_name'];
				}
				
				$data[$harvest_type] = $entries;
			}
			elseif ( $harvest_type == 'tag_fields' )
			{
				/**	----------------------------------------
				/**	Discover Our Fields
				/**	----------------------------------------*/
				
				$fields	= array();
											
				$query = ee()->db->query(
					"SELECT tag_preference_name, tag_preference_value 
					 FROM 	exp_tag_preferences
					 WHERE 	tag_preference_name 
					 LIKE 	'%_tag_field' 
					 AND 	tag_preference_value != '0'"
				);
										  
        						  		
				foreach($query->result_array() AS $row)
				{
					foreach($harvest_items as $channel_id)
					{
						if ($row['tag_preference_name'] == $channel_id.'_tag_field')
						{
							$fields[$channel_id] = $row['tag_preference_value'];
						}
					}
				}
				
				/** --------------------------------------------
				/**  Validate Fields - They Might Have Deleted Since Saving Preferences?
				/** --------------------------------------------*/
				
				$query = ee()->db->query(
					"SELECT COUNT(*) AS count 
					 FROM 	{$this->sc->db->channel_fields} 
					 WHERE 	field_id 
					 IN 	('" . implode("','", ee()->db->escape_str(array_unique($fields))) . "')"
				);
										  
				if ($query->row('count') != sizeof(array_unique(array_values($fields))))
				{
					$this->add_crumb(ee()->lang->line('error'));
					$this->cached_vars['error_message'] = ee()->lang->line('error_invalid_custom_fields_for_channels');
					
					return $this->ee_cp_view('error_page.html');
				}
				
				/**	----------------------------------------
				/**	 Initial Query of Data Retrieval
				/**	----------------------------------------*/
				
				$sql	= "SELECT 		%sql 
						   FROM 		{$this->sc->db->channel_titles} AS wt 
						   LEFT JOIN 	{$this->sc->db->channel_data} AS wd 
						   ON 			wt.entry_id = wd.entry_id 
						   WHERE		wt.{$this->sc->db->channel_id} 
						   IN 			('".implode( "','", ee()->db->escape_str(array_keys($fields)) )."')";
			
				/**	----------------------------------------
				/**	 Check Total
				/**	----------------------------------------*/
				
				$query	= ee()->db->query( str_replace( "%sql", "COUNT(*) AS count", $sql ) ); 
				$query_row = $query->row_array();
				
				if ($query_row['count'] == 0)
				{
					$done[$harvest_type] = TRUE;
					continue;
				}
				
				if ($query_row['count'] > $total)
				{
					$total = $query_row['count'];
				}
			
				/**	----------------------------------------
				/**	Get data
				/**	----------------------------------------*/
				
				$sql	.= " ORDER BY entry_id ASC LIMIT ".(( $batch - 1 ) * $per_batch).",".$per_batch;
				
				$query	= ee()->db->query( 
					str_replace( 
						"%sql", 
						"wt.entry_id, wt.site_id, wt.{$this->sc->db->channel_id}, wd.field_id_" . 
							implode( ", wd.field_id_", $fields ), 
						$sql 
					) 
				);
				
				// There is nothing to harvest, so we are done!
				if ($query->num_rows() == 0)
				{
					$done[$harvest_type] = TRUE;
					continue;
				}
				// The number left is less than or equal to the number per batch, so this is the last batch!
				elseif($query->num_rows() < $per_batch OR $query_row['count'] == ( $batch * $per_batch ))
				{
					$done[$harvest_type] = TRUE;
				}
			
				/**	----------------------------------------
				/**	Prep data
				/**	----------------------------------------*/
			
				$entries	= array();
				
				foreach ( $query->result_array() as $row )
				{
					if ( ! isset( $fields[ $row[$this->sc->db->channel_id] ] ) ) continue;
					
					$id	= 'field_id_'.$fields[ $row[$this->sc->db->channel_id] ];
					
					if ( $row[ $id ] == '' ) continue;
					
					$entries[ $row['entry_id'] ][ $this->sc->db->channel_id ]	= $row[$this->sc->db->channel_id];
					$entries[ $row['entry_id'] ][ 'site_id' ]					= $row['site_id'];
					$entries[ $row['entry_id'] ][ 'str' ]						= $row[$id];
				}
				
				$data[$harvest_type] = $entries;
			}
		}
		
		/** --------------------------------------------
        /**  Commence Parsing!
        /** --------------------------------------------*/
        
        if ( ! class_exists('Tag') )
		{
			require $this->addon_path.'mod.tag.php';
		}
        
		foreach($data as $harvest_type => $entries)
		{
			if ( $harvest_type == 'channel_categories')
			{	
				$Tag = new Tag();
					
				foreach ( $entries as $key => $val )
				{
					$Tag->remote		= FALSE;
					$Tag->batch			= TRUE;
					$Tag->entry_id		= $key;
					$Tag->site_id		= $val['site_id'];
					$Tag->channel_id	= $val[$this->sc->db->channel_id];
					$Tag->str			= implode( "\n", $val['str'] );
					$Tag->parse();
				}
			}
			elseif ( $harvest_type == 'gallery_categories' )
			{
				$Tag	= new Tag();
				
				foreach ( $entries as $key => $val )
				{
					$Tag->remote		= FALSE;
					$Tag->batch			= TRUE;
					$Tag->type			= 'gallery';
					$Tag->entry_id		= $key;
					$Tag->channel_id	= $val[$this->sc->db->channel_id];
					$Tag->site_id		= ee()->config->item('site_id'); // Do Gallery Tags really need a Site ID, since Modules are Site agnostic?
					$Tag->str			= implode( "\n", $val['str'] );
					$Tag->parse();
				}
			}
			elseif ( $harvest_type == 'tag_fields' )
			{
				$Tag	= new Tag();
				
				foreach ( $entries as $key => $val )
				{
					$Tag->remote		= FALSE;
					$Tag->batch			= TRUE;
					$Tag->entry_id		= $key;
					$Tag->content_id	= $Tag->channel_id = $val[$this->sc->db->channel_id];
					$Tag->site_id		= $val['site_id'];
					$Tag->str			= $val['str'];
					$Tag->parse();
				}
			}
		}
		
		/**	----------------------------------------
		/**	Return
		/**	----------------------------------------*/
		
		return array('done'				=> $done,
					 'harvest_sources'	=> $harvest_sources, 
					 'batch'			=> $batch, 
					 'per_batch'		=> $per_batch,
					 'total'			=> round($total/$per_batch));
	}
	/* END _harvest() */
	
    
	/**	----------------------------------------
	/**	Recount Tag Statistics
	/**	---------------------------------------*/
	
	public function recount( $return = TRUE )
    {

    	
    	/** --------------------------------------------
		/**	Set num per batch and start
		/** --------------------------------------------*/
		
		$num	= ( ee()->input->get_post('num') !== FALSE AND is_numeric( ee()->input->get_post('num') ) === TRUE ) ? ee()->input->get_post('num'): 1000;
		$start	= ( ee()->input->get_post('start') !== FALSE AND is_numeric( ee()->input->get_post('start') ) === TRUE ) ? ee()->input->get_post('start'): 0;
		
		/**	----------------------------------------
		/**	 Check Totals
		/**	----------------------------------------*/
		
		$countq		= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_tag_tags" );
		$remainingq	= ee()->db->query( "SELECT site_id FROM exp_tag_tags LIMIT ".ee()->db->escape_str( $start ).",".ee()->db->escape_str( $num ) );
		
		/**	----------------------------------------
		/**	Any tags at all?
		/**	----------------------------------------*/
		
		if ( $countq->num_rows() == 0 OR $countq->row('count') == 0 )
		{
			ee()->functions->bounce( $this->base.'msg=no_tags_to_recount');
			exit;
		}
		
		/**	----------------------------------------
		/**	Are we done?
		/**	----------------------------------------*/
		
		if ( $remainingq->num_rows() == 0 )
		{
			ee()->functions->bounce( $this->base.'msg=tags_successfully_recounted');
			exit;
		}
    	
    	/** --------------------------------------------
		/**	Is this our first pass through?
		/** --------------------------------------------*/
		
		if ( $start == 0 )
		{
			/** --------------------------------------------
			/**  Old Entries Not Removed in Previous Versions
			/** --------------------------------------------*/
			
			// Disabled because it was deleting Tags from entries submitted via SAEF from a Guest member.
			//ee()->db->query("DELETE FROM exp_tag_entries WHERE exp_tag_entries.author_id = 0");
						
			ee()->db->query("DELETE te 
							FROM exp_tag_entries AS te
							LEFT JOIN exp_members AS m ON te.author_id = m.member_id
							WHERE te.author_id != 0
							AND m.member_id IS NULL");
			
			ee()->db->query("DELETE te FROM exp_tag_entries AS te
							LEFT JOIN {$this->sc->db->channel_titles} AS wt ON te.entry_id = wt.entry_id
							WHERE te.type = 'channel'
							AND wt.entry_id IS NULL");
			
			if ( ee()->db->table_exists( 'exp_gallery_entries' ) === TRUE )
			{
				ee()->db->query("DELETE te FROM exp_tag_entries AS te
								LEFT JOIN exp_gallery_entries AS ge ON te.entry_id = ge.entry_id
								WHERE te.type = 'gallery'
								AND ge.entry_id IS NULL");
			}
			
			/**	----------------------------------------
			/**	Remove Orphans
			/**	----------------------------------------*/
	
			ee()->db->query("DELETE tt 
							FROM exp_tag_tags AS tt
							LEFT JOIN exp_tag_entries AS te ON te.tag_id = tt.tag_id
							WHERE te.tag_id IS NULL");
		}

		/**	----------------------------------------
		/**	Recount stats for all existing tags
		/**	----------------------------------------*/
    	
    	$query	= ee()->db->query( "SELECT tag_id FROM exp_tag_tags LIMIT ".ee()->db->escape_str( $start ).",".ee()->db->escape_str( $num ) );
		
		$tags	= array();
		
		foreach ( $query->result_array() as $row )
		{
			$tags[]	= $row['tag_id'];
		}
		
		$this->_recount( $tags );

		/**	----------------------------------------
		/**	Loop and refresh page
		/**	----------------------------------------*/
		
		$start	+=	$num;
						
		$url	= $this->base.'P='.'recount'.AMP.'num='.$num.AMP.'start='.$start;
		
		$data	= array(
						'title'		=> ee()->lang->line('recount'),
						'heading'	=> ee()->lang->line('recount'),
						'content'	=> str_replace( array( '%num', '%start', '%total' ), array( $num, $start, $countq->row('count') ), ee()->lang->line('tag_recount_running') ),
						'rate'		=> 2,
						'link'		=> array( $url, 'click here to get there' ),
						'redirect'	=> $url
						);
		
		ee()->output->show_message( $data );
    }
    /* END recount() */
    
    
	/**	----------------------------------------
	/**	Recount
	/**	---------------------------------------*/
	
	public function _recount( $tags = array() )
    {		
		if ( class_exists('Tag') === FALSE )
		{
			require $this->addon_path.'mod.tag'.EXT;
		}
		
		// $Tag	= new Tag;
		
		Tag::_recount( array( 'tag_id' => $tags ) );
    }
    
	/* END recount */
	
    // --------------------------------------------------------------------

	/**
	 *	Returns the JavaScript for the Publish Form
	 *
	 *	@access		public
	 *	@return		string
	 */

    function tag_publish_javascript()
    {
    	$this->cached_vars['existing_tags'] = array();
    	
    	$this->cached_vars['tag_name']					= ee()->lang->line('tags');
    	$this->cached_vars['delimiter']					= 'comma';
    	$this->cached_vars['separator']					= ', ';
    	$this->cached_vars['publish_entry_tag_limit']	= 0;
    	
    	/** --------------------------------------------
        /**  Fetch Tag's Name from Preferences
        /** --------------------------------------------*/
    	
    	if ( ee()->input->get_post($this->sc->db->channel_id) !== FALSE )
		{
			$channel_id = ee()->input->get_post($this->sc->db->channel_id);
			
			$query	= ee()->db->query( "SELECT tag_preference_value, tag_preference_name FROM exp_tag_preferences
										WHERE tag_preference_name IN ('".ee()->db->escape_str($channel_id)."_publish_tab_label', 'separator', 'publish_entry_tag_limit')
										AND tag_preference_value != ''
										AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'" );
			
			foreach($query->result_array() as $row)
			{
				if ($row['tag_preference_name'] == ee()->db->escape_str($channel_id)."_publish_tab_label")
				{
					$this->cached_vars['tag_name'] = $row['tag_preference_value'];
				}
				elseif($row['tag_preference_name'] == 'separator')
				{
					$this->cached_vars['delimiter'] = $row['tag_preference_value'];
					
					switch ( $row['tag_preference_value'] )
					{
						case 'comma'		: $this->cached_vars['separator'] = ", ";
							break;
						case 'space'		: $this->cached_vars['separator'] = " ";
							break;
						case 'semicolon'	: $this->cached_vars['separator'] = "; ";
							break;
						case 'colon'		: $this->cached_vars['separator'] = ": ";
							break;
						default				: $this->cached_vars['separator'] = "\n";
							break;
					}
				}
				else
				{
					$this->cached_vars['publish_entry_tag_limit'] = $row['tag_preference_value'];
				}
			}
		}
    
    	if (APP_VER >= 2.0)
    	{
    		$query	= ee()->db->query("SELECT tag_name FROM exp_tag_tags
									   WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
									   ORDER BY tag_name" );
									   
			if ($query->num_rows() > 0)
			{
				$this->cached_vars['existing_tags'] = $query->result_array();
			}
    	}
    
		$this->file_view('publish_tab_block.js', gmmktime() - 1);
    }
    /**	END tag_publish_javascript */
    
    // --------------------------------------------------------------------

	/**
	 *	Returns the CSS for the Publish Form
	 *
	 *	@access		public
	 *	@return		string
	 */

    function tag_publish_css ()
    {
		$this->file_view('publish_tab_block.css');
    }
    /**	END tag_css */
    

	/**	----------------------------------------
	/**	AJAX tag browse
	/**	---------------------------------------*/
	
	public function tag_browse()
    {
    	ee()->lang->loadfile( 'tag' );
    	
		/**	----------------------------------------
		/**	Handle existing
		/**	----------------------------------------*/
		
		$existing	= array();
		
		if ( ee()->input->get_post('existing') !== FALSE )
		{
			$existing	= explode( "||", ee()->security->xss_clean(ee()->input->get_post('existing')) );
		}
		
		/**	----------------------------------------
		/**	Query and construct
		/**	----------------------------------------*/
		
		$this->_db_charset_switch('UTF-8');
		
		$extra = '';
		
		if (ee()->input->get_post('msm_tag_search') !== 'y')
		{
			$extra = " AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'";
		}
		
		if (ee()->input->get_post('str') == '*')
		{
			$query	= ee()->db->query("SELECT DISTINCT tag_name AS name
									   FROM exp_tag_tags
									   WHERE tag_name NOT IN ('".implode( "','", ee()->db->escape_str( $existing ) )."') 
									   {$extra}
									   ORDER BY tag_name" );
		}
		else
		{
			$str 	= $this->_clean_str( ee()->input->get_post('str') );
			
			$query	= ee()->db->query("SELECT DISTINCT tag_name AS name
									   FROM exp_tag_tags
									   WHERE tag_alpha = '".ee()->db->escape_str( $this->_first_character($str) )."'
									   AND tag_name LIKE '".ee()->db->escape_str( $str )."%'
									   AND tag_name NOT IN ('".implode( "','", ee()->db->escape_str( $existing ) )."') 
									   {$extra}
									   ORDER BY tag_name" );
		}
		
		$this->_db_charset_switch('default');
		
		if ( $query->num_rows() == 0 )
		{
			$select = '<div class="message"><p>'.ee()->lang->line('no_matching_tags').'</p></div>';
		}
		else
		{
			$select	= '<ul>';
			
			foreach ( $query->result_array() as $row )
			{
				$select	.= '<li><a href="#">'.$row['name']."</a></li>";
			}
			
			$select	.= '</ul>';
		}
		
		@header("HTTP/1.0 200 OK");
		@header("HTTP/1.1 200 OK");
		
		exit($select);
    }
	/* END AJAX browse */
	
	/**	----------------------------------------
	/**	AJAX Tag Auto Complete for EE 2.x
	/**	---------------------------------------*/
	
	public function tag_autocomplete()
	{	
		/**	----------------------------------------
		/**	Handle existing
		/**	----------------------------------------*/
		
		$existing = array();
		
		if ( ee()->input->get('current_tags') !== FALSE )
		{
			/** --------------------------------------------
			/**  Delimiter
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
			
			$current_tags = substr(ee()->input->get('current_tags'), 0, strrpos(ee()->input->get('current_tags'), $delim));
			
			$existing = array_unique(explode( $delim, ee()->security->xss_clean( $current_tags)));
		}
		
		/**	----------------------------------------
		/**	Query DB
		/**	----------------------------------------*/
		
		$this->_db_charset_switch('UTF-8');

		$sql = "SELECT tag_name
			    FROM exp_tag_tags 
			    WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' ";
			    
		if (sizeof($existing) > 0)
		{
			$sql .= "AND tag_name NOT IN ('".implode( "','", ee()->db->escape_str( $existing ) )."') ";
		}
		
		if (ee()->input->get('q') != '*')
		{
			$sql .= "AND tag_name LIKE '".ee()->db->escape_like_str(ee()->input->get('q'))."%' ";
		}
		
		$sql .= "ORDER BY tag_name DESC LIMIT 100";
		
		$query = ee()->db->query($sql);
		
		$this->_db_charset_switch('default');
		
		$return_tags = array();
		
		foreach($query->result_array() as $row)
		{
			$return_tags[] = $row['tag_name'];
		}
		
		$output = implode("\n", array_unique($return_tags));
		
		/** --------------------------------------------
        /**  Headers
        /** --------------------------------------------*/
		
		ee()->output->set_status_header(200);
		@header("Cache-Control: max-age=5184000, must-revalidate");
		@header('Last-Modified: '.gmdate('D, d M Y H:i:s', gmmktime()).' GMT');
		@header('Expires: '.gmdate('D, d M Y H:i:s', gmmktime() + 1).' GMT');
		@header('Content-Length: '.strlen($output));

        /**	----------------------------------------
        /**	 Send JavaScript/CSS Header and Output
        /**	----------------------------------------*/

        @header("Content-type: text/plain");
		
		exit($output);
	}
	/* END ajax_autocomplete() */
	
	
	/**	----------------------------------------
	/**	AJAX tag suggest for EE 1.x
	/**	---------------------------------------*/
	
	public function tag_suggest()
    {
    	ee()->lang->loadfile('tag');
		
		/**	----------------------------------------
		/**	Clean str
		/**	----------------------------------------*/
		
		$str	= ( ee()->input->post('str') === FALSE ) ? '': $this->_clean_str( ee()->input->post('str') );
		
		/**	----------------------------------------
		/**	Create array
		/**	----------------------------------------*/
		
		$arr	= str_replace( "||", ' ', $str );
		
		/**	----------------------------------------
		/**	Handle existing
		/**	----------------------------------------*/
		
		$existing = array();
		
		if ( ee()->input->get_post('existing') !== FALSE )
		{
			if (APP_VER < 2.0)
			{
				$existing = explode( "||", ee()->security->xss_clean( ee()->input->post('existing') ) );
			}
			else
			{
				/** --------------------------------------------
				/**  Delimiter
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
			
				$existing = explode( $delim, ee()->security->xss_clean( ee()->input->post('existing') ) );
			}
		}
		
		/**	----------------------------------------
		/**	Query DB
		/**	----------------------------------------*/
		
		$this->_db_charset_switch('UTF-8');

		$sql = "SELECT DISTINCT tag_name AS name 
			    FROM 			exp_tag_tags 
			    WHERE 			tag_name NOT IN ('".implode( "','", ee()->db->escape_str( $existing ) )."')";
		
		if (ee()->input->get_post('msm_tag_search') !== 'y')
		{
			$sql .= " AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'";
		}
		
		$sql .= " ORDER BY total_entries DESC LIMIT 50";
		
		$query = ee()->db->query($sql);
		
		$this->_db_charset_switch('default');
		
		$return_tags = array();
		
		if (function_exists('mb_stristr'))
		{
			foreach($query->result_array() as $row)
			{
				if (mb_stristr ( $arr, $row['name']))
				{
					$return_tags[] = $row;
				}
			}
		}
		else
		{	
			foreach($query->result_array() as $row)
			{
				if (stristr ( $arr, $row['name']))
				{
					$return_tags[] = $row;
				}
			}
		}
		
		/**	----------------------------------------
		/**	Assemble string
		/**	----------------------------------------*/
		
		if ( sizeof($return_tags) == 0 )
		{
			$return = '<div class="message"><p>'.ee()->lang->line('no_matching_tags').'</p></div>';
		}
		else
		{
			$return	= "<ul>";
			
			foreach ( $return_tags as $row )
			{	
				if ($this->preference('separator') == 'space' AND stristr(' ', $row['name']))
				{
					$row['name'] = '"' . $row['name'] . '"';
				}
				
				$return	.= '<li><a href="#">'.$row['name'].'</a></li>';
			}
			
			$return	.= "</ul>";	
		}
		
		@header("HTTP/1.0 200 OK");
		@header("HTTP/1.1 200 OK");
		
		exit($return);
    }
	/* END _tag_suggest() */
	
    
	/**	----------------------------------------
    /**	 Switches DB Connection Between Default and UTF-8
    /**	----------------------------------------*/

	private function _db_charset_switch($type = 'utf-8')
	{	
		/** --------------------------------------------
        /**  ExpressionEngine 2.x is UTF-8
        /** --------------------------------------------*/
        
		if (APP_VER >= 2.0)
		{
			return;
		}
		
		/** --------------------------------------------
        /**  Determine Current Character Set
        /** --------------------------------------------*/
	
		if ( ! isset($this->cache['character_set_client']))
		{
			$query = ee()->db->query("SHOW VARIABLES");
			
			foreach($query->result_array() as $row)
			{
				$this->cache[$row['Variable_name']] = $row['Value'];
			}
		}
		
		if ($this->cache['character_set_client'] == 'utf8')
		{
			return;
		}
		
		/** --------------------------------------------
        /**  Switch Character Sets
        /** --------------------------------------------*/
		
		if (strtolower($type) == 'default')
		{
			if (ee()->db->dbdriver == 'mysqli')
			{
				@mysqli_query(ee()->db->conn_id, "SET NAMES `".ee()->db->escape_str($this->cache['character_set_client'])."` COLLATE `".ee()->db->escape_str($this->cache['collation_connection'])."`");
			}
			else
			{
				@mysql_query("SET NAMES `".ee()->db->escape_str($this->cache['character_set_client'])."` COLLATE `".ee()->db->escape_str($this->cache['collation_connection'])."`", ee()->db->conn_id);
			}
		}
		else
		{
			if (ee()->db->dbdriver == 'mysqli')
			{
				@mysqli_query(ee()->db->conn_id, "SET NAMES `utf8` COLLATE `utf8_general_ci`");
			}
			else
			{
				@mysql_query("SET NAMES `utf8` COLLATE `utf8_general_ci`", ee()->db->conn_id);
			}
		}
	}
	/** END _db_charset_switch **/

    


	// --------------------------------------------------------------------

	/**
	 * Module Installation
	 *
	 * Due to the nature of the 1.x branch of ExpressionEngine, this	public function is always required.
	 * However, because of the large size of the module the actual code for installing, uninstalling,
	 * and upgrading is located in a separate file to make coding easier
	 *
	 * @access	public
	 * @return	bool
	 */
	public function tag_module_install()
    {
        require_once $this->addon_path.'upd.tag.base.php';
    	
    	$U = new Tag_updater_base();
    	return $U->install();
    }
	/* END Tag_module_install() */    
    
	// --------------------------------------------------------------------

	/**
	 * Module Uninstallation
	 *
	 * Due to the nature of the 1.x branch of ExpressionEngine, this	public function is always required.
	 * However, because of the large size of the module the actual code for installing, uninstalling,
	 * and upgrading is located in a separate file to make coding easier
	 *
	 * @access	public
	 * @return	bool
	 */
	public function tag_module_deinstall()
    {
        require_once $this->addon_path.'upd.tag.base.php';
    	
    	$U = new Tag_updater_base();
    	return $U->uninstall();
    }
    /* END Tag_module_deinstall() */


	// --------------------------------------------------------------------

	/**
	 * Module Upgrading
	 *
	 * This	public function is not required by the 1.x branch of ExpressionEngine by default.  However,
	 * as the install and deinstall ones are, we are just going to keep the habit and include it
	 * anyhow.
	 *
	 * @access	public
	 * @return	bool
	 */
	public function tag_module_update()
    {
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		$this->add_crumb(ee()->lang->line('update_tag_module'));
			$this->cached_vars['form_url'] = $this->base.'&method=tag_module_update';
			return $this->ee_cp_view('update_module.html');
		}
    
    	require_once $this->addon_path.'upd.tag.base.php';
    	
    	$U = new Tag_updater_base();
    	
    	if ($U->update() !== TRUE)
    	{
    		return $this->index(ee()->lang->line('update_failure'));
    	}
    	else
    	{
    		return $this->index(ee()->lang->line('update_successful'));
    	}
    }
    /* END Tag_module_update() */


	// --------------------------------------------------------------------

	/**
	 * ajax
	 *
	 * this is a passthrough function from ext in order to allow CP view 
	 * calls without auto CP template wrapping in 2.x
	 *
	 * @access	public
	 * @return	string	result of passed function
	 */
	public function ajax()
	{
		if ( ee()->input->get('ajax') 	=== FALSE 	OR 
			 ee()->input->get('method') === FALSE 	OR
			 ee()->input->get('ajax') 	!= 'solspace_tag_module')
		{
			return '';
		}
		
		$method = ee()->input->get('method');
		
		if (method_exists($this, $method))
		{
			return $this->$method();
		}
		else
		{
			return '';
		}
	}


	// --------------------------------------------------------------------

	/**
	 * _error_message
	 *
	 * shows a display error when something is wrong
	 * 
	 * @access	public
	 * @return	null	sets an error message output
	 */	
	public function _error_message($message)
	{
		return ee()->output->show_user_error('submission', $message);
	}
}

/* END CLASS Tag_mcp */
?>