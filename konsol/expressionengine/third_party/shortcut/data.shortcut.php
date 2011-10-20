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
 * Shortcut - Data Models
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/data.shortcut.php
 */
 
if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/data.addon_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/data.addon_builder.php';
}

class Shortcut_data extends Addon_builder_data_bridge
{
	var $cached			= array();
	var $default_prefs	= array(
		'shortcut_base'		=> '',
		'shortcut_prefix'	=> 'x'
	);

	// --------------------------------------------------------------------
	
	/**
	 * Get hits
	 *
	 * @access	public
	 * @param	params	shortcut_id
	 * @return	array
	 */
    
	function get_hits( $shortcut_id = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( is_numeric( $shortcut_id ) === FALSE ) return 0;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $shortcut_id ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = 0;
 		
 		/** --------------------------------------------
        /**  Grab hits
        /** --------------------------------------------*/
		
		$sql = "SELECT shortcut_id, hits
			FROM exp_shortcut_hits";
		
		$query = ee()->db->query( $sql );
		
		foreach( $query->result_array() as $row)
		{
			$this->cached[$cache_name][ $this->_imploder( array( $row['shortcut_id'] ) ) ] = $row['hits'];
		}
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash];	
    }
    
    /* End get hits */

	// --------------------------------------------------------------------
	
	/**
	 * Get next shortcut
	 *
	 * @access	public
	 * @param	params	site_id
	 * @return	array
	 */
    
	function get_next_shortcut()
    { 		
        /** --------------------------------------------
        /**  Use empties
        /** --------------------------------------------*/
        
        $query	= ee()->db->query(
        	"SELECT shortcut_id, shortcut
        	FROM exp_shortcut_shortcuts
        	WHERE full_url = ''
        	AND shortcut != ''
        	ORDER BY shortcut_id ASC
        	LIMIT 1"
        );
        
        if ( $query->num_rows() > 0 )
        {
			$this->cached['get_next_shortcut_id'] = $query->row('shortcut_id');
        	return $query->row('shortcut');
        }
        
 		/** --------------------------------------------
        /**  Grab prefix pref
        /** --------------------------------------------*/
        
        if ( ( $prefix = $this->get_preference( ee()->config->item('site_id'), 'shortcut_prefix' ) ) === FALSE )
        {
        	$prefix	= '';
        }
        
        /** --------------------------------------------
        /**  Insert shortcut
        /** --------------------------------------------*/
        
        ee()->db->query(
        	ee()->db->insert_string(
        		'exp_shortcut_shortcuts',
        		array(
					'entry_date'	=> ee()->localize->now
				)
			)
		);
        														
        $id = ee()->db->insert_id();
 		
 		$this->cached['get_next_shortcut_id'] = $id;
        
        /** --------------------------------------------
        /**  Build Hash
        /** --------------------------------------------*/
        
        // 26 letters in 2 cases 10 digits
        
        $hash   = rand( 0,9 );
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = strlen($chars); 
        
        while ($id > ($length - 1))
        {
        	$r  = $id % $length;
        	$id = $id / $length;
        	$hash .= $chars[$r];
        }
        
        $hash .= $chars[$id];
        
        /** --------------------------------------------
        /**  Update the record with the shortcut
        /** --------------------------------------------*/
        
        $update	= ee()->db->update_string(
			'exp_shortcut_shortcuts',
			array(
				'shortcut'		=> $prefix . $hash
			),
			array(
				'shortcut_id'	=> $this->cached['get_next_shortcut_id']
			)
		);
        
        ee()->db->query( $update );
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
		return $prefix . $hash;
    }
    
    /* End get next shortcut */

	// --------------------------------------------------------------------
	
	/**
	 * Get preference
	 *
	 * @access	public
	 * @param	params	site_id
	 * @return	array
	 */
    
	function get_preference( $site_id = '', $pref = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( $pref == '' ) return FALSE;
        
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id, $pref ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Grab prefs
        /** --------------------------------------------*/
        
        $prefs	= $this->get_preferences( $site_id );
 		
 		/** --------------------------------------------
        /**  Check for our pref
        /** --------------------------------------------*/
        
        if ( isset( $prefs[ $pref ] ) === FALSE )
        {
        	return FALSE;
        }
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash]	= $prefs[ $pref ];	
    }
    
    /* End get preference */

	// --------------------------------------------------------------------
	
	/**
	 * Get preferences
	 *
	 * @access	public
	 * @param	params	site_id
	 * @return	array
	 */
    
	function get_preferences( $site_id = '' )
    { 		
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = $this->default_prefs;
 		
 		/** --------------------------------------------
        /**  Grab prefs
        /** --------------------------------------------*/
		
		$sql = 'SELECT pref_name, pref_value
		FROM exp_shortcut_preferences
		WHERE site_id = ' . ee()->db->escape_str( $site_id );
		
		$query = ee()->db->query( $sql );
		
		foreach( $query->result_array() as $row)
		{
			$this->cached[$cache_name][$cache_hash][ $row['pref_name'] ] = $row['pref_value'];
		}
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash];	
    }
    
    /* End get preferences */

	// --------------------------------------------------------------------
	
	/**
	 * Get shortcut
	 *
	 * @access	public
	 * @param	integer	site_id
	 * @param	string	url
	 * @return	array
	 */
    
	function get_shortcut( $site_id = '', $url = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( $site_id == '' OR $url == '' ) return FALSE;
        
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id, $url ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Grab url
        /** --------------------------------------------*/
        
        $sql	= "SELECT shortcut FROM exp_shortcut_shortcuts WHERE site_id = " . ee()->db->escape_str( $site_id ) . " AND full_url = '" . ee()->db->escape_str( $url ) . "'";
        
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return $this->cached[$cache_name][$cache_hash] = FALSE;
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash]	= $query->row('shortcut');	
    }
    
    /* End get shortcut */

	// --------------------------------------------------------------------
	
	/**
	 * Get shortcut id
	 *
	 * @access	public
	 * @param	integer	site_id
	 * @param	string	shortcut
	 * @return	array
	 */
    
	function get_shortcut_id( $site_id = '', $shortcut = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( $site_id == '' OR $shortcut == '' ) return FALSE;
        
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id, $shortcut ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Grab url
        /** --------------------------------------------*/
        
        $sql	= "SELECT shortcut_id FROM exp_shortcut_shortcuts WHERE site_id = " . ee()->db->escape_str( $site_id ) . " AND shortcut = '" . ee()->db->escape_str( $shortcut ) . "' LIMIT 1";
        
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return $this->cached[$cache_name][$cache_hash];
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash] = $query->row('shortcut_id');
    }
    
    /* End get shortcut id */

	// --------------------------------------------------------------------
	
	/**
	 * Get url
	 *
	 * @access	public
	 * @param	integer	site_id
	 * @param	string	shortcut
	 * @return	array
	 */
    
	function get_url( $site_id = '', $shortcut = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( $site_id == '' OR $shortcut == '' ) return FALSE;
        
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id, $shortcut ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Grab url
        /** --------------------------------------------*/
        
        $sql	= "SELECT shortcut_id, full_url FROM exp_shortcut_shortcuts WHERE BINARY shortcut = '" . ee()->db->escape_str( $shortcut ) . "'";
        
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 )
        {
        	$this->cached[ 'get_shortcut_id' ][$cache_hash] = FALSE;
        	return $this->cached[$cache_name][$cache_hash] = FALSE;
        }
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		$this->cached[ 'get_shortcut_id' ][$cache_hash]	= $query->row('shortcut_id');
 		return $this->cached[$cache_name][$cache_hash]	= $query->row('full_url');
    }
    
    /* End get url */

	// --------------------------------------------------------------------
	
	/**
	 * Set hits
	 *
	 * @access	public
	 * @param	params	shortcut_id
	 * @return	array
	 */
    
	function set_hits( $shortcut_id = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( is_numeric( $shortcut_id ) === FALSE ) return FALSE;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $shortcut_id ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Exists?
        /** --------------------------------------------*/
		
		$sql = "SELECT COUNT(*) AS count
			FROM exp_shortcut_hits
			WHERE shortcut_id = " . ee()->db->escape_str( $shortcut_id );
		
		$query = ee()->db->query( $sql );
		
		if ( $query->row('count') == 0 )
		{
			ee()->db->query(
				ee()->db->insert_string(
					'exp_shortcut_hits',
					array(
						'shortcut_id'	=> $shortcut_id,
						'hits'			=> 1
					)
				)
			);
		}
		else
		{
			ee()->db->query(
				"UPDATE exp_shortcut_hits
				SET hits = hits + 1
				WHERE shortcut_id = " . ee()->db->escape_str( $shortcut_id ) 
			);
		}
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash]	= TRUE;
    }
    
    /* End set hits */

	// --------------------------------------------------------------------
	
	/**
	 * Set preferences
	 *
	 * @access	public
	 * @param	params	site_id
	 * @return	array
	 */
    
	function set_preferences( $site_id = '', $prefs = array() )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( empty( $prefs ) ) return FALSE;
        
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
 		
 		/** --------------------------------------------
        /**  Update
        /** --------------------------------------------*/
        
        foreach ( $prefs as $key => $val )
        {        	
        	if ( isset( $this->default_prefs[ $key ] ) === TRUE )
        	{        	
				ee()->db->query(
					ee()->db->update_string(
						'exp_shortcut_preferences',
						array(
							'pref_value'	=> $val
						),
						array(
							'site_id'		=> $site_id,
							'pref_name'		=> $key
						)
					)
				);
        	}
        }
        
 		/** --------------------------------------------
        /**  Return
        /** --------------------------------------------*/
        
        return TRUE;
    }
    
    /* End set preferences */

	// --------------------------------------------------------------------
	
	/**
	 * Set shortcut
	 *
	 * @access	public
	 * @param	string shortcut
	 * @param	string url
	 * @return	array
	 */
    
	function set_shortcut( $shortcut_id = '', $shortcut = '', $custom_shortcut = '', $url = '', $autogenerated = 'y', $edit_mode = FALSE )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( empty( $shortcut ) OR empty( $url ) ) return FALSE;
        
 		/** --------------------------------------------
        /**  Set site id
        /** --------------------------------------------*/
        
        $site_id	= ee()->config->item('site_id');
        
 		/** --------------------------------------------
        /**  Custom shortcut
        /** --------------------------------------------*/
        
        $custom_shortcut	= ( $custom_shortcut == '' ) ? $shortcut: $custom_shortcut;
 		
 		/** --------------------------------------------
        /**  URL
        /** --------------------------------------------*/
		
		if ( strncasecmp( $url, 'http', 4 ) != 0 )
		{
			$url = 'http://' . $url;
		}
 		
 		/** --------------------------------------------
        /**  Has this url already been saved and are we in autogenerate mode?
        /** --------------------------------------------*/
        
        if ( $autogenerated == 'y' AND $edit_mode === FALSE )
        {
			$query	= ee()->db->query(
				"SELECT shortcut, full_url
				FROM exp_shortcut_shortcuts
				WHERE full_url = '" . ee()->db->escape_str( $url ) . "'
				OR shortcut = '" . ee()->db->escape_str( $shortcut ) . "'
				LIMIT 1"
			);
			
			if ( $query->num_rows > 0 )
			{
				if ( $query->row('full_url') == $url ) return $query->row('shortcut');
				
				if ( $query->row('shortcut') == $custom_shortcut )
				{
					$custom_shortcut	= $this->get_next_shortcut();
				}
			}
		}
 		
 		/** --------------------------------------------
        /**  Update
        /** --------------------------------------------*/
        
		ee()->db->query(
			ee()->db->update_string(
				'exp_shortcut_shortcuts',
				array(
					'site_id'		=> $site_id,
					'shortcut'		=> $custom_shortcut,
					'full_url'		=> $url,
					'autogenerated'	=> $autogenerated,
					'entry_date'	=> ee()->localize->now,
					'edit_date'		=> ee()->localize->now
				),
				array(
					'shortcut_id'	=> $shortcut_id
				)
			)
		);
        
 		/** --------------------------------------------
        /**  Return
        /** --------------------------------------------*/
        
        return $custom_shortcut;
    }
    
    /* End set shortcut */

	// --------------------------------------------------------------------
	
	/**
	 * Shortcut exists
	 *
	 * @access	public
	 * @param	params	shortcut
	 * @return	array
	 */
    
	function shortcut_exists( $shortcut = '' )
    {
 		/** --------------------------------------------
        /**  Validate
        /** --------------------------------------------*/
        
        if ( $shortcut == '' ) return FALSE;
        
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
 		
 		/** --------------------------------------------
        /**  Check DB
        /** --------------------------------------------*/
        
        $sql	= "SELECT COUNT(*) AS count
			FROM exp_shortcut_shortcuts
			WHERE BINARY shortcut = '" . ee()->db->escape_str( $shortcut ) . "'";
        
        $query	= ee()->db->query( $sql );
 		
 		/** --------------------------------------------
        /**  Exists?
        /** --------------------------------------------*/
        
        if ( $query->row('count') > 0 )
        {
        	return $this->cached[$cache_name][$cache_hash] = 'y';
        }
        
 		/** --------------------------------------------
        /**  Return
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash]	= 'n';	
    }
    
    /* End shortcut exists */

	// --------------------------------------------------------------------	
}
// END CLASS Shortcut_data