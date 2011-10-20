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
 * Tag - Actions
 *
 * @package 	Solspace:Tag
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/tag/act.tag.php
 */

if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/extension_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/extension_builder.php';
}

class Tag_actions extends Addon_builder_bridge {

	public $current_char_set = '';
    

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function __construct()
    {		
    	parent::Addon_builder_bridge();

		// 2.x installs are all utf-8
		if (APP_VER >= 2.0)
		{
			$this->current_char_set = 'utf-8';
		}
	}
	// END constructor
	
	
	// --------------------------------------------------------------------

	/**
	 *	Database Character Set Switch
	 *
	 *	Used because the EE 1.x database was not UTF-8, which was causing a problem when Tag 
	 *	tried to have international character support.  EE 2.x is magically delicious and UTF-8
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		null
	 */

	public function db_charset_switch($type = 'utf-8')
	{
		if (APP_VER >= 2.0)
		{
			return;
		}
	
		if ( ! isset($this->cache['character_set_client']))
		{
			$query = ee()->db->query("SHOW VARIABLES");

			foreach($query->result_array() as $row)
			{
				if ($row['Variable_name'] == 'character_set_client')
				{
					$this->cache['character_set_client'] = $row['Value'];
				}
				
				if ($row['Variable_name'] == 'collation_connection')
				{
					$this->cache['collation_connection'] = $row['Value'];
				}
			}
		}

		if ($this->cache['character_set_client'] == 'utf8')
		{
			$this->current_char_set = 'utf-8';
			
			return;
		}

		if (strtolower($type) == 'default')
		{
			@mysql_query(
				"SET NAMES `" . 
					ee()->db->escape_str($this->cache['character_set_client']) . 
					"` COLLATE `" . ee()->db->escape_str($this->cache['collation_connection']) . "`", 
				ee()->db->conn_id
			);
			
			$this->current_char_set = $this->cache['character_set_client'];
		}
		else
		{
			@mysql_query("SET NAMES `utf8` COLLATE `utf8_general_ci`", ee()->db->conn_id);
			
			$this->current_char_set = 'utf-8';
		}
	}
	// End DB UTF-8 Switch


	// --------------------------------------------------------------------

	/**
	 * merges the data for two tags, the first being the dominant
	 * @access 	public
	 * @param	(string) Tag that getting merged to (dominant)
	 * @param	(string) Tag that getting merged in	(recessive)
	 * @param	(int) 	 site_id in case we need to loop this for everything
	 * @return 	(bool)	 success?
	 */
	
	public function merge_tags($to_tag = '', $from_tag = '', $site_id = 0)
	{
		//cant work with blanks
		if ( $to_tag === '' OR $from_tag === '') return FALSE;
		
		//clean site_id
		$site_id 	= ee()->db->escape_str(
			(is_numeric($site_id) AND $site_id != 0) ? $site_id : ee()->config->item('site_id')
		);
		
		//--------------------------------------------  
		//	Charset check. Need to make sure its UTF-8
		//	so we can correctly work with foreign sets
		//	also setting a bool so we dont undo another
		//	charset change somewhere else
		//--------------------------------------------
		
		$change_back = FALSE;
				
		if ( $this->current_char_set !== 'utf-8' )
		{
			$change_back = TRUE;
			
			//set utf8
			$this->db_charset_switch();
		}				
				
		//--------------------------------------------  
		//	get tag_ids
		//--------------------------------------------		
		
		//have to use binary here because subsequent 
		//spaces count as the same as none
		//and we could get false positives
		$tquery = ee()->db->query(
			"SELECT tag_id, tag_name 
			 FROM 	exp_tag_tags
			 WHERE	site_id = $site_id
			 AND	BINARY tag_name
			 IN		('" . ee()->db->escape_str($to_tag) . "',
					 '" . ee()->db->escape_str($from_tag) . "')"
		);
		
		//did we get both results? cannot do anything with one
		if ($tquery->num_rows() < 2 ) 
		{
			//change charset back?
			if ($change_back) 
			{
				$this->db_charset_switch('default');
			}
			
			return FALSE;
		}
		
		//--------------------------------------------  
		//	set ids for later use
		//--------------------------------------------
		
		$from_id = 0;
		$to_id	 = 0;
		
		//this should only ever be 2 ..we hope
		foreach ($tquery->result_array() as $row)
		{
			if ($row['tag_name'] == $to_tag)
			{
				$to_id = $row['tag_id'];
			}
			
			if ($row['tag_name'] == $from_tag)
			{
				$from_id = $row['tag_id'];
			}
		}		
		
		//--------------------------------------------  
		//	convert tag entries
		//--------------------------------------------		
		
		$from_data_query = ee()->db->query(
			"SELECT entry_id, type
			 FROM 	exp_tag_entries
			 WHERE	site_id = $site_id
			 AND	tag_id 	= '" .  ee()->db->escape_str($from_id) . "'"
		);
	
		//if there are any entries, lets convert them
		if ($from_data_query->num_rows() > 0) 
		{
			$entry_ids = array();
						
			//seperate data by id and type 
			//because the entry tables could have the same ID
			foreach($from_data_query->result_array() as $row)
			{
				$entry_ids[$row['type']][] = $row['entry_id'];
			}
			
			$sql = "SELECT 	entry_id, type 
			 		FROM 	exp_tag_entries
			 		WHERE	site_id = $site_id
			 		AND		tag_id 	= '" .  ee()->db->escape_str($to_id) . "'";
			
			//need to check entry_id AND type because there is no primary key
			$first = TRUE;
			
			$sql .= ' AND (';
			
			foreach ($entry_ids as $type => $type_ids)
			{
				if ($first)
				{
					$first 	= FALSE;
				}
				else
				{
					$sql 	.= ' OR ';
				}
				
				$sql .= " ( type = '" . ee()->db->escape_str($type) . "' AND 
							entry_id IN (" . implode(',', ee()->db->escape_str($type_ids)) . ") )";
			}
			
			//ends the paran from AND (
			$sql .= " )";		
			
			$to_data_query = ee()->db->query($sql);
	
			//if there are any matches, then we already have 
			//tagged this entry and dont need to convert
			//so we remove the matching items from the array of items to convert
			if ($to_data_query->num_rows() > 0) 
			{	
				$tagged_ids = array();	
				
				foreach ($to_data_query->result_array() as $row)
				{
					$tagged_ids[$row['type']][] = $row['entry_id'];
				}
				
				foreach ($entry_ids as $type => $type_ids)
				{
					if (isset($tagged_ids[$type]))
					{
						$entry_ids[$type] = array_diff_assoc($entry_ids[$type], $tagged_ids[$type]);						
					}
				}
			}
			
			//now with our cleaned arrays, we need to update the unique tags to be the to_tags id
			foreach ($entry_ids as $type => $type_ids)
			{
				if (empty($type_ids)) continue;
								
				ee()->db->query(
					ee()->db->update_string(
						'exp_tag_entries',
						array(
							'tag_id' => $to_id
						),
						"site_id 	= $site_id	AND
						 type 		= '" . ee()->db->escape_str($type) . "' AND 
						 tag_id		= '" . ee()->db->escape_str($from_id) . "' AND
						 entry_id 	IN (" . implode(',', ee()->db->escape_str($type_ids)) . ")"
					)
				);
			}
		}		

		//--------------------------------------------  
		//	cleanup (by id, tis unique)
		//--------------------------------------------
		
		//remove from_tag
		ee()->db->query(
			"DELETE FROM exp_tag_tags
			 WHERE		 tag_id = '" . ee()->db->escape_str($from_id) . "'"
		);
		
		//remove from_tag
		ee()->db->query(
			"DELETE FROM exp_tag_entries
			 WHERE		 tag_id = '" . ee()->db->escape_str($from_id) . "'"
		);
		
		//recount main tag
		$this->recount_tags($to_id);
		
		//change charset back?
		if ($change_back) 
		{
			$this->db_charset_switch('default');
		}
		
		return TRUE;
	}
	//end merge tags


	// --------------------------------------------------------------------

	/**
	 * resets tag counts in the db
	 * @access 	public
	 * @param	(array/string) array of tag ids or a singular tag id
	 * @return 	(null)
	 */
	
	public function recount_tags($tag_ids = array())
	{
		//array?
		if ( ! is_array($tag_ids))
		{
			if (is_numeric($tag_ids))
			{
				$tag_ids = array($tag_ids);
			}
			else
			{
				return;
			}
		}
		
		//cannot work without data
		if ( count( $tag_ids ) == 0 ) return;
		
		// ----------------------------------------
		// Zero out
		// ----------------------------------------

		foreach ( $tag_ids as $tag_id )
		{
			ee()->db->query( 
				ee()->db->update_string(
					'exp_tag_tags',
					array(	
						'total_entries'		=> 0, 
						'channel_entries'	=> 0, 
						'gallery_entries'	=> 0 
					), 
					array( 
						'tag_id' => $tag_id 
					) 
				) 
			);
		}

		//	----------------------------------------
		//	Get counts
		//	----------------------------------------

		$query	= ee()->db->query(  
			"SELECT tag_id, type 
			 FROM 	exp_tag_entries 
			 WHERE  tag_id 
			 IN 	('" . implode( "','", ee()->db->escape_str($tag_ids) ) . "')" 
		);

		//	----------------------------------------
		//	Array counts
		//	----------------------------------------

		$counts	= array();

		foreach ( $query->result_array() as $row )
		{
			$counts[ $row['tag_id'] ][ $row['type'] ][]	= 1;
		}

		//	----------------------------------------
		//	Update counts
		//	----------------------------------------

		foreach ( $counts as $key => $val )
		{
			$data = array();

			$data['channel_entries']	= ( isset( $val['channel'] ) ) ? count( $val['channel'] ) : 0;
			$data['gallery_entries']	= ( isset( $val['gallery'] ) ) ? count( $val['gallery'] ) : 0;
			$data['total_entries']		= $data['channel_entries'] + $data['gallery_entries'];

			ee()->db->query( 
				ee()->db->update_string( 
					'exp_tag_tags', 
					$data, 
					array( 
						'tag_id' => $key 
					) 
				) 
			);
		}
	}
	//END recount_tags
	
}
/* END Tag_actions Class */