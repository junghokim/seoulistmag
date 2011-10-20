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
 * Tag Module Class - Tabs!
 *
 * Handles the adding of Tabs to the Publish Page in EE 2.x
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/expressionengine/third_party/modules/tag/tab.tag.php
 */

if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/module_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
}

class Tag_tab extends Module_builder_bridge
{
	// --------------------------------------------------------------------

	/**
	 *	Constructor
	 *
	 *	@access		public
	 *	@return		null
	 */
	
	public function __construct()
	{
		parent::Module_builder_bridge('tag');
	}
	/* END constructor */
	
	// --------------------------------------------------------------------

	/**
	 *	Publish Tabs
	 *
	 *	Creates the fields that will be displayed on the Publish page for EE 2.x
	 *
	 *	@access		public
	 *	@param		integer
	 *	@param		integer
	 *	@return		array
	 */

	public function publish_tabs($channel_id, $entry_id = '')
	{
		$settings = array();
	
		if (REQ != 'CP')
		{
			return $settings;
		}
		
		// @bugfix - EE 2.x on submit of an entry calls this method with incorrect arguments
		if (is_array($channel_id))
		{
			$entry_id	= $channel_id[1];
			$channel_id	= $channel_id[0];
		}
		
		/** --------------------------------------------
        /**  Delimiter
        /** --------------------------------------------*/
		
		$query	= ee()->db->query( "SELECT tag_preference_value, tag_preference_name FROM exp_tag_preferences
									WHERE tag_preference_name IN ('".ee()->db->escape_str($channel_id)."_publish_tab_label')
									AND site_id = '".ee()->db->escape_str( ee()->config->item('site_id') )."'" );
									
		foreach($query->result_array() as $row)
		{
			if ($row['tag_preference_name'] == $channel_id.'_publish_tab_label')
			{
				$tag_name = $row['tag_preference_value'];
			}
			else
			{
				${$row['tag_preference_name']} = $row['tag_preference_value'];
			}
		}
		
		/** --------------------------------------------
        /**  Do we have a Publish Tab for this Channel?
        /** --------------------------------------------*/
        
        if ( empty($tag_name))
        {
        	return array();
        }
		
		/** --------------------------------------------
        /**  Tag Separator
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
			default				: $delim = "\n"; // Extra slash for JS usage
				break;
		}
		
		/** --------------------------------------------
        /**  Add in our JavaScript/CSS
        /** --------------------------------------------*/
        
		if (is_object(ee()->cp))
		{
			//this is in hopes of preventing double loading of scripts
			
			//jquery autocomplete js
			if ( ! isset(ee()->sessions->cache['solspace']['scripts']['jquery']['autocomplete']) OR
				 ! ee()->sessions->cache['solspace']['scripts']['jquery']['autocomplete'])
			{
				ee()->cp->add_js_script(array( 
					'<script type="text/javascript" charset="utf-8" src="' . 
					ee()->config->item('theme_folder_url') . 
					'solspace_themes/lib/js/jq_plugins/jquery.autocomplete.js"></script>'
				), FALSE);
				
				ee()->sessions->cache['solspace']['scripts']['jquery']['autocomplete'] = TRUE;
			}
		
			//jquery autocomplete css
			if ( ! isset(ee()->sessions->cache['solspace']['styles']['jquery']['autocomplete']) OR
				 ! ee()->sessions->cache['solspace']['styles']['jquery']['autocomplete'])
			{
				ee()->cp->add_js_script(array( 
					'<link rel="stylesheet" type="text/css" media="screen" charset="utf-8" href="' . 
					ee()->config->item('theme_folder_url') . 
					'solspace_themes/lib/css/jquery.autocomplete.css" />'
				), FALSE);
				
				ee()->sessions->cache['solspace']['styles']['jquery']['autocomplete'] = TRUE;
			}
			
			//view files loaded via sessions_end hacks		
			ee()->cp->add_js_script(array( 
				'<script type="text/javascript" charset="utf-8" src="' . 
				$this->base.'&method=tag_publish_javascript'.
				'&ajax=solspace_tag_module&channel_id='.$channel_id.'"></script>'
			), FALSE);
			
			ee()->cp->add_js_script(array( 
				'<link rel="stylesheet" type="text/css" media="screen" charset="utf-8" href="' . 
				$this->base.'&method=tag_publish_css&ajax=solspace_tag_module" />'
			), FALSE);
        
		}

        /** --------------------------------------------
        /**  Current Tags for Entry
        /** --------------------------------------------*/

		$query = ee()->db->query(  "SELECT t.tag_name FROM exp_tag_tags t
									LEFT JOIN exp_tag_entries e ON t.tag_id = e.tag_id
									WHERE e.entry_id = '".ee()->db->escape_str($entry_id)."'
									AND type = 'channel'
									ORDER BY t.tag_name" );
		
		$existing_tags = array();

		foreach ( $query->result_array() as $row )
		{
			/**	----------------------------------------
			/*	We have a space so add quotes around it, if the delimiter is a space too
			/**	----------------------------------------*/

			if ($delim == " " AND strpos($row['tag_name'], ' ') !== FALSE)
			{
				$existing_tags[] = '"'.$row['tag_name'].'"';
			}
			else
			{
				$existing_tags[] = $row['tag_name'];
			}
		}

		/** --------------------------------------------
        /**  Build Fields
        /** --------------------------------------------*/
        
		$settings[] = array(
				'field_id'				=> 'solspace_tag_submit',
				'field_label'			=> ee()->lang->line('tag_field'),
				'field_required' 		=> 'n',
				'field_data'			=> (sizeof($existing_tags) == 0) ? '' : implode($delim, $existing_tags).$delim,
				'field_ta_rows'			=> 6,
				'field_fmt'				=> '',
				'field_instructions' 	=> ee()->lang->line('tag_field_instructions_'.$this->preference('separator')),
				'field_show_fmt'		=> 'n',
				'field_fmt_options'		=> array(),
				'field_pre_populate'	=> 'n',
				'field_text_direction'	=> 'ltr',
				'field_type' 			=> 'textarea',
				'field_show_writemode'	=> 'n'
			);
			
		$settings[] = array(
				'field_id'				=> 'solspace_tag_suggest',
				'field_label'			=> ee()->lang->line('suggest_tags'),
				'field_required' 		=> 'n',
				'field_data'			=> $channel_id,
				'field_instructions' 	=> '',
				'field_type' 			=> 'tag',
				'field_show_writemode'	=> 'n'
			);

		return $settings;
	}
	/* END publish_tabs() */
	
	// --------------------------------------------------------------------

	/**
	 *	Validate Submitted Publish data
	 *
	 *	Allows you to validate the data after the publish form has been submitted but before any 
	 *	additions to the database. Returns FALSE if there are no errors, an array of errors otherwise.
	 *
	 *	@access		public
	 *	@param		array
	 *	@return		bool|array
	 */

	public function validate_publish($params)
	{
		return FALSE;
	}
	/* END validate_publish() */
	
	// --------------------------------------------------------------------

	/**
	 *	Insert Publish Form Data
	 *
	 *	Allows the insertion of data after the core insert/update has been done, thus making 
	 *	available the current $entry_id. Returns nothing.
	 *
	 *	@access		public
	 *	@param		array
	 *	@return		null
	 */
	
	public function publish_data_db($params)
	{
		if ( ! isset($params['mod_data']['solspace_tag_submit']))
		{
			return;
		}
		
		require_once $this->addon_path.'mod.tag'.EXT;
		
		$TAG = new Tag();
		
		$TAG->channel_id	= $params['meta']['channel_id'];
		$TAG->site_id		= $params['meta']['site_id'];
		$TAG->entry_id		= $params['entry_id'];
		$TAG->str			= $params['mod_data']['solspace_tag_submit'];
		$TAG->type			= 'channel';
		
		$TAG->parse();
	}
	/* END publish_data_db() */
	
	// --------------------------------------------------------------------

	/**
	 *	Entry Delete
	 *
	 *	Called near the end of the entry delete function, this allows you to sync your records if 
	 *	any are tied to channel entry_ids.
	 *
	 *	@access		public
	 *	@param		array
	 *	@return		null
	 */

	public function publish_data_delete_db($params)
	{
		require_once $this->addon_path.'mod.tag'.EXT;
		
		$TAG = new Tag();
							
		return $TAG->delete( $params['entry_ids'] );
	}
	/* publish_data_delete_db() */

}
/* END Tag_tab CLASS */

/* End of file tab.tag.php */
/* Location: ./system/expressionengine/third_party/modules/tag/tab.tag.php */