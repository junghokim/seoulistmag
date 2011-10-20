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
 * Tag Module Class - Data Models
 *
 * Data Models for the Tag Module
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/modules/tag/data.tag.php
 */
 
if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/data.addon_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/data.addon_builder.php';
}

class Tag_data extends Addon_builder_data_bridge {

	// --------------------------------------------------------------------
	
	/**
	 * Get the Preference for the Module for the Current Site
	 *
	 * @access	public
	 * @param	array	Array of Channel/Weblog IDs
	 * @return	array
	 */
    
	function get_module_preferences( )
    {
 		global $DB, $PREFS;
 		
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')]))
 		{
 			return $this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')];
 		}
 		
 		$this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')] = array();
 		
 		/** --------------------------------------------
        /**  Perform the Actual Work
        /** --------------------------------------------*/
        
        $possible_params = array('where', 'order_by', 'limit');
        
        $query = ee()->db->query("SELECT * FROM exp_tag_preferences 
        						  WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'");
        					 
        foreach($query->result_array() as $row)
        {
        	$this->cached[$cache_name][$cache_hash][$row['site_id']][$row['tag_preference_name']] = $row['tag_preference_value'];
        }
       
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')];	
    }
    /* END get_module_preferences() */


	// --------------------------------------------------------------------
	
	/**
	 * Get the ids of items that have names
	 *
	 * @access	public
	 * @param	bool	use cache or no (helpful when making changes in the middle of a document)	
	 * @return	array 	Array of Channel/Weblog IDs
	 */
    
	public function get_tab_channel_ids( $use_cache = TRUE )
    {
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if ($use_cache AND isset($this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')]))
 		{
 			return $this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')];
 		}

		$ids_with_names = array();
		
		$prefs = $this->get_module_preferences($use_cache);
		
		//just find the prefs that are tab name related
		//we want an array of the channel ID numbers with the tab name as a value
		foreach($prefs as $key => $value)
		{
			if ( substr($key, -18) == "_publish_tab_label" AND ! in_array($value, array('', NULL), TRUE))
			{
				$num 					= str_replace('_publish_tab_label', '', $key);
				$ids_with_names[$num] 	= $prefs[$key];
			}
		}

		//set false for if statements if empty
		$ids_with_names = (count($ids_with_names) > 0) ? $ids_with_names : FALSE;

		//cache result (if $use_cache is false, this will still write the unchache result to cache)
		$this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')] = $ids_with_names;

 		return $this->cached[$cache_name][$cache_hash][ee()->config->item('site_id')];	
	}

	// --------------------------------------------------------------------
	
}
// END CLASS Tag_data