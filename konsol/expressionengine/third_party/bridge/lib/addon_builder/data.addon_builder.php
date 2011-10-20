<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Bridge - Expansion
 *
 * @package		Bridge:Expansion
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2010, Solspace, Inc.
 * @link		http://solspace.com/docs/
 * @version		1.1.5
 * @filesource 	./system/bridge/
 * 
 */
 
 /**
 * Data Models
 *
 * The parent class for all of the Data Model classes in Bridge enabled Add-Ons.  Helps with caching
 * of common queries.
 *
 * @package 	Bridge:Expansion
 * @subpackage	Add-On Builder
 * @category	Data
 * @author		Solspace DevTeam
 * @link		http://solspace.com/docs/
 * @filesource 	./system/bridge/lib/addon_builder/data.addon_builder.php
 */
 
if (APP_VER < 2.0)
{
	require_once PATH . 'bridge/lib/addon_builder/addon_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/addon_builder.php';
}

class Addon_builder_data_bridge {

	public $cached			= array();
	public $nomenclature	= array(
		'site_weblog_preferences'	=> 'site_channel_preferences',
		'can_admin_weblogs'			=> 'can_admin_channels',
		'weblog_id'					=> 'channel_id',
		'blog_name'					=> 'channel_name',
		'blog_title'				=> 'channel_title',
		'blog_url'					=> 'channel_url',
		'blog_description'			=> 'channel_description',
		'blog_lang'					=> 'channel_lang',
		'weblog_max_chars'			=> 'channel_max_chars',
		'weblog_notify'				=> 'channel_notify',
		'weblog_require_membership'	=> 'channel_require_membership',
		'weblog_html_formatting'	=> 'channel_html_formatting',
		'weblog_allow_img_urls'		=> 'channel_allow_img_urls',
		'weblog_auto_link_urls'		=> 'channel_auto_link_urls',
		'weblog_notify_emails'		=> 'channel_notify_emails',
		'field_pre_blog_id'			=> 'field_pre_channel_id'
	);
	
	//names of methods from AOB that should NOT be used by call
	//if the method already exists in this class, then the point is moot
	//because __call will not be activated
	private $aob_non_static_friendly = array(
		'actions',
		'database_version',
		'preference',
		'extensions_enabled',
		'ee_cp_view',
		'retrieve_remote_file',
		'cycle',
		'output',
		'build_crumbs',
		'add_crumb',
		'fetch_stylesheet',
		'view',
		'file_view'
	);
	
	public $parent_aob_instance;
	
    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	object	this should be an instance of the parent object
	 * @return	null
	 */
    
	function Addon_builder_data_bridge(&$parent_aob_instance = FALSE)
    {	
		//this way we have a pointer to AOB
		//however, since this gets called from the child constructor,
		//AOB has to point itself to it.
		//this is just here in case
		if (is_object($parent_aob_instance))
		{
			$this->parent_aob_instance =& $parent_aob_instance;
		}
	
    	/** --------------------------------------------
        /**  Prepare the Cache
        /** --------------------------------------------*/
    	
    	$class = get_class($this);
    	
    	if ( ! isset(ee()->session) OR ! is_object(ee()->session))
    	{
    		if ( ! isset($GLOBALS['bridge']['cache']['addon_builder']['data'][$class]))
    		{
    			$GLOBALS['bridge']['cache']['addon_builder']['data'][$class] = array();
    		}
    		
    		$this->cached =& $GLOBALS['bridge']['cache']['addon_builder']['data'][$class];
    	}
    	else
    	{
    		if ( ! isset(ee()->session->cache['bridge']['addon_builder']['data'][$class]))
 			{
 				if( isset($GLOBALS['bridge']['cache']['addon_builder']['data'][$class]))
				{
					ee()->session->cache['bridge']['addon_builder']['data'][$class] = $GLOBALS['bridge']['cache']['addon_builder']['data'][$class];
				}
 				else
 				{
 					ee()->session->cache['bridge']['addon_builder']['data'][$class] = array();
 				}
 			}
 		
 			$this->cached =& ee()->session->cache['bridge']['addon_builder']['data'][$class];
 		} 		
    }
    /* END Addon_builder_data_bridge() */


    // --------------------------------------------------------------------

	/**
	 * __call
	 *
	 * intercepts calls to methods that do not exist. Magic ;)
	 * if the method is in AOB, return it
	 *
	 * @access	public
	 * @return	string
	 */

	public function __call($method, $args) 
	{			
		//attempt to first call it on the parent AOB object
		if (isset($this->parent_aob_instance) AND
			is_object($this->parent_aob_instance) AND 
			is_callable(array($this->parent_aob_instance, $method))) 
		{					
			return call_user_func_array(array($this->parent_aob_instance, $method), $args);
		}
		
		//we have an array of items that should NOT be attempted to call
		if ( ! in_array($method, $this->aob_non_static_friendly) AND 
			 is_callable(array('Addon_builder_bridge', $method))) 
		{						
			return call_user_func_array(array('Addon_builder_bridge', $method), $args);
		}
	}
 	//end __call
    

	// --------------------------------------------------------------------
	
	/**
	 * Translate Keys from 2.0 to 1.0
	 *
	 * @access	public
	 * @param	array
	 * @return	array
	 */
    
	function translate_keys($data = array())
    {
    	/** --------------------------------------------
        /**  No Process for Non, Empty, or Already 2.0 Ready Arrays
        /** --------------------------------------------*/
    
 		if ( ! is_array($data) OR count($data) == 0 OR APP_VER >= 2.0)
 		{
 			return $data;
 		}
 		
 		/** --------------------------------------------
        /**  An Entire Result Array?  Process Each
        /** --------------------------------------------*/
 		
 		$first = current($data);
 		
 		if ( is_array($first))
 		{
 			foreach($data as $key => $value)
 			{
 				$data[$key] = $this->translate_keys($value);
 			}
 			
 			return $data;
 		}
 		
 		/** --------------------------------------------
        /**  Set New Values, Remove Old Values
        /** --------------------------------------------*/
		
		foreach($this->nomenclature as $old => $new)
		{
			if ( isset($data[$old]))
			{
				$data[$new] = $data[$old];
				unset($data[$old]);
			}
		}
		
		return $data;
 	}
 	/* END translate_keys() */


	// --------------------------------------------------------------------
	
	/**
	 * Get author id from entry id
	 *
	 * @access	public
	 * @param	params	entry id
	 * @return	integer
	 */
    
	function get_author_id_from_entry_id( $entry_id = '' )
    {
    	if ( is_numeric( $entry_id ) === FALSE ) return FALSE;
    
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->get_channel_id_from_entry_id( $entry_id );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
        
        return $this->cached[$cache_name][$cache_hash];
	}
	
	/*	End get author id from entry id */

	// --------------------------------------------------------------------
	
	/**
	 * Get channel id from entry id
	 *
	 * @access	public
	 * @param	params	entry id
	 * @return	integer
	 */
    
	function get_channel_id_from_entry_id( $entry_id = '' )
    {
    	if ( is_numeric( $entry_id ) === FALSE ) return FALSE;
    
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Grab from DB
        /** --------------------------------------------*/
        
        $query	= ee()->db->query( "SELECT author_id, " . $this->sc->db->channel_id . " AS channel_id FROM " . $this->sc->db->channel_titles . " WHERE entry_id = '" . ee()->db->escape_str( $entry_id ) . "' LIMIT 1" );
        
        if ( $query->num_rows > 0 )
        {
        	$this->cached[$cache_name][$cache_hash]	= $query->row('channel_id');
        	$this->cached['get_author_id_from_entry_id'][$cache_hash]	= $query->row('author_id');
        }
        
        return $this->cached[$cache_name][$cache_hash];
	}
	
	/*	End get channel id from entry id */

	// --------------------------------------------------------------------
	
	/**
	 * List of Installations Sites
	 *
	 * @access	public
	 * @param	params	MySQL clauses, if necessary
	 * @return	array
	 */
    
	function get_sites()
    {
 		global $DB, $PREFS, $SESS;
 		
 		/** --------------------------------------------
        /**  SuperAdmins Alredy Have All Sites
        /** --------------------------------------------*/
        
        if (is_object($SESS) && isset(ee()->session->userdata['group_id']) && ee()->session->userdata['group_id'] == 1 && isset(ee()->session->userdata['assigned_sites']) && is_array(ee()->session->userdata['assigned_sites']))
        {
        	return ee()->session->userdata['assigned_sites'];
        }
 		
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
 		
 		/** --------------------------------------------
        /**  Perform the Actual Work
        /** --------------------------------------------*/
        
        if (ee()->config->item('multiple_sites_enabled') == 'y')
        {
        	$sites_query = ee()->db->query("SELECT site_id, site_label FROM exp_sites ORDER BY site_label");
		}
		else
		{
			$sites_query = ee()->db->query("SELECT site_id, site_label FROM exp_sites WHERE site_id = '1'");
		}
		
		foreach($sites_query->result_array() as $row)
		{
			$this->cached[$cache_name][$cache_hash][$row['site_id']] = $row['site_label'];
		}
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash];	
    }
    /* END get_sites() */

	// --------------------------------------------------------------------

	
}
// END CLASS Addon_builder_data