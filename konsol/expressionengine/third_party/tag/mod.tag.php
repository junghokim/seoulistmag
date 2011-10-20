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
 * Tag Module Class - User Side
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:Tag
 * @author		Solspace Dev Team
 * @filesource 	./system/modules/tag/mod.tag.php
 */
 
if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/module_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
}

class Tag extends Module_builder_bridge
{
	public $TYPE;

	public $remote					= FALSE;
	public $batch					= FALSE;

	public $author_id				= '';
	public $tag_id					= '';
	public $tag						= '';
	public $channel_id				= '';
	public $site_id					= '';
	public $entry_id				= '';
	public $old_entry_id			= '';
	public $tag_relevance			= array();
	public $max_relevance			= 0;
	public $str						= '';
	public $tagdata					= '';
	public $site_url				= '';
	public $cp_url					= '';
	public $type					= 'channel';
                                	
	public $existing				= array();
	public $new						= array();
	public $bad						= FALSE;

	// Pagination variables

    public $paginate				= FALSE;
    public $pagination_links		= '';
    public $page_next				= '';
    public $page_previous			= '';
	public $current_page			= 1;
	public $total_pages				= 1;
	public $total_rows				=  0;
	public $p_limit					= '';
	public $p_page					= '';
	public $basepath				= '';
	public $uristr					= '';


    /**
     * contructor
     *
 	 * @access	public
	 * @param	int/string 	channel_id
	 * @param	int/string 	entry_id
	 * @param	string 	 	string of tags		
	 * @return  object 	 	instance of itself of course
     */

	public function __construct( $channel_id = '', $entry_id = '', $str = '' )
	{	
		parent::Module_builder_bridge('tag');
		
		// -------------------------------------
		//  Module Installed and Up to Date? Extensions enabled?
		// -------------------------------------
		
		if ($this->database_version() == FALSE OR 
			$this->version_compare($this->database_version(), '<', TAG_VERSION) OR 
			! $this->extensions_enabled())
		{
			$this->disabled = TRUE;
			
			trigger_error(ee()->lang->line('tag_module_disabled'), E_USER_NOTICE);
		}
		
		
		$this->type = 'channel';

		//	----------------------------------------
		//	 UTF-8
		//	----------------------------------------

		$this->actions()->db_charset_switch('UTF-8');

		if (function_exists ( 'mb_internal_encoding'))
		{
			mb_internal_encoding('UTF-8');
		}

		//	----------------------------------------
		//	 Retrieve Preferences for Module/Site
		//	----------------------------------------

		if (ee()->db->table_exists('exp_tag_preferences'))
		{
			if ( ! isset($this->cache['preferences'][ee()->config->item('site_id')]))
			{
				$this->cache['preferences'][ee()->config->item('site_id')] = array();
				
				$query = ee()->db->query(
					"SELECT * 
					 FROM 	exp_tag_preferences 
					 WHERE 	site_id = '" . ee()->db->escape_str(ee()->config->item('site_id')) . "'"
				);
				
				foreach($query->result_array() as $row)
				{
					$this->{$row['tag_preference_name']} = $row['tag_preference_value'];
				}
			}
		}

		$this->channel_id			= $channel_id;
		$this->entry_id				= $entry_id;
		$this->site_id				= ee()->config->item('site_id');
		$this->str					= $str;

		if (ee()->config->item("use_category_name") == 'y' AND ee()->config->item("reserved_category_word") != '')
		{
			$this->use_category_names	= ee()->config->item("use_category_name");
			$this->reserved_cat_segment	= ee()->config->item("reserved_category_word");
		}

		ee()->lang->loadfile('tag');
		
		//--------------------------------------------  
		//	websafe seperator if any
		//--------------------------------------------
		
		$this->websafe_separator	= '+';

		if ( isset(ee()->TMPL) 	  	AND
			 is_object(ee()->TMPL)	AND
			 ! in_array(ee()->TMPL->fetch_param('websafe_separator'), array(FALSE, ''), TRUE) )
		{
			$this->websafe_separator	= ee()->TMPL->fetch_param('websafe_separator');
		}
		
	}
	//	END constructor


    /**	----------------------------------------
    /**	Form
    /**	----------------------------------------*/

	function form()
	{
		/**	----------------------------------------
		/**	Is the form enabled?
		/**	----------------------------------------*/

		if ( $this->preference('enable_tag_form') == 'n' )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	Grab entry id
		/**	----------------------------------------*/

		$type	= ( ee()->TMPL->fetch_param('type') !== FALSE ) ? ee()->TMPL->fetch_param('type') : 'channel';
		
		if ($type == 'weblog')
		{
			$type = 'channel';
		}

		if ( $this->_entry_id( $type ) === FALSE )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	Prep data
		/**	----------------------------------------*/

        $RET				= ( isset( $_POST['RET'] ) !== FALSE ) ? ee()->security->xss_clean( $_POST['RET'] ): ee()->functions->fetch_current_uri();

		$form_name			= ( ee()->TMPL->fetch_param('form_name') ) ? ee()->TMPL->fetch_param('form_name'): 'tag_form';

		$data				= array();

		$data['ACT']		= ee()->functions->fetch_action_id('Tag', 'insert_tags');

		$data['RET']		= $RET;

		$data['URI']		= (ee()->uri->uri_string == '') ? 'index': ee()->uri->uri_string;

		$data['entry_id']	= $this->entry_id;

		$data['type']		= $type;

		$data['return']		= ( ee()->TMPL->fetch_param('return') ) ? ee()->TMPL->fetch_param('return'): '';

		/**	----------------------------------------
		/**	Generate form
		/**	----------------------------------------*/

		$tagdata	= ee()->TMPL->tagdata;

        $res		= ee()->functions->form_declaration(
											array(
												'hidden_fields'	=> $data,
												'action'		=> $RET,
												'id'			=> ( ee()->TMPL->fetch_param('form_id') ) ? ee()->TMPL->fetch_param('form_id'): $form_name,
												'name'			=> $form_name
												)
											);

        $res		.= stripslashes($tagdata);
        $res		.= "</form>";

		return $res;
	}

	/**	END form */


    /**	----------------------------------------
    /**	Insert tags
    /**	----------------------------------------*/

	function insert_tags()
	{


		$this->remote	= TRUE;

		/**	----------------------------------------
		/**	Is the form enabled?
		/**	----------------------------------------*/

		if ( $this->preference('enable_tag_form') == 'n' )
		{
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
		}

        /** ----------------------------------------
        /**  Is the user banned?
        /** ----------------------------------------*/

        if (ee()->session->userdata['is_banned'] === TRUE)
        {
            return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }

        /** ----------------------------------------
        /**  Is the IP address and User Agent required?
        /** ----------------------------------------*/

        if (ee()->config->item('require_ip_for_posting') == 'y')
        {
        	if (ee()->input->ip_address() == '0.0.0.0' OR ee()->session->userdata['user_agent'] == "")
        	{
            	return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        	}
        }

        /** ----------------------------------------
		/**  Is the nation of the user banend?
		/** ----------------------------------------*/

		ee()->session->nation_ban_check();

        /** ----------------------------------------
        /**  Blacklist/Whitelist Check
        /** ----------------------------------------*/

        if ( ee()->blacklist->blacklisted == 'y' AND ee()->blacklist->whitelisted == 'n' )
        {
        	return ee()->output->show_user_error('general', array(ee()->lang->line('not_authorized')));
        }

		/**	----------------------------------------
		/**	Entry id
		/**	----------------------------------------*/

		if ( ee()->input->get_post('entry_id') !== FALSE AND ctype_digit( ee()->input->get_post('entry_id') ) === TRUE )
		{
			$this->entry_id = ee()->input->get_post('entry_id');
		}
		else
		{
            return ee()->output->show_user_error('general', array(ee()->lang->line('missing_entry_id')));
		}

		/**	----------------------------------------
		/**	Tags
		/**	----------------------------------------*/

		if ( ee()->input->post('tags') !== FALSE )
		{
			$this->str = ee()->input->post('tags');
		}
		else
		{
            return ee()->output->show_user_error('general', array(ee()->lang->line('no_tags_submitted')));
		}

        /**	----------------------------------------
        /**	Check Form Hash
        /**	----------------------------------------*/

		if ( ! $this->check_secure_forms())
		{
			return ee()->output->show_user_error(
				'general', 
				array(
					ee()->lang->line('not_authorized')
				)
			);
		}

		/**	----------------------------------------
		/**	Gallery mode?
		/**	----------------------------------------*/

		if ( ee()->input->get_post('type') == 'gallery' )
		{
			$query	= ee()->db->query( 
				"SELECT gallery_id 
				 FROM 	exp_gallery_entries 
				 WHERE 	entry_id = '" . ee()->db->escape_str($this->entry_id) . "'" 
			);

			if ( $query->num_rows() == 0 )
			{
				return ee()->output->show_user_error(
					'general', 
					array(
						ee()->lang->line('gallery_entry_not_found')
					)
				);
			}

			$this->type			= 'gallery';
			$this->channel_id	= $query->row('gallery_id');
			$this->site_id		= ee()->config->item('site_id');
		}

		/**	----------------------------------------
		/**	Parse
		/**	----------------------------------------*/

		if ( $this->parse( FALSE ) === FALSE )
		{
            return ee()->output->show_user_error('general', array(ee()->lang->line('error_tag_parsing')));
		}

		/**	----------------------------------------
		/**	Return
		/**	----------------------------------------*/

		$return	= ( ee()->input->get_post('return') !== FALSE AND 
					ee()->input->get_post('return') != '' ) ? 
						ee()->input->get_post('return') : 
						ee()->input->get_post('RET');

		if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $return, $match ) > 0 )
		{
			$return	= ee()->functions->create_url( $match['1'] );
		}
		elseif ( stristr( $return, "http://" ) === FALSE )
		{
			$return	= ee()->functions->create_url( $return );
		}

		$return	= $this->_chars_decode($return);

		ee()->functions->redirect( $return );
	}

	/**	END insert tags */


    /**	----------------------------------------
    /**	Tag name
    /**	----------------------------------------*/

	function tag_name()
	{
		if ( ee()->TMPL->tagdata == '' )
		{
			$marker		= ( ee()->TMPL->fetch_param('marker') )		? trim(str_replace(SLASH, '/', ee()->TMPL->fetch_param('marker')), '/') : 'tag';
			$id_marker	= ( ee()->TMPL->fetch_param('id_marker') )	? trim(str_replace(SLASH, '/', ee()->TMPL->fetch_param('id_marker')), '/') : 'tag/id';

			/**	----------------------------------------
			/**	Tag provided?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('tag') !== FALSE )
			{
				$this->tag	= ee()->TMPL->fetch_param('tag');
			}
			if ( ee()->TMPL->fetch_param('tag_id') !== FALSE )
			{
				$this->tag_id = ee()->TMPL->fetch_param('tag_id');
			}
			elseif(preg_match("/".preg_quote($id_marker, '/')."\/([0-9\|]+)(\/|$)/", ee()->uri->query_string, $match))
			{
				$this->tag_id = $match[1];
			}
			elseif(preg_match("/".preg_quote($marker, '/')."\/(.*?)(\/|$)/", ee()->uri->query_string, $match))
			{
				$this->tag = $match[1];
			}
		}
		else
		{
			$this->tag	= ee()->TMPL->tagdata;
		}
		
		/** --------------------------------------------
        /**  Pull Tag from DB if Tag ID
        /** --------------------------------------------*/
        
        if ($this->tag_id != '')
        {
        	$query = ee()->db->query(
				"SELECT t.tag_name 
				 FROM 	exp_tag_tags t
				 WHERE 	t.site_id 
				 IN 	('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
				 AND 	t.tag_id = '".ee()->db->escape_str($this->tag_id)."'"
			);
					   			 
			if ($query->num_rows() > 0)
			{
				$this->tag = $query->row('tag_name');
			}
        }
        
		//--------------------------------------------  
		//	tag seperator
		//--------------------------------------------

		if ( ee()->TMPL->fetch_param('tag_separator') !== FALSE AND 
			 ee()->TMPL->fetch_param('tag_separator') != '' )
		{
			$this->tag = str_replace( ee()->TMPL->fetch_param('tag_separator'), ',', $this->tag);
		}
		
		//--------------------------------------------  
		//	websafe separator
		//--------------------------------------------

        $websafe_separator	= '+';

		if ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND 
			 ee()->TMPL->fetch_param('websafe_separator') != '' )
		{
			$websafe_separator	= ee()->TMPL->fetch_param('websafe_separator');
		}

		$this->tag = $this->_clean_str(str_replace( $websafe_separator, ' ', $this->tag));

		if ( $this->tag == '' )
		{
			return '';
		}

		$tags		= explode( ",", stripslashes($this->tag));

		foreach ( $tags as $key => $tag )
		{
			switch(ee()->TMPL->fetch_param('case'))
			{
				case 'upper' :
					$tags[$key] = strtoupper($tag);
				break;
				case 'lower' :
					$tags[$key] = strtolower($tag);
				break;
				case 'sentence' :
					$tags[$key] = ucfirst($tag);
				break;
				case 'none' : break;
				default :
					$tags[$key] = ucwords($tag);
				break;
			}

		}

		if ( count( $tags ) > 1 )
		{
			return implode( ", ", $tags );
		}
		else
		{
			return $tags[0];
		}
	}

	/**	END tag name */


    /**	----------------------------------------
    /**	Tags
    /**	----------------------------------------*/

	function tags()
	{
		/**	----------------------------------------
		/**	Tag type
		/**	----------------------------------------*/

		$type = 'channel';

		if ( ee()->TMPL->fetch_param('type') !== FALSE AND ee()->TMPL->fetch_param('type') != '' )
		{
			$type = ee()->TMPL->fetch_param('type');
		}
		
		if ($type == 'weblog')
		{
			$type = 'channel';
		}

		/**	----------------------------------------
		/**	Websafe separator
		/**	----------------------------------------*/

		$websafe_separator	= '+';

		if ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' )
		{
			$websafe_separator	= ee()->TMPL->fetch_param('websafe_separator');
		}

		/**	----------------------------------------
		/**	Entry id
		/**	----------------------------------------*/
		
		if ( ctype_digit( ee()->TMPL->fetch_param('entry_id') ))
		{
			$this->entry_id = ee()->TMPL->fetch_param('entry_id');
		}
		elseif ( $this->_entry_id( $type ) === FALSE ) 
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/**	----------------------------------------
		/**	Start SQL
		/**	----------------------------------------*/

		$sql	= "SELECT	t.tag_name, t.tag_id, t.tag_name AS tag, 
							t.gallery_entries, t.channel_entries, t.total_entries, t.clicks
				   FROM exp_tag_tags t
				   LEFT JOIN exp_tag_entries e ON t.tag_id = e.tag_id
				   WHERE t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
				   AND e.entry_id = '".ee()->db->escape_str($this->entry_id)."'";

		/**	----------------------------------------
		/**	Exclude?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
		{
			$ids	= $this->_exclude( ee()->TMPL->fetch_param('exclude') );

			if ( is_array( $ids ) )
			{
				$sql	.= " AND t.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
			}
		}
		
		/** --------------------------------------------
        /**  Bad Tags
        /** --------------------------------------------*/
        
        if (sizeof($this->bad()) > 0)
        {
        	$sql .= " AND t.tag_name NOT IN ('".implode( "','", ee()->db->escape_str($this->bad()) )."')";
        }

		/**	----------------------------------------
		/**	Tag type
		/**	----------------------------------------*/

		if ( $type != 'channel' )
		{
			$sql	.= " ".ee()->functions->sql_andor_string( $type, 'e.type' );
		}
		else
		{
			$sql	.= " AND e.type = 'channel'";
		}

		/**	----------------------------------------
		/**	Order
		/**	----------------------------------------*/

		if ( in_array( ee()->TMPL->fetch_param('orderby'), array( 'clicks', 'edit_date', 'entry_date', 'gallery_entries', 'total_entries', 'channel_entries' ) ) )
		{
			$sql	.= " ORDER BY t.".ee()->TMPL->fetch_param('orderby');
			$sql	.= ( stristr( 'asc', ee()->TMPL->fetch_param('sort') ) ) ? " ASC": " DESC";
		}
		else
		{
			$sql	.= " ORDER BY t.tag_name";
			$sql	.= ( stristr( 'desc', ee()->TMPL->fetch_param('sort') ) ) ? " DESC": " ASC";
		}

		/**	----------------------------------------
		/**	Limit
		/**	----------------------------------------*/

		if ( ctype_digit( ee()->TMPL->fetch_param('limit') ) === TRUE )
		{
			$sql	.= " LIMIT ".ee()->TMPL->fetch_param('limit');
		}

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/
		
		$query	= ee()->db->query( $sql );

		/**	----------------------------------------
		/**	Empty?
		/**	----------------------------------------*/

		if ( $query->num_rows() == 0 )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/**	----------------------------------------
		/**	Parse
		/**	----------------------------------------*/
		
		$qs	= (ee()->config->item('force_query_string') == 'y') ? '' : '?';

		$r	= '';
		
		$subscribe_links = (stristr(ee()->TMPL->tagdata, 'subscribe_link'.RD)) ? TRUE : FALSE;
		$total_results	 = sizeof($query->result_array());

		foreach ( $query->result_array() as $count => $row )
		{
			$tagdata	= ee()->TMPL->tagdata;
			
			$row['entry_id']			= $this->entry_id;
			$row['count']				= $count+1;
			$row['tag_count']			= $row['count'];
			$row['total_results']		= $total_results;
			$row['tag_total_results']	= $row['total_results'];
			$row['weblog_entries']		= $row['channel_entries'];

			/**	----------------------------------------
			/**	Add content
			/**	----------------------------------------*/

			$row['websafe_tag']	= str_replace( " ", $websafe_separator, $row['tag'] );

			/**	----------------------------------------
			/**	Parse conditionals
			/**	----------------------------------------*/

			$cond		= $row;
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );
			
			/** --------------------------------------------
			/**  Subscribe/Unsubscribe Links
			/** --------------------------------------------*/
			
			if ($subscribe_links === TRUE)
			{
				if (ee()->session->userdata['member_id'] == 0)
				{
					$tagdata = str_replace(array(LD.'subscribe_link'.RD, LD.'unsubscribe_link'.RD), '', $tagdata);
				}
				else
				{
					$tagdata = str_replace(LD.'subscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'subscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
					$tagdata = str_replace(LD.'unsubscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'unsubscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
				}
			}
			
			/**	----------------------------------------
			/**	Parse singles
			/**	----------------------------------------*/
			
			foreach ( $row as $key => $val )
			{
				$tagdata	= ee()->TMPL->swap_var_single( $key, $val, $tagdata );
			}

			$r	.= $tagdata;
		}

		$backspace	= ( ctype_digit( ee()->TMPL->fetch_param('backspace') ) === TRUE ) ? ee()->TMPL->fetch_param('backspace'): 0;

		$r			= ( $backspace > 0 ) ? substr( $r, 0, - $backspace ): $r;
		
		$this->actions()->db_charset_switch('default');

		return $r;
	}

	/**	END tags */


    /**	----------------------------------------
    /**	Tags from field
    /**	----------------------------------------
    /*	This function helps create a list of
    /*	tags from the contents of a field.
    /**	----------------------------------------*/

	function tags_from_field()
	{


		/**	----------------------------------------
		/**	Websafe separator
		/**	----------------------------------------*/

		$websafe_separator	= '+';

		if ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' )
		{
			$websafe_separator	= ee()->TMPL->fetch_param('websafe_separator');
		}

		if ( ctype_digit( ee()->TMPL->fetch_param('backspace') ) === FALSE )
		{
			$backspace	= FALSE;
		}
		else
		{
			$backspace	= ee()->TMPL->fetch_param('backspace');
		}

		if ( preg_match( "/".LD."format".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."format".RD."/s", ee()->TMPL->tagdata, $match ) == 0 )
		{
			return;
		}
		else
		{
			$block			= $match['1'];
			ee()->TMPL->tagdata	= str_replace( $match['0'], '', ee()->TMPL->tagdata );

			$separator = ( ee()->TMPL->fetch_param('delimiter') ) ? ee()->TMPL->fetch_param('delimiter') : $this->preference('separator');

			if ( $separator == 'comma' )
			{
				$tags	= preg_split( "/,|\n|\r/", trim( ee()->TMPL->tagdata ), -1, PREG_SPLIT_NO_EMPTY );
			}
			elseif ( $separator == 'semicolon' )
			{
				$tags	= preg_split( "/;|\n|\r/", trim( ee()->TMPL->tagdata ), -1, PREG_SPLIT_NO_EMPTY );
			}
			elseif ( $separator == 'colon' )
			{
				$tags	= preg_split( "/:|\n|\r/", trim( ee()->TMPL->tagdata ), -1, PREG_SPLIT_NO_EMPTY );
			}
			else
			{
				$tags	= preg_split( "/\n|\r/", trim( ee()->TMPL->tagdata ), -1, PREG_SPLIT_NO_EMPTY );
			}
		}

		$r	= '';

		$tags	= array_diff( $tags, $this->bad() );

		natcasesort( $tags );

		foreach ( $tags as $tag )
		{
			$tagdata				= $block;

			$cond['tag']			= trim( $tag );
			$cond['websafe_tag']	= str_replace( " ", $websafe_separator, trim( $tag ) );
			ee()->functions->prep_conditionals( $tagdata, $cond );

			$tagdata				= str_replace( LD."tag".RD, trim( $tag ), $tagdata );
			$tagdata				= str_replace( LD."websafe_tag".RD, str_replace( " ", $websafe_separator, trim( $tag ) ), $tagdata );

			$r						.= $tagdata;
		}

		$r	= ( $backspace ) ? substr( $r, 0, -$backspace ): $r;

		return $r;
	}

	/**	END tags from field */
	
	/**	----------------------------------------
	/**	 Has Entry Been Tagged by This Member Already?
	/**	----------------------------------------*/
	
	function tagged( )
	{

		
		$saved		= FALSE;
		
		if (ee()->TMPL->fetch_param('type') == 'gallery')
		{
			$type = 'gallery';
			
			if ( $this->_entry_id( 'gallery' ) === FALSE )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
		}
		else
		{
			$type = 'channel';
			
			if ( $this->_entry_id( 'channel' ) === FALSE )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
		}
		

		if ( ee()->session->userdata['member_id'] != 0 )
		{
			$sql	= "SELECT COUNT(*) AS count 
					   FROM exp_tag_entries 
					   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."') 
					   AND type = '{$type}' 
					   AND entry_id = '".ee()->db->escape_str( $this->entry_id )."' 
					   AND author_id = '".ee()->db->escape_str( ee()->session->userdata['member_id'] )."'";
					   
			$query = ee()->db->query( $sql );
							
			if ($query->row('count') > 0)
			{
				$saved	= TRUE;
			}
		}
		
		$tagdata			= ee()->TMPL->tagdata;
		
		$cond['tagged']		= ( $saved )   ? TRUE: FALSE;
		$cond['not_tagged']	= ( ! $saved ) ? TRUE: FALSE;
		
		$this->actions()->db_charset_switch('default');
		
		return $this->return_data = ee()->functions->prep_conditionals($tagdata, $cond);
	}
	
	/* End tagged() */


	/**	----------------------------------------
	/**	Search
	/**	----------------------------------------*/

	function search_results()
	{
		/**	----------------------------------------
		/**	Search module installed?
		/**	----------------------------------------*/

		if ( ee()->db->table_exists('exp_search') === FALSE )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

        /** ----------------------------------------
        /*	If the QSTR variable is less than 32 chars, we don't have a valid search hash
        /** ----------------------------------------*/

        if ( strlen(ee()->uri->query_string) < 32 )
        {
        	$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
        }

        /** ----------------------------------------
        /**	Capture search ID number
        /** ----------------------------------------*/

        $search_id = substr( ee()->uri->query_string, 0, 32 );

        /** ----------------------------------------
        /**	Check DB
        /** ----------------------------------------*/

        $query	= ee()->db->query( "SELECT keywords FROM exp_search WHERE search_id = '".ee()->db->escape_str( $search_id )."'" );

        if ( $query->num_rows() == 0 )
        {
        	$this->actions()->db_charset_switch('default');
        	return $this->_no_results('tag');
        }
        else
        {
        	$keywords	= $query->row('keywords');
        }

        /** ----------------------------------------
        /**	Turn keywords into an array
        /** ----------------------------------------*/

        $exclude	= array();
        $terms		= array();

		if ( preg_match_all( "/\-*\"(.*?)\"/", $keywords, $matches ) )
		{
			for( $m=0; $m < sizeof( $matches['1'] ); $m++ )
			{
				$terms[]	= trim( str_replace( '"', '', $matches['0'][$m] ) );
				$keywords	= str_replace( $matches['0'][$m],'', $keywords );
			}
		}

		if ( trim( $keywords ) != '' )
		{
			$terms = array_merge( $terms, preg_split( "/\s+/", trim( $keywords ) ) );
		}

		$keywords	= array();

		foreach ( $terms as $val )
		{
			if ( substr( $val, 0, 1 ) == "-" )
			{
				$exclude[]	= substr( $val, 1 );
			}
			else
			{
				$keywords[]	= $val;
			}
		}

		/**	----------------------------------------
		/**	What kind of search?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('where') !== FALSE )
		{
			foreach ( array( 'all', 'any', 'exact_phrase' ) as $wheres )
			{
				if ( ee()->TMPL->fetch_param('where') == $wheres )
				{
					$where	= $wheres;
				}
			}
		}
		else
		{
			$where	= 'any';
		}

		/**	----------------------------------------
		/**	Start SQL
		/**	----------------------------------------*/

		$sql	= "SELECT DISTINCT(e.entry_id), e.tag_id
					   FROM exp_tag_entries AS e
					   LEFT JOIN exp_tag_tags AS t
					   ON e.tag_id = t.tag_id
					   WHERE";

		$binary	 = ( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

		$sql	.= " t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";

		$sql	.= " AND e.type = 'channel'";

		/**	----------------------------------------
		/**	Exclude?
		/**	----------------------------------------
		/*	Get entry ids that should be included in
		/*	our results
		/**	----------------------------------------*/

		if ( count( $exclude ) > 0 )
		{
			$exclude_q	= ee()->db->query( $sql." AND {$binary} t.tag_name IN ('".implode( "','", ee()->db->escape_str($exclude) )."')" );

			$exclude	= array();

			foreach ( $exclude_q->result_array() as $row )
			{
				$exclude[]	= $row['entry_id'];
			}
		}

		/**	----------------------------------------
		/**	What kind of search?
		/**	----------------------------------------*/

		if ( $where == 'any' OR $where == 'all' )
		{
			$sql	.= " AND {$binary} t.tag_name IN ('".implode( "','", ee()->db->escape_str($keywords) )."')";
		}
		else
		{
			$sql	.= " AND {$binary} t.tag_name = '".implode( " ", ee()->db->escape_str($keywords) )."'";
		}

		/**	----------------------------------------
		/**	Are we ranking?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param( 'tag_rank' ) != FALSE AND in_array( ee()->TMPL->fetch_param( 'tag_rank' ), array( 'clicks', 'gallery_entries', 'total_entries', 'channel_entries' ) ) )
		{
			$sql	.= " ORDER BY t.".ee()->db->escape_str( ee()->TMPL->fetch_param( 'tag_rank' ) )." DESC";
		}

		/**	----------------------------------------
		/**	Run query
		/**	----------------------------------------*/

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		if ( $where == 'all' )
		{
			/**	----------------------------------------
			/**	Assemble entry ids inclusively
			/**	----------------------------------------*/

			$arr	= array();

			foreach ( $query->result_array() as $row )
			{
				if ( in_array( $row['entry_id'], $exclude ) ) continue;

				$arr[ $row['tag_id'] ][]	= $row['entry_id'];
			}

			/**	----------------------------------------
			/**	Check minimum requirements
			/**	----------------------------------------
			/*	If the number of tags is less than the
			/*	number of keywords, we can't possibly
			/*	meet the requirement that all entries
			/*	returned contain all of our tags.
			/**	----------------------------------------*/

			if ( count( $arr ) < count( $keywords ) )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			if ( count( $arr ) < 2 )
			{
				$chosen	= array_shift( $arr );
			}
			else
			{
				$chosen = call_user_func_array('array_intersect', $arr);
			}

			if ( count( $chosen ) == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			$this->entry_id	= implode( "|", $chosen );
		}
		else
		{
			/**	----------------------------------------
			/**	Assemble entry ids
			/**	----------------------------------------*/

			$ids	= array();

			foreach ( $query->result_array() as $row )
			{
				if ( in_array( $row['entry_id'], $exclude ) ) continue;

				array_push($ids, $row['entry_id']);
			}

			$this->entry_id	= implode('|', $ids);
		}

		/**	----------------------------------------
		/**	Parse entries
		/**	----------------------------------------*/

		if ( ! $tagdata = $this->_entries( array('dynamic' => 'off') ) )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

        return $tagdata;
	}

	/**	END search */


	/**	----------------------------------------
	/**	Entries
	/**	----------------------------------------*/

	function entries()
	{
		$marker		= ( ee()->TMPL->fetch_param('marker') )		? trim(str_replace(SLASH, '/', ee()->TMPL->fetch_param('marker')), '/') : 'tag';
		$id_marker	= ( ee()->TMPL->fetch_param('id_marker') )	? trim(str_replace(SLASH, '/', ee()->TMPL->fetch_param('id_marker')), '/') : 'tag/id';
		$dynamic	= ( ee()->TMPL->fetch_param('dynamic') !== FALSE AND $this->check_no(ee()->TMPL->fetch_param('dynamic'))) ? 'off': 'on';

		$qstring = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;
		$cat_id  = '';

		/**	----------------------------------------
		/**	Tag provided?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('tag') !== FALSE )
		{
			$this->tag = ee()->TMPL->fetch_param('tag');
		}
		elseif ( ee()->TMPL->fetch_param('tag_id') !== FALSE )
		{
			$this->tag_id = ee()->TMPL->fetch_param('tag_id');
		}
		elseif(preg_match("/".preg_quote($id_marker, '/')."\/([0-9\|]+)(\/|$)/", ee()->uri->query_string, $match))
		{
			$this->tag_id = $match[1];
		}
		elseif(preg_match("/".preg_quote($marker, '/')."\/(.*?)(\/|$)/", ee()->uri->query_string, $match))
		{
			$this->tag = $match[1];
		}
		
		if ( $this->tag == '' && $this->tag_id == '' )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	Remove reserved characters
		/**	----------------------------------------*/

		//--------------------------------------------  
		//	tag seperator
		//--------------------------------------------

		if ( ee()->TMPL->fetch_param('tag_separator') !== FALSE AND 
			 ee()->TMPL->fetch_param('tag_separator') != '' )
		{
			$this->tag = str_replace( ee()->TMPL->fetch_param('tag_separator'), ',', $this->tag);
		}

		//--------------------------------------------  
		//	websafe separator
		//--------------------------------------------

		$websafe_separator = ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND 
							   ee()->TMPL->fetch_param('websafe_separator') != '' ) ? 
								ee()->TMPL->fetch_param('websafe_separator') : '+';

		if ($this->tag_id == '')
		{
			$this->tag	= str_replace( $websafe_separator, " ", $this->tag );
			$this->tag	= str_replace( "%20", " ", $this->tag );
			$this->tag	= $this->_clean_str( $this->tag );
		}
		
		/**	----------------------------------------
		/**	Are we ranking?
		/**	----------------------------------------*/

		if ( in_array( ee()->TMPL->fetch_param( 'tag_rank' ), array( 'clicks', 'gallery_entries', 'total_entries', 'channel_entries' ) ) )
		{
			$tag_rank	= ee()->TMPL->fetch_param( 'tag_rank' );
		}

		/**	----------------------------------------
		/**	Inclusive tags?
		/**	----------------------------------------*/

		if ( $this->check_yes(ee()->TMPL->fetch_param('inclusive')) === FALSE)
		{
			$this->tag		= str_replace( ",", "|", $this->tag );

			$sql		= "SELECT DISTINCT(e.entry_id)
						   FROM exp_tag_entries AS e
						   LEFT JOIN exp_tag_tags AS t ON e.tag_id = t.tag_id ";

			/**	----------------------------------------
			/**	Are we checking for category?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('category') !== FALSE AND ee()->TMPL->fetch_param('category') != '' )
			{
				/**	----------------------------------------
				/**	Get the id
				/**	----------------------------------------*/

				if ( ctype_digit( str_replace( array("not ", "|"), "", ee()->TMPL->fetch_param('category') ) ) === TRUE )
				{
					$cat_id	= ee()->TMPL->fetch_param('category');
				}
				elseif ( preg_match( "/C(\d+)/s", ee()->TMPL->fetch_param('category'), $match ) )
				{
					$cat_id	= $match['1'];
				}
				else
				{
					$cat_q	= ee()->db->query( "SELECT cat_id FROM exp_categories
										   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
										   AND cat_url_title = '".ee()->db->escape_str( ee()->TMPL->fetch_param('category') )."'" );

					if ( $cat_q->num_rows() > 0 )
					{
						$cat_id	= '';

						foreach ( $cat_q->result_array() as $row )
						{
							$cat_id	.= $row['cat_id']."|";
						}
					}
				}
			}

			// Numeric version of the category?

			if (preg_match("#(^|\/)C(\d+)#", $qstring, $match) AND $dynamic == 'on')
			{
				$cat_id = $match['2'];
			}

			/**	----------------------------------------
			/**	Do we have a Category id?
			/**	----------------------------------------
        	/*  We use LEFT JOIN when there is a 'not' so that we get
        	/*  entries that are not assigned to a category.
        	/* --------------------------------*/

        	if ($cat_id != '')
        	{
				if (substr($cat_id, 0, 3) == 'not' && $this->check_no(ee()->TMPL->fetch_param('uncategorized_entries')) === FALSE)
				{
					$sql .= "LEFT JOIN exp_category_posts AS cp ON e.entry_id = cp.entry_id ";
				}
				else
				{
					$sql .= "INNER JOIN exp_category_posts AS cp ON e.entry_id = cp.entry_id ";
				}
			}

			/**	----------------------------------------
			/**	 Search for Tag Names
			/**	----------------------------------------*/

			$sql		.= " WHERE";

			$binary		 = ( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

			$sql		.= " t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
			
			if ($this->tag_id != '')
			{
				$sql .= ee()->functions->sql_andor_string( $this->tag_id, ' t.tag_id');
			}
			else
			{		
				if (substr($this->tag, 0, 4) == 'not ' && $this->check_yes(ee()->TMPL->fetch_param('exclusive')))
				{
					$sql .= " AND e.entry_id NOT IN (SELECT DISTINCT entry_id FROM exp_tag_entries AS e, exp_tag_tags AS t
												 WHERE e.tag_id = t.tag_id ".
											   ee()->functions->sql_andor_string( substr($this->tag, 4), $binary.' t.tag_name').
											   ")";
				}
				
				$sql		.= ee()->functions->sql_andor_string( $this->tag, $binary.' t.tag_name');
			}

			$sql		.= " AND e.type = 'channel'";

			/** ----------------------------------------------
			/**  Limit query by category
			/** ----------------------------------------------*/

			if ($cat_id != '')
			{
				if (substr($cat_id, 0, 3) == 'not' && $this->check_no(ee()->TMPL->fetch_param('uncategorized_entries')) === FALSE)
				{
					$sql .= ee()->functions->sql_andor_string($cat_id, 'cp.cat_id', '', TRUE)." ";
				}
				else
				{
					$sql .= ee()->functions->sql_andor_string($cat_id, 'cp.cat_id')." ";
				}
			}

			/**	----------------------------------------
			/**	Are we ranking?
			/**	----------------------------------------*/

			if ( isset( $tag_rank ) )
			{
				$sql	.= " ORDER BY t.".$tag_rank." DESC";
			}

			/**	----------------------------------------
			/**	Run query
			/**	----------------------------------------*/

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			/**	----------------------------------------
			/**	Assemble entry ids
			/**	----------------------------------------*/

			$ids	= array();

			foreach ( $query->result_array() as $row )
			{
				$ids[] = $row['entry_id'];
			}

			$this->entry_id	= implode('|', $ids);
		}
		else
		{
			if ($this->tag_id == '')
			{		
				$tags	= preg_split( "/[,|\|]/", $this->tag );

				$tags	= array_unique( $tags );
			}
			
			$sql	= "SELECT DISTINCT(e.entry_id), t.tag_id
					   FROM exp_tag_entries e
					   LEFT JOIN exp_tag_tags t ON t.tag_id = e.tag_id ";

			/**	----------------------------------------
			/**	Are we checking for a category?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('category') !== FALSE AND ee()->TMPL->fetch_param('category') != '' )
			{
				/**	----------------------------------------
				/**	Get the id
				/**	----------------------------------------*/

				if ( ctype_digit( str_replace( array("not ", "|"), "", ee()->TMPL->fetch_param('category') ) ) === TRUE )
				{
					$cat_id	= ee()->TMPL->fetch_param('category');
				}
				elseif ( preg_match( "/C(\d+)/s", ee()->TMPL->fetch_param('category'), $match ) )
				{
					$cat_id	= $match['1'];
				}
				else
				{
					$cat_q	= ee()->db->query( "SELECT cat_id FROM exp_categories
										   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
										   AND cat_url_title = '".ee()->db->escape_str( ee()->TMPL->fetch_param('category') )."'" );

					if ( $cat_q->num_rows() > 0 )
					{
						$cat_id	= '';

						foreach ( $cat_q->result_array() as $row )
						{
							$cat_id	.= $row['cat_id']."|";
						}
					}
				}
			}

			// Numeric version of the category?

			if (preg_match("#(^|\/)C(\d+)#", $qstring, $match) AND $dynamic == 'on')
			{
				$cat_id = $match['2'];
			}

			/**	----------------------------------------
			/**	Do we have a Category id?
			/**	----------------------------------------
        	/*  We use LEFT JOIN when there is a 'not' so that we get
        	/*  entries that are not assigned to a category.
        	/* --------------------------------*/

        	if ($cat_id != '')
        	{
				if (substr($cat_id, 0, 3) == 'not' && $this->check_no(ee()->TMPL->fetch_param('uncategorized_entries')) === FALSE)
				{
					$sql .= "LEFT JOIN exp_category_posts AS cp ON e.entry_id = cp.entry_id ";
				}
				else
				{
					$sql .= "INNER JOIN exp_category_posts AS cp ON e.entry_id = cp.entry_id ";
				}
			}

			$sql	.= " WHERE";

			$binary	 = ( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

			$sql	.= " t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
			
			if ($this->tag_id != '')
			{
				$sql	.= " AND t.tag_id IN ('".implode( "','", ee()->db->escape_str(explode('|', $this->tag_id)))."')";
			}
			else
			{
				if (sizeof($tags) == 1)
				{
					$sql	.= " AND {$binary} t.tag_name IN ('".implode( "','", ee()->db->escape_str($tags))."')";
				}
				else
				{
					$tsql = "SELECT te.entry_id, t.tag_name FROM exp_tag_entries AS te
							 LEFT JOIN exp_tag_tags AS t ON t.tag_id = te.tag_id
							 WHERE {$binary} t.tag_name IN ('".implode( "','", ee()->db->escape_str($tags))."')
							 AND te.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
							 AND te.type = 'channel'";
							 
					$tquery = ee()->db->query($tsql);
					
					if ($tquery->num_rows() == 0)
					{
						$this->actions()->db_charset_switch('default');
						return $this->_no_results('tag');
					}
					
					$entry_array = array();
					
					foreach($tquery->result_array() as $row)
					{
						$entry_array[$row['tag_name']][] = $row['entry_id'];
					}
					
					if (sizeof($entry_array) != sizeof($tags))
					{
						$this->actions()->db_charset_switch('default');
						return $this->_no_results('tag');
					}
				
					$chosen = call_user_func_array('array_intersect', $entry_array);
					
					if (sizeof($chosen) == 0)
					{
						$this->actions()->db_charset_switch('default');
						return $this->_no_results('tag');
					}
					
					$sql .= "AND e.entry_id IN ('".implode("','", $chosen)."') ";
				}
			}
			
			$sql	.= " AND e.type = 'channel'";

			/** ----------------------------------------------
			/**  Limit query by category
			/** ----------------------------------------------*/

			if ($cat_id != '')
			{
				if (substr($cat_id, 0, 3) == 'not' && $this->check_no(ee()->TMPL->fetch_param('uncategorized_entries')) === FALSE)
				{
					$sql .= ee()->functions->sql_andor_string($cat_id, 'cp.cat_id', '', TRUE)." ";
				}
				else
				{
					$sql .= ee()->functions->sql_andor_string($cat_id, 'cp.cat_id')." ";
				}
			}

			/**	----------------------------------------
			/**	Are we ranking?
			/**	----------------------------------------*/

			if ( isset( $tag_rank ) )
			{
				$sql	.= " ORDER BY t.".$tag_rank." DESC";
			}

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			$arr	= array();

			foreach ( $query->result_array() as $row )
			{
				$arr[ $row['tag_id'] ][]	= $row['entry_id'];
			}

			if ( count( $arr ) < 2 )
			{
				$chosen	= array_shift( $arr );
			}
			else
			{
				//we need a unique set of entry ids so we dont have repeat results
				$chosen = array_unique(call_user_func_array('array_merge', $arr));
			}

			if ( count( $chosen ) == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			$this->entry_id	= implode( "|", $chosen );
		}

		/** ----------------------------------------------
        /**  Only Entries with Pages
        /** ----------------------------------------------*/

		if ( ee()->TMPL->fetch_param('show_pages') !== FALSE AND in_array( ee()->TMPL->fetch_param('show_pages'), array('only', 'no') ) AND ( $pages = ee()->config->item('site_pages') ) !== FALSE)
		{			
			//is this version 2?
			if (  ! array_key_exists('templates', $pages) AND 
				  array_key_exists(ee()->config->item('site_id'), $pages) )
			{
				$pages = $pages[ee()->config->item('site_id')];
			}
			
			if ( ee()->TMPL->fetch_param('show_pages') == 'only' )
			{
				$this->entry_id	= implode( "|", array_intersect( explode( "|", $this->entry_id ), array_flip( $pages['templates'] ) ) );
			}
			else
			{
				$this->entry_id	= implode( "|", array_diff( explode( "|", $this->entry_id ), array_flip( $pages['templates'] ) ) );
			}
		}

		/**	----------------------------------------
		/**	Parse entries
		/**	----------------------------------------*/

		if ( ! $tagdata = $this->_entries( array('dynamic' => 'off', 'show_pages' => 'yes') ) )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

        return $tagdata;
	}

	/**	END entries */


	/**	----------------------------------------
	/**	Sub entries
	/**	----------------------------------------*/

	function _entries ( $params = array() )
	{
		/**	----------------------------------------
		/**	Execute?
		/**	----------------------------------------*/

		if ( $this->entry_id == '' ) return FALSE;

		/**	----------------------------------------
		/**	Invoke Channel class
		/**	----------------------------------------*/
		
		if (APP_VER < 2.0)
		{
			if ( ! class_exists('Weblog') )
			{
				require PATH_MOD.'/weblog/mod.weblog'.EXT;
			}
	
			$this->actions()->db_charset_switch('default');
	
			$channel = new Weblog;
		}
		else
		{
			if ( ! class_exists('Channel') )
			{
				require PATH_MOD.'/channel/mod.channel'.EXT;
			}
	
			$channel = new Channel;
		}
		
		/**	----------------------------------------
		/**	Pass params
		/**	----------------------------------------*/
		
		if (ee()->TMPL->fetch_param($this->sc->channel.'_entry_id') !== FALSE && 
			ee()->TMPL->fetch_param($this->sc->channel.'_entry_id') != ''
			AND ctype_digit(str_replace(array("not ", "|"), '', 
				ee()->TMPL->fetch_param($this->sc->channel.'_entry_id'))) === TRUE
		   )
		{
			if (substr(ee()->TMPL->fetch_param($this->sc->channel.'_entry_id'), 0, 4) == 'not ')
			{
				// Only those Entry IDs not in the parameter.
				$this->entry_id = implode('|', array_diff(explode('|', $this->entry_id), explode('|', substr(ee()->TMPL->fetch_param($this->sc->channel.'_entry_id'), 4))));
			}
			else
			{
				
				$this->entry_id = implode('|', array_intersect(explode('|', $this->entry_id), explode('|', ee()->TMPL->fetch_param($this->sc->channel.'_entry_id'))));
			}
		}

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

        ee()->TMPL->tagparams['inclusive']	= '';

		ee()->TMPL->tagparams['show_pages']	= 'all';

        if ( isset( $params['dynamic'] ) AND $params['dynamic'] == "off" )
        {
			ee()->TMPL->tagparams['dynamic']	= 'off';
        }

		//	----------------------------------------
		//	Pre-process related data
		//	----------------------------------------
		// 	Look. This sucks. Those knuckleheads have the TMPL class coded so that only
		// 	one method in the channel class and one method in the search class are allowed
		// 	to parse related entries tags. This is no doubt for performance reasons. I
		// 	can dig it. But it makes 3rd party developers' jobs hard. Well, write your
		// 	own damned platform then Mitchell. See how you like it. Fine, I think I'll
		// 	write the software platform that all inter-stellar space craft will rely on
		// 	for life support. Then we'll see about classes and methods Rick and Paul. Then
		// 	we'll see indeed.
		//	----------------------------------------

		ee()->TMPL->tagdata		= ee()->TMPL->assign_relationship_data( ee()->TMPL->tagdata );

		ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );

		/**	----------------------------------------
		/**	Execute needed methods
		/**	----------------------------------------*/

		if (APP_VER < 2.0)
		{
        	$channel->fetch_custom_weblog_fields();
		}
		else
		{
			$channel->fetch_custom_channel_fields();
		}
		
        $channel->fetch_custom_member_fields();

		$channel->fetch_pagination_data();

		/**	----------------------------------------
		/**	Grab entry data
		/**	----------------------------------------*/

        $channel->create_pagination();

        $channel->build_sql_query();

        if ($channel->sql == '')
        {
        	$this->actions()->db_charset_switch('default');
        	return $this->return_data = $this->_no_results('tag');
        }

        /** --------------------------------------------
        /**  Order By Relevance for the Related Entries Tag
        /** --------------------------------------------*/

        if (ee()->TMPL->fetch_param('orderby') == 'relevance' && isset(ee()->TMPL->tagparts[1]) && ee()->TMPL->tagparts[1] == 'related_entries')
        {
        	/** --------------------------------------------
			/**  Stupid, Bloody Pagination!
			/** --------------------------------------------*/

        	if ($channel->pager_sql != '' && $channel->paginate == TRUE)
        	{
        		$channel->pager_sql .= 'ORDER BY FIELD(t.entry_id, '.str_replace('|', ',', $this->entry_id).')';;

				// Cutting a quick corner here with the offset and limit...

				$offset = ( ! ee()->TMPL->fetch_param('offset') OR ! is_numeric(ee()->TMPL->fetch_param('offset'))) ? '0' : ee()->TMPL->fetch_param('offset');

				$channel->pager_sql .= ($channel->p_page == '') ? " LIMIT ".$offset.', '.$channel->p_limit : " LIMIT ".$channel->p_page.', '.$channel->p_limit;

				$pquery = ee()->db->query($channel->pager_sql);

				$entries = array();

				// Build ID numbers (checking for duplicates)

				foreach ($pquery->result_array() as $row)
				{
					$entries[] = $row['entry_id'];
				}

				$channel->sql = preg_replace("/t\.entry_id\s+IN\s+\([^\)]+\)/is",
        								"t.entry_id IN (".implode(',', $entries).")",
        								$channel->sql);


				unset($pquery);
				unset($entries);
        	}


        	$channel->sql = preg_replace("/ORDER BY.+?(LIMIT|$)/is",
        								'ORDER BY FIELD(t.entry_id, '.str_replace('|', ',', $this->entry_id).'), t.entry_date, t.title \1',
        								$channel->sql);
        }


        $channel->query = ee()->db->query($channel->sql);

        if ($channel->query->num_rows() == 0)
        {
            return FALSE;
        }
        
        if (APP_VER < 2.0)
		{			
			$channel->query->result	= $channel->query->result_array();
		}

		/**	----------------------------------------
		/**	Are we forcing the order?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param( 'tag_rank' ) !== FALSE )
		{
			/**	----------------------------------------
			/**	Reorder
			/**	----------------------------------------
			/*	The channel class fetches entries and
			/*	sorts them for us, but not according to
			/*	our ranking order. So we need to
			/*	reorder them.
			/**	----------------------------------------*/

			$new	= array_flip(explode( "|", $this->entry_id ));

			foreach ( $channel->query->result_array() as $key => $row )
			{
				$new[$row['entry_id']] = $row;
			}

			foreach ( $new as $key => $val )
			{
				if ( is_array( $val ) !== TRUE )
				{
					unset( $new[$key] );
				}
			}

			/**	----------------------------------------
			/**	Redeclare
			/**	----------------------------------------
			/*	We will reassign the $channel->query->result with our
			/*	reordered array of values. Thank you PHP for being so fast with array loops.
			/**	----------------------------------------*/

			if (APP_VER < 2.0)
			{
				$channel->query->result	= array_values($new);
			}
			else
			{
				$channel->query->result_array = array_values($new);
			}
			
			//	Clear some memory
			unset( $new );
			unset( $entries );
		}

        /** --------------------------------------------
        /**  Typography
        /** --------------------------------------------*/
        
        if (APP_VER < 2.0)
        {
        	if ( ! class_exists('Typography'))
			{
				require PATH_CORE.'core.typography'.EXT;
			}
					
			$channel->TYPE = new Typography;
			$channel->TYPE->convert_curly = FALSE;
        }
        else
        {
			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;
		}
		
        $channel->fetch_categories();

        /** --------------------------------------------
        /**  Last Bit of Relevance Code
        /** --------------------------------------------*/

        if (ee()->TMPL->fetch_param('orderby') == 'relevance' && isset(ee()->TMPL->tagparts[1]) && ee()->TMPL->tagparts[1] == 'related_entries')
        {
        	foreach ( $channel->query->result_array() as $key => $row )
			{
				if (APP_VER < 2.0)
				{
					$channel->query->result[$key]['max_relevance']			= $this->max_relevance;
					$channel->query->result[$key]['tag_relevance']			= $this->tag_relevance[$row['entry_id']];
					$channel->query->result[$key]['tag_relevance_percent']	= round(($this->tag_relevance[$row['entry_id']] / $this->max_relevance) * 100);
				}
				else
				{
					$channel->query->result_array[$key]['max_relevance']			= $this->max_relevance;
					$channel->query->result_array[$key]['tag_relevance']			= $this->tag_relevance[$row['entry_id']];
					$channel->query->result_array[$key]['tag_relevance_percent']	= round(($this->tag_relevance[$row['entry_id']] / $this->max_relevance) * 100);
				}
			}
        }

		/**	----------------------------------------
		/**	Parse and return entry data
		/**	----------------------------------------*/
		
		if (APP_VER < 2.0)
		{
			$channel->parse_weblog_entries();
		}
		else
		{
			$channel->parse_channel_entries();
		}

		$channel->add_pagination_data();

		/**	----------------------------------------
		/**	Count tag
		/**	----------------------------------------*/

		$this->_count_tag( $channel->current_page );

		if (count(ee()->TMPL->related_data) > 0 AND count($channel->related_entries) > 0)
		{
			$channel->parse_related_entries();
		}

		if (count(ee()->TMPL->reverse_related_data) > 0 AND count($channel->reverse_related_entries) > 0)
		{
			$channel->parse_reverse_related_entries();
		}

		/**	----------------------------------------
		/*	Handle problem with pagination segments in the url
		/**	----------------------------------------*/

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}

        $tagdata = $channel->return_data;

        return $tagdata;
	}

	/**	END sub entries */


	/**	----------------------------------------
	/**	Gallery entries
	/**	----------------------------------------*/

	function gallery_entries()
	{


		/**	----------------------------------------
		/**	Gallery installed?
		/**	----------------------------------------*/

		if ( ee()->db->table_exists('exp_gallery_entries') === FALSE )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	Set marker
		/**	----------------------------------------*/

		$marker		= ( ee()->TMPL->fetch_param('marker') )		? trim(str_replace(SLASH, '/', ee()->TMPL->fetch_param('marker')), '/') : 'tag';
		$id_marker	= ( ee()->TMPL->fetch_param('id_marker') )	? trim(str_replace(SLASH, '/', ee()->TMPL->fetch_param('id_marker')), '/') : 'tag/id';

		/**	----------------------------------------
		/**	Tag provided?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('tag') !== FALSE )
		{
			$this->tag	= ee()->TMPL->fetch_param('tag');
		}
		if ( ee()->TMPL->fetch_param('tag_id') !== FALSE )
		{
			$this->tag_id = ee()->TMPL->fetch_param('tag_id');
		}
		elseif(preg_match("/".preg_quote($id_marker, '/')."\/([0-9\|]+)(\/|$)/", ee()->uri->query_string, $match))
		{
			$this->tag_id = $match[1];
		}
		elseif(preg_match("/".preg_quote($marker, '/')."\/(.*?)(\/|$)/", ee()->uri->query_string, $match))
		{
			$this->tag = $match[1];
		}

		if ( $this->tag == '' && $this->tag_id == '')
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	Remove reserved characters
		/**	----------------------------------------*/

		//--------------------------------------------  
		//	tag seperator
		//--------------------------------------------

		if ( ee()->TMPL->fetch_param('tag_separator') !== FALSE AND 
			 ee()->TMPL->fetch_param('tag_separator') != '' )
		{
			$this->tag = str_replace( ee()->TMPL->fetch_param('tag_separator'), ',', $this->tag);
		}
		
		//--------------------------------------------  
		//	websafe separator
		//--------------------------------------------

		$websafe_separator		= ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND 
									ee()->TMPL->fetch_param('websafe_separator') != '' ) ? 
										ee()->TMPL->fetch_param('websafe_separator'): '+';

		if ($this->tag_id == '')
		{
			$this->tag	= str_replace( $websafe_separator, " ", $this->tag );
			$this->tag	= str_replace( "%20", " ", $this->tag );

			$this->tag	= $this->_clean_str( $this->tag );
		}
		
		/**	----------------------------------------
		/**	Inclusive tags?
		/**	----------------------------------------*/

		if ( $this->check_yes(ee()->TMPL->fetch_param('inclusive')) === FALSE )
		{
			$this->tag	= str_replace( ",", "|", $this->tag );

			$sql	= "SELECT e.entry_id FROM exp_tag_entries AS e LEFT JOIN exp_tag_tags AS t ON e.tag_id = t.tag_id WHERE";

			$binary	 =	( $this->preference('convert_case') != 'n' ) ? "": " BINARY ";

			$sql	.= " t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
			
			if ($this->tag_id != '')
			{
				$sql	.= ee()->functions->sql_andor_string( $this->tag_id, 't.tag_id');
			}
			else
			{
				$sql	.= ee()->functions->sql_andor_string( $this->tag, $binary.'t.tag_name');
			}
			
			$sql	.= " AND e.type = 'gallery'";

			/**	----------------------------------------
			/**	Run query
			/**	----------------------------------------*/

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			/**	----------------------------------------
			/**	Assemble entry ids
			/**	----------------------------------------*/

			$ids	= array();

			foreach ( $query->result_array() as $row )
			{
				array_push($ids, $row['entry_id']);
			}

			$this->entry_id	= implode('|', $ids);
		}
		else
		{
			if ($this->tag_id == '')
			{
				$tags	= preg_split( "/[,|\|]/", $this->tag );

				$tags	= array_unique( $tags );
			}
			
			$sql	= "SELECT e.entry_id, t.tag_id FROM exp_tag_entries e LEFT JOIN exp_tag_tags t ON t.tag_id = e.tag_id WHERE";

			$binary	 =	( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

			$sql	.= " t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
			
			if ($this->tag_id != '')
			{
				$sql	.= " AND t.tag_id IN ('".implode( "','", ee()->db->escape_str(explode('|', $this->tag_id)))."')";
			}
			else
			{
				$sql	.= " AND {$binary} t.tag_name IN ('".implode( "','", ee()->db->escape_str($tags) )."')";
			}
			
			$sql	.= " AND e.type = 'gallery'";

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			$arr	= array();

			foreach ( $query->result_array() as $row )
			{
				$arr[ $row['tag_id'] ][]	= $row['entry_id'];
			}

			if ( count( $arr ) < 2 )
			{
				$chosen	= array_shift( $arr );
			}
			else
			{
				$chosen = call_user_func_array('array_intersect', $arr);
			}

			if ( count( $chosen ) == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			$this->entry_id	= implode( "|", $chosen );
		}

		/**	----------------------------------------
		/**	Set dynamic
		/**	----------------------------------------*/

		$dynamic	= ( ee()->TMPL->fetch_param('dynamic') !== FALSE AND $this->check_no(ee()->TMPL->fetch_param('dynamic'))) ? 'off': 'on';

		/**	----------------------------------------
		/**	Parse entries
		/**	----------------------------------------*/

		if ( ! $tagdata = $this->_gallery_entries( array( 'dynamic' => $dynamic ) ) )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

        return $tagdata;
	}

	/**	END gallery entries */


	/**	----------------------------------------
	/**	Sub gallery entries
	/**	----------------------------------------*/

	function _gallery_entries( $params = array() )
	{


		/**	----------------------------------------
		/**	Execute?
		/**	----------------------------------------*/

		if ( $this->entry_id == '' ) return FALSE;

		/**	----------------------------------------
		/**	Invoke gallery class
		/**	----------------------------------------*/

		if ( class_exists('Gallery') === FALSE )
        {
        	require PATH_MOD.'/gallery/mod.gallery'.EXT;
        }

        $this->actions()->db_charset_switch('default');

        $GAL = new Gallery;

		/**	----------------------------------------
		/**	Pass params
		/**	----------------------------------------*/

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

        if ( isset( $params['dynamic'] ) AND $params['dynamic'] == "off" )
        {
			$GAL->dynamic = FALSE;
        }

		/**	----------------------------------------
		/**	Pass params
		/**	----------------------------------------*/

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

		if ( ee()->TMPL->fetch_param('columns') !== FALSE )
		{
			$GAL->max_columns = ee()->TMPL->fetch_param('columns');
		}

		if ( ee()->TMPL->fetch_param('rows') !== FALSE )
		{
			$GAL->max_rows = ee()->TMPL->fetch_param('rows');
		}

		$GAL->fetch_pagination_data();

		$GAL->parse_gallery_tag();

		$GAL->build_sql_query();

		if ($GAL->sql == '')
		{
			return FALSE;
		}

		/** --------------------------------------------
        /**  Order By Relevance for the Related Entries Tag
        /** --------------------------------------------*/

        if (ee()->TMPL->fetch_param('orderby') == 'relevance' && isset(ee()->TMPL->tagparts[1]) && ee()->TMPL->tagparts[1] == 'related_gallery_entries')
        {
        	$GAL->sql = preg_replace("/ORDER BY.+?(LIMIT|$)/is",
        								'ORDER BY FIELD(e.entry_id, '.str_replace('|', ',', $this->entry_id).'), e.entry_date desc, e.entry_id desc \1',
        								$GAL->sql);
        }


		$GAL->query = ee()->db->query($GAL->sql);

        if ($GAL->query->num_rows() == 0)
        {
			return FALSE;
        }

		//we need to set the results object else some built in items wont work
		$GAL->query->result = $GAL->query->result_array();

		if ( $GAL->entry_id != '' AND $this->check_no(ee()->TMPL->fetch_param('log_views')) === FALSE)
		{
			$GAL->log_views();
		}

		/** --------------------------------------------
        /**  Last Bit of Relevance Code
        /** --------------------------------------------*/

        if (ee()->TMPL->fetch_param('orderby') == 'relevance' && isset(ee()->TMPL->tagparts[1]) && ee()->TMPL->tagparts[1] == 'related_gallery_entries')
        {
        	foreach ( $GAL->query->result_array() as $key => $row )
			{
				$GAL->query->result[$key]['max_relevance']		 = $this->max_relevance;
				$GAL->query->result[$key]['tag_relevance']		 = $this->tag_relevance[$row['entry_id']];
				$GAL->query->result[$key]['tag_relevance_percent'] = round(($this->tag_relevance[$row['entry_id']] / $this->max_relevance) * 100);
			}
        }

		/** --------------------------------------------
        /**  Typography
        /** --------------------------------------------*/
		
		ee()->load->library('typography');
		
		if (APP_VER >= 2.0)
		{
			ee()->typography->initialize();
		}

		$GAL->TYPE = ee()->typography;

		$GAL->parse_gallery_entries();
		$GAL->add_pagination_data();

		/**	----------------------------------------
		/**	Count tag
		/**	----------------------------------------*/

		$this->_count_tag( $GAL->current_page );

		/**	----------------------------------------
		/*	Handle problem with pagination segments
		/*	in the url
		/**	----------------------------------------*/

		if ( preg_match("#(/P\d+)#", ee()->uri->uri_string, $match) )
		{
			$GAL->return_data	= str_replace( $match['1'], "", $GAL->return_data );
		}
		elseif ( preg_match("#(P\d+)#", ee()->uri->uri_string, $match) )
		{
			$GAL->return_data	= str_replace( $match['1'], "", $GAL->return_data );
		}

		return $GAL->return_data;
	}

	/**	END sub gallery entries */


	/**	----------------------------------------
	/**	Related entries
	/**	----------------------------------------*/

	function related_entries()
	{
		/**	----------------------------------------
		/**	Entry id?
		/**	----------------------------------------*/

		if ( $this->_entry_id() === FALSE ) 
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/**	----------------------------------------
		/**	Get tag ids for entry
		/**	----------------------------------------*/

		$sql	= "SELECT DISTINCT te1.site_id, te1.entry_id, te1.tag_id";

		if (ee()->TMPL->fetch_param('orderby') == 'relevance')
		{
			$sql .= ", COUNT(te1.tag_id) AS tag_relevance";
		}
		
		if (sizeof(ee()->TMPL->site_ids) == 1)
		{
			$sql	.= " FROM exp_tag_entries AS te2
						INNER JOIN exp_tag_entries te1 ON te1.tag_id = te2.tag_id
						WHERE te1.type = 'channel' AND te2.type = 'channel'
						AND te2.entry_id = '".ee()->db->escape_str($this->entry_id)."'
						AND te1.entry_id != '".ee()->db->escape_str($this->entry_id)."'";	
		}
		else
		{
			// So much work, just to get it to work across multiple Sites.
			
			$sql	.= " FROM exp_tag_entries AS te2
						INNER JOIN exp_tag_tags tt2 on tt2.tag_id = te2.tag_id
						INNER JOIN exp_tag_tags tt1 ON tt1.tag_name = tt2.tag_name
						INNER JOIN exp_tag_entries te1 ON te1.tag_id = tt1.tag_id
						WHERE te1.type = 'channel' AND te2.type = 'channel'
						AND te2.entry_id = '".ee()->db->escape_str($this->entry_id)."'
						AND te1.entry_id != '".ee()->db->escape_str($this->entry_id)."'
						AND te1.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
						AND te2.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";				
		}
					
		/**	----------------------------------------
		/**	Exclude?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
		{
			$ids	= $this->_exclude( ee()->TMPL->fetch_param('exclude') );

			if ( is_array( $ids ) )
			{
				$sql	.= " AND te1.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
			}
		}

		/**	----------------------------------------
		/**	Rank limit
		/**	----------------------------------------
		/*	We can pull entries by tag rank. Users can indicate their ranking method and pull by clicks, entries or both.
		/**	----------------------------------------*/

		if ( ctype_digit( ee()->TMPL->fetch_param('rank_limit') ) === TRUE )
		{
			$rank		= array();
			
			if (sizeof(ee()->TMPL->site_ids) == 1)
			{
				$sql_rank	= "SELECT tt1.tag_id, ( tt1.total_entries + tt1.clicks ) AS sum
								FROM exp_tag_entries AS te2
								INNER JOIN exp_tag_tags tt1 ON tt1.tag_id = te2.tag_id
								WHERE tt1.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
								AND te2.type = 'channel'
								AND te2.entry_id != '".ee()->db->escape_str($this->entry_id)."'";
			}
			else
			{
				$sql_rank	= "SELECT tt1.tag_id, ( tt1.total_entries + tt1.clicks ) AS sum
								FROM exp_tag_entries AS te2
								INNER JOIN exp_tag_tags tt2 on tt2.tag_id = te2.tag_id
								INNER JOIN exp_tag_tags tt1 ON tt1.tag_name = tt2.tag_name
								WHERE tt1.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
								AND te2.type = 'channel'
								AND te2.entry_id != '".ee()->db->escape_str($this->entry_id)."'";
			}

			/**	----------------------------------------
			/**	Filter to our tags only
			/**	----------------------------------------*/

			if (ee()->TMPL->fetch_param('orderby') == 'relevance')
			{
				$query	= ee()->db->query( $sql." GROUP BY te1.entry_id ORDER BY tag_relevance");
			}
			else
			{
				$query	= ee()->db->query( $sql );
			}
			
			if ( $query->num_rows() == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			if ($query->num_rows() > 0)
			{
				$data = array();

				foreach ( $query->result_array() as $row )
				{
					$data[] = $row['tag_id'];
				}

				$sql_rank .= "AND tt1.tag_id IN (".implode(',', $data).")";
			}

			/**	----------------------------------------
			/**	Group
			/**	----------------------------------------*/

			$sql_rank	.= " GROUP BY tt1.tag_id";

			$rank_method	= ( ee()->TMPL->fetch_param('rank_method') ) ? ee()->TMPL->fetch_param('rank_method'): '';

			$allowed_ranks	= array( 'total_entries', 'clicks' );

			/**	----------------------------------------
			/**	Rank by both entries and clicks?
			/**	----------------------------------------*/

			if ( $rank_method == '' OR ( stristr( $rank_method, 'total_entries' ) AND stristr( $rank_method, 'clicks' ) ) )
			{
				$sql_rank	.= " ORDER BY sum";
			}

			/**	----------------------------------------
			/**	Rank by one vector?
			/**	----------------------------------------*/

			elseif ( in_array( $rank_method, $allowed_ranks ) )
			{
				$sql_rank	.= " ORDER BY tt1.".ee()->db->escape_str( $rank_method );
			}
			else
			{
				$sql_rank	.= " ORDER BY tt1.total_entries";
			}

			$sql_rank	.= " DESC LIMIT ".ee()->TMPL->fetch_param('rank_limit');

			ee()->TMPL->log_item("Tag sql_rank:".$sql_rank);

			$r			= ee()->db->query( $sql_rank );

			foreach ( $r->result_array() as $row )
			{
				$rank[]	= ee()->db->escape_str( $row['tag_id'] );
			}

			unset($r);

			$sql	.= " AND te1.tag_id IN ('".implode( "','", ee()->db->escape_str($rank) )."')";
		}

		if (ee()->TMPL->fetch_param('orderby') == 'relevance')
		{
			$sql .= " GROUP BY te1.entry_id ORDER BY tag_relevance";

			$sort = ee()->TMPL->fetch_param('sort');

			switch ($sort)
			{
				case 'asc'	: $sql .= " asc";
					break;
				case 'desc'	: $sql .= " desc";
					break;
				default		: $sql .= " desc";
					break;
			}
		}

		/**	----------------------------------------
		/**	Run query
		/**	----------------------------------------*/

		$query	= ee()->db->query( $sql );

		ee()->TMPL->log_item("Tag sql:".$sql);

		if ( $query->num_rows() == 0 )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	 Count of Original Entry's Tags for Max Relevance
		/**	----------------------------------------*/

		if (ee()->TMPL->fetch_param('orderby') == 'relevance')
		{
			$mquery = ee()->db->query("SELECT COUNT(te1.tag_id) AS count 
								  FROM exp_tag_entries AS te1
								  WHERE te1.type = 'channel' 
								  AND te1.entry_id = '".ee()->db->escape_str($this->entry_id)."'");

			$this->max_relevance = $mquery->row('count');
		}

		/**	----------------------------------------
		/**	Assemble entry ids
		/**	----------------------------------------*/

		$this->old_entry_id = $this->entry_id;

		$ids	= array();

		foreach ( $query->result_array() as $row )
		{
			if (isset($row['tag_relevance']))
			{
				$this->tag_relevance[$row['entry_id']] = $row['tag_relevance'];
			}

			$ids[] = $row['entry_id'];
		}

		$this->entry_id	= implode('|', $ids);

		/**	----------------------------------------
		/**	Parse entries
		/**	----------------------------------------*/

		if ( ! $tagdata = $this->_entries( array( 'dynamic' => 'off' ) ) )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

        return $tagdata;
	}

	/**	END related entries */


	/**	----------------------------------------
	/**	Related gallery entries
	/**	----------------------------------------*/

	function related_gallery_entries()
	{


		/**	----------------------------------------
		/**	Entry id?
		/**	----------------------------------------*/
		
		if (ee()->TMPL->fetch_param('type') == 'gallery')
		{
			$type = 'gallery';
			if ( $this->_entry_id( 'gallery' ) === FALSE )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
		}
		else
		{
			$type = 'channel';
			
			if ( $this->_entry_id( 'channel' ) === FALSE )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
		}

		/**	----------------------------------------
		/**	Get tag ids for entry
		/**	----------------------------------------*/

		$sql	= "SELECT DISTINCT te1.entry_id, te1.tag_id";

		if (ee()->TMPL->fetch_param('orderby') == 'relevance')
		{
			$sql .= ", COUNT(te1.tag_id) AS tag_relevance";
		}

		$sql	.= " FROM exp_tag_entries AS te1
				   LEFT JOIN exp_tag_entries AS te2 ON te1.tag_id = te2.tag_id
				   LEFT JOIN exp_tag_tags tt ON tt.tag_id = te1.tag_id
				   WHERE te1.type = 'gallery' AND te2.type = '{$type}'
				   AND te2.entry_id = '".ee()->db->escape_str($this->entry_id)."'";
				   
		if ($type == 'gallery')
		{
			$sql .= " AND te1.entry_id != '".ee()->db->escape_str($this->entry_id)."'";
		}

		/**	----------------------------------------
		/**	Exclude?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
		{
			$ids	= $this->_exclude( ee()->TMPL->fetch_param('exclude') );

			if ( is_array( $ids ) )
			{
				$sql	.= " AND te.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
			}
		}

		/**	----------------------------------------
		/**	Rank limit
		/**	----------------------------------------
		/*	We can pull entries by tag rank. Users
		/*	can indicate their ranking method and
		/*	pull by clicks, entries or both.
		/**	----------------------------------------*/

		if ( ctype_digit( ee()->TMPL->fetch_param('rank_limit') ) === TRUE )
		{
			$rank		= array();

			$sql_rank	= "SELECT t.tag_id, ( t.total_entries + t.clicks ) AS sum
						   FROM exp_tag_entries e
						   LEFT JOIN exp_tag_tags t
						   ON e.tag_id = t.tag_id
						   WHERE t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
						   AND e.type = 'gallery'
						   AND e.entry_id != '".ee()->db->escape_str($this->entry_id)."'";

			/**	----------------------------------------
			/**	Filter to our tags only
			/**	----------------------------------------*/
			
			if (ee()->TMPL->fetch_param('orderby') == 'relevance')
			{
				$query	= ee()->db->query( $sql." GROUP BY te1.entry_id ORDER BY tag_relevance" );
			}
			else
			{
				$query	= ee()->db->query( $sql );
			}
			
			if ( $query->num_rows() == 0 )
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}

			if ($query->num_rows() > 0)
			{
				$data = array();

				foreach ( $query->result_array() as $row )
				{
					$data[] = $row['tag_id'];
				}

				$sql_rank .= "AND t.tag_id IN (".implode(',', $data).")";
			}

			/**	----------------------------------------
			/**	Group
			/**	----------------------------------------*/

			$sql_rank	.= " GROUP BY t.tag_id";

			$rank_method	= ( ee()->TMPL->fetch_param('rank_method') ) ? ee()->TMPL->fetch_param('rank_method'): '';

			$allowed_ranks	= array( 'total_entries', 'clicks' );

			/**	----------------------------------------
			/**	Rank by both entries and clicks?
			/**	----------------------------------------*/

			if ( $rank_method == '' OR ( stristr( $rank_method, 'total_entries' ) AND stristr( $rank_method, 'clicks' ) ) )
			{
				$sql_rank	.= " ORDER BY sum";
			}

			/**	----------------------------------------
			/**	Rank by one vector?
			/**	----------------------------------------*/

			elseif ( in_array( $rank_method, $allowed_ranks ) )
			{
				$sql_rank	.= " ORDER BY t.".ee()->db->escape_str( $rank_method );
			}
			else
			{
				$sql_rank	.= " ORDER BY t.total_entries";
			}

			$sql_rank	.= " DESC LIMIT ".ceil(ee()->TMPL->fetch_param('rank_limit'));

			$r			= ee()->db->query( $sql_rank );

			foreach ( $r->result_array() as $row )
			{
				$rank[]	= ee()->db->escape_str( $row['tag_id'] );
			}

			unset($r);

			$sql	.= " AND te1.tag_id IN ('".implode( "','", ee()->db->escape_str($rank) )."')";
		}

		if (ee()->TMPL->fetch_param('orderby') == 'relevance')
		{
			$sql .= " GROUP BY te1.entry_id ORDER BY tag_relevance";

			$sort = ee()->TMPL->fetch_param('sort');

			switch ($sort)
			{
				case 'asc'	: $sql .= " asc";
					break;
				case 'desc'	: $sql .= " desc";
					break;
				default		: $sql .= " desc";
					break;
			}
		}

		/**	----------------------------------------
		/**	Run query
		/**	----------------------------------------*/

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

		/**	----------------------------------------
		/**	 Count of Original Entry's Tags for Max Relevance
		/**	----------------------------------------*/

		if (ee()->TMPL->fetch_param('orderby') == 'relevance')
		{
			$mquery = ee()->db->query("SELECT COUNT(te1.tag_id) AS count FROM exp_tag_entries AS te1
								  WHERE te1.type = '{$type}' AND te1.entry_id = '".ee()->db->escape_str($this->entry_id)."'");

			$this->max_relevance = $mquery->row('count');
		}

		/**	----------------------------------------
		/**	Assemble entry ids
		/**	----------------------------------------*/

		$this->old_entry_id = $this->entry_id;

		$ids	= array();

		foreach ( $query->result_array() as $row )
		{
			if (isset($row['tag_relevance']))
			{
				$this->tag_relevance[$row['entry_id']] = $row['tag_relevance'];
			}

			$ids[] = $row['entry_id'];
		}

		$this->entry_id	= implode('|', $ids);

		/**	----------------------------------------
		/**	Parse entries
		/**	----------------------------------------*/

		if ( ! $tagdata = $this->_gallery_entries( array( 'dynamic' => 'off' ) ) )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}

        return $tagdata;
	}

	/**	END related gallery entries */


	/**	----------------------------------------
	/**	Cloud
	/**	----------------------------------------*/

	function cloud()
	{


		$max 			= 1;  // Must be 1, cannot divide by zero, Mitchell!

		$rank_by		= (ee()->TMPL->fetch_param('rank_by') == 'clicks') ? 'clicks' : 'entries';

		$groups			= ( ctype_digit( ee()->TMPL->fetch_param('groups') ) === TRUE ) ? ee()->TMPL->fetch_param('groups'): 5;

		$start			= ( ctype_digit( ee()->TMPL->fetch_param('start') ) === TRUE ) ? ee()->TMPL->fetch_param('start'): 10;

		$step			= ( ctype_digit( ee()->TMPL->fetch_param('step') ) === TRUE ) ? ee()->TMPL->fetch_param('step'): 2;

		$username		= ( ee()->TMPL->fetch_param('username') !== FALSE AND ee()->TMPL->fetch_param('username') != '' ) ? ee()->TMPL->fetch_param('username'): '';

		$author_id		= ( ee()->TMPL->fetch_param('author_id') !== FALSE AND ee()->TMPL->fetch_param('author_id') != '' ) ? ee()->TMPL->fetch_param('author_id'): '';

		$show_expired	= ( ee()->TMPL->fetch_param('show_expired') !== FALSE AND ee()->TMPL->fetch_param('show_expired') != '' ) ? ee()->TMPL->fetch_param('show_expired'): 'no';

		$show_future_entries	= ( ee()->TMPL->fetch_param('show_future_entries') !== FALSE AND ee()->TMPL->fetch_param('show_future_entries') != '' ) ? ee()->TMPL->fetch_param('show_future_entries'): 'no';

		$start_on		= ( ee()->TMPL->fetch_param('start_on') !== FALSE AND ee()->TMPL->fetch_param('start_on') != '' ) ? ee()->TMPL->fetch_param('start_on'): '';

		$status			= ( ee()->TMPL->fetch_param('status') !== FALSE AND ee()->TMPL->fetch_param('status') != '' ) ? ee()->TMPL->fetch_param('status'): '';

		$stop_before	= ( ee()->TMPL->fetch_param('stop_before') !== FALSE AND ee()->TMPL->fetch_param('stop_before') != '' ) ? ee()->TMPL->fetch_param('stop_before'): '';

		$day_limit		= ( ee()->TMPL->fetch_param('day_limit') !== FALSE AND ee()->TMPL->fetch_param('day_limit') != '' ) ? ee()->TMPL->fetch_param('day_limit'): '';

		$websafe_separator		= ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' ) ? ee()->TMPL->fetch_param('websafe_separator'): '+';


		/** --------------------------------------------
        /**  Fixed Order - Override of tag_id="" parameter
        /** --------------------------------------------*/
        
        // fixed entry id ordering
		if (($fixed_order = ee()->TMPL->fetch_param('fixed_order')) === FALSE OR preg_match('/[^0-9\|]/', $fixed_order))
		{
			$fixed_order = FALSE;
		}
		else
		{
			// Override Tag ID parameter to get exactly these entries
			// Other parameters will still affect results. I blame the user for using them if it
			// does not work they way they want.
			ee()->TMPL->tagparams['tag_id'] = $fixed_order;
			
			$fixed_order = preg_split('/\|/', $fixed_order, -1, PREG_SPLIT_NO_EMPTY);
			
			// A quick and easy way to reverse the order of these entries.  People might like this.
			if (ee()->TMPL->fetch_param('sort') == 'desc')
			{
				$fixed_order = array_reverse($fixed_order);
			}
		}

		/**	----------------------------------------
		/**	Weblog or Gallery mode?
		/**	----------------------------------------*/

		$entries_prefix	= "g";

		if ( ee()->db->table_exists('exp_gallery_entries') === FALSE OR ee()->TMPL->fetch_param('gallery') === FALSE OR ee()->TMPL->fetch_param('gallery') == '')
		{
			$entries_prefix	= "wt";

			/**	----------------------------------------
			/**	Begin SQL
			/**	----------------------------------------*/

			$sql = "SELECT t.tag_id, t.clicks, t.tag_name, t.total_entries, t.channel_entries, t.gallery_entries,
						   w.{$this->sc->db->channel_id}, w.{$this->sc->db->channel_url}, w.comment_url, COUNT(e.tag_id) AS count
					FROM exp_tag_tags AS t
					LEFT JOIN exp_tag_entries e ON t.tag_id = e.tag_id
					LEFT JOIN {$this->sc->db->channels} AS w ON w.{$this->sc->db->channel_id} = e.channel_id";

			/**	----------------------------------------
			/**	Handle date stuff
			/**	----------------------------------------*/

			if ( $start_on != '' OR $stop_before != '' OR $day_limit != '' OR $status != '' OR $show_expired != '' OR $show_future_entries != '' )
			{
				$sql	.= " LEFT JOIN {$this->sc->db->channel_titles} AS wt ON wt.entry_id = e.entry_id";
			}

			/**	----------------------------------------
			/**	Are we checking category?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('category') !== FALSE AND ee()->TMPL->fetch_param('category') != '' )
			{
				/**	----------------------------------------
				/**	Get the id
				/**	----------------------------------------*/

				if ( ctype_digit( str_replace( array("not ", "|"), '', ee()->TMPL->fetch_param('category') ) ) === TRUE )
				{
					$cat_id	= ee()->TMPL->fetch_param('category');
				}
				elseif ( preg_match( "/C(\d+)/s", ee()->TMPL->fetch_param('category'), $match ) )
				{
					$cat_id	= $match['1'];
				}
				else
				{
					$cat_q	= ee()->db->query( "SELECT cat_id FROM exp_categories
										   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
										   AND cat_url_title = '".ee()->db->escape_str( ee()->TMPL->fetch_param('category') )."'" );

					if ( $cat_q->num_rows() > 0 )
					{
						$cat_id	= '';

						foreach ( $cat_q->result_array() as $row )
						{
							$cat_id	.= $row['cat_id']."|";
						}
					}
				}

				/**	----------------------------------------
				/**	Do we have an id?
				/**	----------------------------------------*/

				if ( isset( $cat_id ) )
				{
					$sql .= " LEFT JOIN exp_category_posts AS cp ON e.entry_id = cp.entry_id";
				}
			}

			$sql .= " WHERE t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."') 
					  AND t.tag_id != '' AND e.type = 'channel'";

			/**	----------------------------------------
			/**	No bad tags
			/**	----------------------------------------*/
			
			if (sizeof($this->bad()) > 0)
			{
				$sql	.= " AND t.tag_name NOT IN ('".implode( "','", ee()->db->escape_str($this->bad()) )."')";
			}
			
			/**	----------------------------------------
			/**	 Narrow Tags via Tag Name
			/**	----------------------------------------*/
	
			if ( ee()->TMPL->fetch_param('tag_name') !== FALSE AND ee()->TMPL->fetch_param('tag_name') != '' )
			{
				if (substr( ee()->TMPL->fetch_param('tag_name'), 0, 4) == 'not ')
				{
					$ids	= $this->_exclude( substr(ee()->TMPL->fetch_param('tag_name'), 4));

					if ( is_array( $ids ) )
					{
						$sql	.= " AND t.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
					}
				}
				else
				{
					$ids	= $this->_exclude( ee()->TMPL->fetch_param('tag_name') );

					if ( is_array( $ids ) )
					{
						$sql	.= " AND t.tag_id IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
					}
				}
			}
			
			/**	----------------------------------------
			/**	 Narrow Tags via Tag ID
			/**	----------------------------------------*/
	
			if ( ee()->TMPL->fetch_param('tag_id') !== FALSE AND ee()->TMPL->fetch_param('tag_id') != '' )
			{
				$sql .= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('tag_id'), "t.tag_id" );
			}

			/**	----------------------------------------
			/**	Exclude?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
			{
				$ids	= $this->_exclude( ee()->TMPL->fetch_param('exclude') );

				if ( is_array( $ids ) )
				{
					$sql	.= " AND t.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
				}
			}

			/**	----------------------------------------
			/**	Are we checking category?
			/**	----------------------------------------*/

			if ( isset( $cat_id ) )
			{
				$sql .= " ".ee()->functions->sql_andor_string( $cat_id, "cp.cat_id" );
			}

			/**	----------------------------------------
			/**	Limit to/exclude specific channels
			/**	----------------------------------------*/

			if ($channel = ee()->TMPL->fetch_param($this->sc->channel))
			{
				$xql = "SELECT {$this->sc->db->channel_id} FROM {$this->sc->db->channels}
						WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";

				$xql .= ee()->functions->sql_andor_string($channel, $this->sc->db->channel_name);

				$query = ee()->db->query($xql);

				if ($query->num_rows() == 0)
				{
					$this->actions()->db_charset_switch('default');
					return $this->_no_results('tag');
				}
				else
				{
					$zchannels = array();
					
					foreach ($query->result_array() as $row)
					{
						$zchannels[] = $row[$this->sc->db->channel_id];
					}
							
					$sql .= " AND e.channel_id IN ('".implode("','", ee()->db->escape_str($zchannels))."')";
				}
			}

			/** ----------------------------------------------
			/**  We only select entries that have not expired
			/** ----------------------------------------------*/

			$timestamp = (ee()->TMPL->cache_timestamp != '') ? ee()->localize->set_gmt(ee()->TMPL->cache_timestamp) : ee()->localize->now;

			if ( $show_future_entries != 'yes')
			{
				$sql .= " AND wt.entry_date < ".$timestamp." ";
			}

			if ( $show_expired != 'yes')
			{
				$sql .= " AND (wt.expiration_date = 0 || wt.expiration_date > ".$timestamp.") ";
			}
		}
		else
		{
			/**	----------------------------------------
			/**	Begin SQL
			/**	----------------------------------------*/

			$sql = "SELECT t.tag_id, t.clicks, t.tag_name, t.total_entries, t.channel_entries, t.gallery_entries, COUNT(e.tag_id) AS count
					FROM exp_tag_tags t
					LEFT JOIN exp_tag_entries e ON t.tag_id = e.tag_id
					LEFT JOIN exp_gallery_entries g ON g.entry_id = e.entry_id";

			/**	----------------------------------------
			/**	Are we checking category?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('category') !== FALSE )
			{
				$sql .= " LEFT JOIN exp_gallery_categories AS gc ON g.cat_id = gc.cat_id";
			}

			/**	----------------------------------------
			/**	Where clause
			/**	----------------------------------------*/

			$sql .= " WHERE t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."') AND  t.tag_id != '' AND e.type = 'gallery'";

			/**	----------------------------------------
			/**	No bad tags
			/**	----------------------------------------*/
			
			if (sizeof($this->bad()) > 0)
			{
				$sql	.= " AND t.tag_name NOT IN ('".implode( "','", ee()->db->escape_str($this->bad()) )."')";
			}
			
			/**	----------------------------------------
			/**	Exclude?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
			{
				$ids	= $this->_exclude( ee()->TMPL->fetch_param('exclude') );

				if ( is_array( $ids ) )
				{
					$sql	.= " AND t.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
				}
			}
			
			/**	----------------------------------------
			/**	 Narrow Tags via Tag Name
			/**	----------------------------------------*/
	
			if ( ee()->TMPL->fetch_param('tag_name') !== FALSE AND ee()->TMPL->fetch_param('tag_name') != '' )
			{
				if (substr( ee()->TMPL->fetch_param('tag_name'), 0, 4) == 'not ')
				{
					$ids	= $this->_exclude( substr(ee()->TMPL->fetch_param('tag_name'), 4));

					if ( is_array( $ids ) )
					{
						$sql	.= " AND t.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
					}
				}
				else
				{
					$ids	= $this->_exclude( ee()->TMPL->fetch_param('tag_name') );

					if ( is_array( $ids ) )
					{
						$sql	.= " AND t.tag_id IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
					}
				}
			}
			
			/**	----------------------------------------
			/**	 Narrow Tags via Tag ID
			/**	----------------------------------------*/
	
			if ( ee()->TMPL->fetch_param('tag_id') !== FALSE AND ee()->TMPL->fetch_param('tag_id') != '' )
			{
				$sql .= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('tag_id'), "t.tag_id" );
			}

			/**	----------------------------------------
			/**	Are we checking category?
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('category') !== FALSE AND ee()->TMPL->fetch_param('category') != '' )
			{
				if ( ctype_digit( str_replace( array("not ", "|"), "", ee()->TMPL->fetch_param('category') ) ) === TRUE )
				{
					$sql .= " ".ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('category'), 'gc.cat_id' );
				}
				else
				{
					$sql .= " ".ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('category'), 'gc.cat_name' );
				}
			}

			/**	----------------------------------------
			/**	Filter by gallery
			/**	----------------------------------------*/

			if ( ee()->TMPL->fetch_param('gallery') !== FALSE AND ee()->TMPL->fetch_param('gallery') != '' )
			{
				$query	= ee()->db->query( "SELECT gallery_id FROM exp_galleries WHERE gallery_short_name = '".ee()->db->escape_str( ee()->TMPL->fetch_param('gallery') )."'" );

				if ( $query->num_rows() > 0 )
				{
					$sql .= " AND g.gallery_id = '".$query->row('gallery_id')."'";
				}
			}

			/**	----------------------------------------
			/**	Convert status real quick
			/**	----------------------------------------*/

			$s	= '';

			if ( $status != '' )
			{
				$s	.= ( stristr( $status, 'open' ) != '' ) ? 'o|': '';
				$s	.= ( stristr( $status, 'closed' ) != '' ) ? 'c': '';
				$status	= $s;
			}
		}

		/**	-----------------------------------------
		/**	Limit by status
		/**	----------------------------------------*/

		if ( $status != '' )
		{
			$sql	.= " ".ee()->functions->sql_andor_string( $status, $entries_prefix.".status" );
		}

		/**	-----------------------------------------
		/**	Limit by author
		/**	----------------------------------------*/

		if ( ctype_digit( $author_id ) === TRUE )
		{
			$sql .= " AND e.author_id = '".ee()->db->escape_str( $author_id )."'";
		}
		elseif ( $username == 'CURRENT_USER' )
		{
			$sql .= " AND e.author_id = '".ee()->db->escape_str( ee()->session->userdata('member_id') )."'";
		}
		elseif ( $username != '' )
		{
			$m_id = ee()->db->query("SELECT member_id FROM exp_members WHERE username='".ee()->db->escape_str( $username )."'");

			if ( $m_id->num_rows() > 0 )
			{
				$sql .= " AND e.author_id = '".$m_id->row('member_id')."'";
			}
		}

		/**	----------------------------------------
		/**	Limit query by number of days
		/**	----------------------------------------*/

		if ( $day_limit != '' )
		{
			$time = ee()->localize->now - ( $day_limit * 60 * 60 * 24);

			$sql .= " AND ".$entries_prefix.".entry_date >= '".$time."'";
		}
		else // OR
		{
			/**	----------------------------------------
	        /**	Limit query by date range given in tag parameters
	        /**	----------------------------------------*/

	        if ( $start_on != '' )
	            $sql .= " AND ".$entries_prefix.".entry_date >= '".ee()->localize->convert_human_date_to_gmt($start_on)."'";

	        if ( $stop_before != '' )
	            $sql .= " AND ".$entries_prefix.".entry_date < '".ee()->localize->convert_human_date_to_gmt($stop_before)."'";
		}

		/** --------------------------------------
		/**  Most Popular Tags, by #
		/** --------------------------------------*/

		if (ee()->TMPL->fetch_param('most_popular') !== FALSE && is_numeric(ee()->TMPL->fetch_param('most_popular')))
		{
			if ($rank_by == 'clicks')
			{
				$query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT DISTINCT t.tag_id FROM ', $sql)." ORDER BY t.clicks DESC LIMIT 0, ".ceil(ee()->TMPL->fetch_param('most_popular')));
			}
			else
			{
				$query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT DISTINCT t.tag_id FROM ', $sql)." ORDER BY t.total_entries DESC LIMIT 0, ".ceil(ee()->TMPL->fetch_param('most_popular')));
			}

			if ($query->num_rows() == 0)
			{
				$this->actions()->db_charset_switch('default');
				return $this->return_data = $this->_no_results('tag');
			}

			$tag_ids = array();

			foreach($query->result_array() as $row)
			{
				$tag_ids[] = $row['tag_id'];
			}

			$sql .= " AND t.tag_id IN (".implode(',', $tag_ids).")";
		}


		// --------------------------------------
		//  Pagination checkeroo! - Do Before GROUP BY!
		// --------------------------------------

		$query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(DISTINCT e.tag_id) AS count FROM ', $sql));

		if ($query->row('count') == 0 AND 
			 strpos( ee()->TMPL->tagdata, 'paginate' ) !== FALSE)
		{
			$this->actions()->db_charset_switch('default');
			return $this->return_data = $this->_no_results('tag');
		}
		
		$this->p_limit  	= ( ! ee()->TMPL->fetch_param('limit'))  ? 20 : ee()->TMPL->fetch_param('limit');
		$this->total_rows 	= $query->row('count');
		$this->p_page 		= ($this->p_page == '' || ($this->p_limit > 1 AND $this->p_page == 1)) ? 0 : $this->p_page;

		if ($this->p_page > $this->total_rows)
		{
			$this->p_page = 0;
		}
		
		//get pagination info
		$pagination_data = $this->universal_pagination(array(
			'sql'					=> preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(DISTINCT e.tag_id) AS count FROM ', $sql), 
			'total_results'			=> $this->total_rows, 
			'tagdata'				=> ee()->TMPL->tagdata,
			'limit'					=> $this->p_limit,
			'uri_string'			=> ee()->uri->uri_string,
			'current_page'			=> $this->p_page,
		));

		//if we paginated, sort the data
		if ($pagination_data['paginate'] === TRUE)
		{
			$this->paginate			= $pagination_data['paginate'];
			$this->page_next		= $pagination_data['page_next']; 
			$this->page_previous	= $pagination_data['page_previous'];
			$this->p_page			= $pagination_data['pagination_page'];
			$this->current_page		= $pagination_data['current_page'];
			$this->pagination_links = $pagination_data['pagination_links'];
			$this->basepath			= $pagination_data['base_url'];
			$this->total_pages		= $pagination_data['total_pages'];
			$this->paginate_data	= $pagination_data['paginate_tagpair_data'];
			ee()->TMPL->tagdata		= $pagination_data['tagdata'];
		}

		/**	----------------------------------------
		/**	Set group by
		/**	----------------------------------------*/

		$sql .= " GROUP BY e.tag_id";

		/**	----------------------------------------
		/**	Find Max for All Pages
		/**	----------------------------------------*/

		if ($this->paginate === TRUE)
		{
			if ($rank_by == 'clicks')
			{
				$query = ee()->db->query($sql." ORDER BY clicks DESC LIMIT 0, 1");
			}
			else
			{
				$query = ee()->db->query($sql." ORDER BY count DESC LIMIT 0, 1");
			}

			if ($query->num_rows() > 0)
			{
				$max = ($rank_by == 'clicks') ? $query->row('clicks') : $query->row('count');
			}
		}

		/**	----------------------------------------
		/**	Set order by
		/**	----------------------------------------*/

		$ord	= " ORDER BY count";
		
		if ($fixed_order !== FALSE)
		{
			$ord = ' ORDER BY FIELD(e.tag_id, '.implode(',', $fixed_order).') ';
		}
		elseif ( ee()->TMPL->fetch_param('orderby') !== FALSE AND ee()->TMPL->fetch_param('orderby') != '' )
		{
			foreach ( array( 'random' => "rand()", 'clicks' => "t.clicks", 'count' => 'count', 'total_entries' => 't.total_entries', 'channel_entries' => 't.channel_entries', 'gallery_entries' => 't.gallery_entries', 'tag_name' => 't.tag_name' ) as $key => $val )
			{
				if ( $key == ee()->TMPL->fetch_param('orderby') )
				{
					$ord	= " ORDER BY ".$val;
				}
			}
		}

		$sql .= $ord;

		/**	----------------------------------------
		/**	Set sort
		/**	----------------------------------------*/

		if (ee()->TMPL->fetch_param('orderby') !== 'random' && $fixed_order === FALSE)
		{
			if ( ee()->TMPL->fetch_param('sort') !== FALSE AND ee()->TMPL->fetch_param('sort') == 'asc' )
			{
				$sql	.= " ASC";
			}
			else
			{
				$sql	.= " DESC";
			}
		}

		/**	----------------------------------------
		/**	Set numerical limit
		/**	----------------------------------------*/

		if ($this->paginate === TRUE && $this->total_rows > $this->p_limit)
		{
			$sql .= " LIMIT ".$this->p_page.', '.$this->p_limit;
		}
		else
		{
			$sql .= ( ctype_digit( ee()->TMPL->fetch_param('limit') ) === TRUE ) ? ' LIMIT '.ee()->TMPL->fetch_param('limit') : ' LIMIT 20';
        }

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/

		$query	= ee()->db->query( $sql );

		/**	----------------------------------------
		/**	Empty?
		/**	----------------------------------------*/

		if ( $query->num_rows() == 0 )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/**	----------------------------------------
		/**	What's the max?
		/**	----------------------------------------*/

		// If we have Pagination, we find the MAX value up above.
		// If not, we find it based on the current results.

		if ($this->paginate !== TRUE)
		{
			foreach ( $query->result_array() as $row )
			{
				if ($rank_by == 'clicks')
				{
					$max	= ( $row['clicks'] > $max ) ? $row['clicks']: $max;
				}
				else
				{
					$max	= ( $row['count'] > $max ) ? $row['count']: $max;
				}
			}
        }

		/**	----------------------------------------
		/**	Order alpha
		/**	----------------------------------------*/

		$tags	= array();

		foreach ( $query->result_array() as $row )
		{
			$tags[$row['tag_name']]['tag_id']			= $row['tag_id'];
			$tags[$row['tag_name']]['count']			= $row['count'];
			$tags[$row['tag_name']]['clicks']			= $row['clicks'];
			$tags[$row['tag_name']]['total_entries']	= $row['total_entries'];
			$tags[$row['tag_name']]['channel_entries']	= $row['channel_entries'];
			$tags[$row['tag_name']]['weblog_entries']	= $row['channel_entries'];
			$tags[$row['tag_name']]['gallery_entries']	= $row['gallery_entries'];
			$tags[$row['tag_name']][$this->sc->db->channel_id]	= ( isset( $row[$this->sc->db->channel_id] ) === TRUE ) ? $row[$this->sc->db->channel_id]: '';
			$tags[$row['tag_name']]['gallery_id']				= ( isset( $row['gallery_id'] ) === TRUE ) ? $row['gallery_id']: '';
			$tags[$row['tag_name']][$this->sc->db->channel_url]	= ( isset( $row[$this->sc->db->channel_url] ) === TRUE ) ? rtrim( $row[$this->sc->db->channel_url], "\/" )."/": '';
			$tags[$row['tag_name']]['comment_url']		= ( isset( $row[$this->sc->db->channel_url] ) === TRUE ) ? rtrim( $row['comment_url'], "\/" )."/": '';
			$tags[$row['tag_name']]['size']				= ceil( (($rank_by == 'clicks') ? $row['clicks'] : $row['count']) / ( $max / $groups ) );
			$tags[$row['tag_name']]['step']				= $tags[$row['tag_name']]['size'] * $step + $start;
		}

		if ( $ord == 'count' )
		{
			ksort( $tags );
		}

		/**	----------------------------------------
		/**	Parse
		/**	----------------------------------------*/

		$r			= '';
		$position	= 0;
				
		$subscribe_links = (stristr(ee()->TMPL->tagdata, 'subscribe_link'.RD)) ? TRUE : FALSE;
		
		$qs	= (ee()->config->item('force_query_string') == 'y') ? '' : '?';
		
		$total_results = sizeof($tags);

		foreach ( $tags as $key => $row )
		{
			$tagdata	= ee()->TMPL->tagdata;

			$row['total_results'] = $total_results;

			$position++;

			/**	----------------------------------------
			/**	Conditionals
			/**	----------------------------------------*/

			$cond					= $row;
			$cond['position']		= $position;
			$cond['tag_name']		= $key;
			$cond['websafe_tag']	= str_replace( " ", $websafe_separator, $key );
			$tagdata				= ee()->functions->prep_conditionals( $tagdata, $cond );

			/**	----------------------------------------
			/**	Parse Switch
			/**	----------------------------------------*/

			if ( preg_match( "/".LD."(switch\s*=.+?)".RD."/is", $tagdata, $match ) > 0 )
			{
				$sparam = ee()->functions->assign_parameters($match['1']);

				$sw = '';

				if ( isset( $sparam['switch'] ) !== FALSE )
				{
					$sopt = explode("|", $sparam['switch']);

					$sw = $sopt[($position + count($sopt)) % count($sopt)];
				}

				$tagdata = ee()->TMPL->swap_var_single($match['1'], $sw, $tagdata);
			}

			/**	----------------------------------------
			/**	Parse singles
			/**	----------------------------------------*/

			$tagdata = str_replace( LD.'tag'.RD, $key, $tagdata );
			$tagdata = str_replace( LD.'tag_name'.RD, $key, $tagdata );
			$tagdata = str_replace( LD.'tag_id'.RD, $row['tag_id'], $tagdata );
			$tagdata = str_replace( LD.'websafe_tag'.RD, str_replace( " ", $websafe_separator, $key ), $tagdata );
			$tagdata = str_replace( LD.'count'.RD, $row['count'], $tagdata );
			$tagdata = str_replace( LD.'clicks'.RD, $row['clicks'], $tagdata );
			$tagdata = str_replace( LD.'total_entries'.RD, $row['total_entries'], $tagdata );
			$tagdata = str_replace( LD.'channel_entries'.RD, $row['channel_entries'], $tagdata );
			$tagdata = str_replace( LD.'weblog_entries'.RD, $row['channel_entries'], $tagdata );
			$tagdata = str_replace( LD.'gallery_entries'.RD, $row['gallery_entries'], $tagdata );
			$tagdata = str_replace( LD.'size'.RD, $row['size'], $tagdata );
			$tagdata = str_replace( LD.'step'.RD, $row['step'], $tagdata );
			$tagdata = str_replace( LD.'position'.RD, $position, $tagdata );
			$tagdata = str_replace( LD.$this->sc->channel.'_id'.RD, $row[$this->sc->db->channel_id], $tagdata );
			$tagdata = str_replace( LD.$this->sc->db->channel_id.RD, $row[$this->sc->db->channel_id], $tagdata );
			$tagdata = str_replace( LD.$this->sc->db->channel_url.RD, $row[$this->sc->db->channel_url], $tagdata );
			$tagdata = str_replace( LD.'comment_url'.RD, $row['comment_url'], $tagdata );
			$tagdata = str_replace( LD.'total_results'.RD, $row['total_results'], $tagdata );
			
			/** --------------------------------------------
			/**  Subscribe/Unsubscribe Links
			/** --------------------------------------------*/
			
			if ($subscribe_links === TRUE)
			{
				if (ee()->session->userdata['member_id'] == 0)
				{
					$tagdata = str_replace(array(LD.'subscribe_link'.RD, LD.'unsubscribe_link'.RD), '', $tagdata);
				}
				else
				{
					$tagdata = str_replace(LD.'subscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'subscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
					$tagdata = str_replace(LD.'unsubscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'unsubscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
				}
			}

			/**	----------------------------------------
			/**	Concat
			/**	----------------------------------------*/

			$r	.= $tagdata;
		}

		/**	----------------------------------------
		/**	Backspace
		/**	----------------------------------------*/

		$backspace			= ( ctype_digit( ee()->TMPL->fetch_param('backspace') ) === TRUE ) ? ee()->TMPL->fetch_param('backspace'): 0;

		$this->return_data	= ( $backspace > 0 ) ? substr( $r, 0, - $backspace ): $r;

		/** --------------------------------------------
        /**  Pagination?
        /** --------------------------------------------*/

		if ($this->paginate == TRUE)
        {
			$this->paginate_data = str_replace(LD.'current_page'.RD, $this->current_page, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'total_pages'.RD,	$this->total_pages, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'pagination_links'.RD, $this->pagination_links, $this->paginate_data);

        	if (preg_match("/".LD."if previous_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_previous == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if previous_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_previous, $match['1']);
					$match['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_previous, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
       	 	}

        	if (preg_match("/".LD."if next_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_next == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if next_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_next, $match['1']);
					$match['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_next, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
        	}

			$position = ( ! ee()->TMPL->fetch_param('paginate')) ? '' : ee()->TMPL->fetch_param('paginate');

			switch ($position)
			{
				case "top"	: $this->return_data  = $this->paginate_data.$this->return_data;
					break;
				case "both"	: $this->return_data  = $this->paginate_data.$this->return_data.$this->paginate_data;
					break;
				default		: $this->return_data .= $this->paginate_data;
					break;
			}
        }
        
        $this->actions()->db_charset_switch('default');

		return $this->return_data;
	}

	/**	END cloud */


    //	----------------------------------------
    //	Parse
    //	----------------------------------------

	function parse( $clean = TRUE )
	{		
		if ( $this->entry_id == '' ) return FALSE;

		$str				= '';

		$arr				= array();
		$data				= array();
		$existing_entries	= array();

		//	----------------------------------------
		//	Clean the str
		//	----------------------------------------

		$this->str	= $this->_clean_str( $this->str );

		//	----------------------------------------
		//	Delete tag entries
		//	----------------------------------------
		// 	When submitting locally, we overwrite the existing tags for this entry with
		// 	the new ones submitted, so let's delete the current tags.
		//	----------------------------------------

		if ( $this->remote === FALSE AND $this->batch === FALSE )
		{
			ee()->db->query( 
				"DELETE FROM 	exp_tag_entries 
				 WHERE 			type 		= '{$this->type}' 
				 AND 			entry_id 	= '" . ee()->db->escape_str($this->entry_id) . "'" 
			);
			
			//--------------------------------------------  
			//	Temporary note: removing this check ( remote != 'y' ) 
			// 	for now so that we can delete remotely entered tags 
			//	in the CP if we don't like them.
			//--------------------------------------------	
		}

		//	----------------------------------------
		// 	In local mode, if we have no tags.  
		//	Clean orphans and get out.
		//	----------------------------------------

		if ( $this->str == '' AND $this->remote === FALSE )
		{
			$this->_clean();

			return TRUE;
		}

		//	----------------------------------------
		//	Grab tag entries for this entry
		//	----------------------------------------

		$tag_ids	= array();

		$sql		= "SELECT 	tag_id, remote 
					   FROM 	exp_tag_entries
					   WHERE	type 	 = '" . ee()->db->escape_str($this->type) . "'
					   AND 		entry_id = '" . ee()->db->escape_str($this->entry_id) . "'";

		$query		= ee()->db->query( $sql );

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$existing_entries[$row['tag_id']]	= $row['remote'];
				$tag_ids[]							= $row['tag_id'];
			}
		}

		//	----------------------------------------
		//	Get Channel Id
		//	----------------------------------------

		if ( $this->channel_id == '' )
		{
			$query	= ee()->db->query(
				"SELECT {$this->sc->db->channel_id}, site_id 
				 FROM 	{$this->sc->db->channel_titles}
				 WHERE 	site_id = '" . ee()->db->escape_str(ee()->config->item('site_id'))."'
				 AND 	entry_id = '".ee()->db->escape_str($this->entry_id) . "'" 
			);

			if ( $query->num_rows() > 0 )
			{
				$this->channel_id	= $query->row($this->sc->db->channel_id);
				$this->site_id		= $query->row('site_id');
			}
		}

		//	----------------------------------------
		//	Update existing tags
		//	----------------------------------------
		// 	We want tags that match the submitted set. 
		//	We will update their edit dates.
		//	----------------------------------------

		$str = $this->array_dbstr( $this->str_arr());

		$binary	= ( $this->preference('convert_case') != 'n' ) ? '': 'BINARY';

		$sql	= "SELECT 	t.tag_id, t.tag_name 
				   FROM 	exp_tag_tags AS t
				   WHERE 	t.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
				   AND 		{$binary} t.tag_name 
				   IN 		('".$str."')";
   
		$query	= ee()->db->query( $sql );
		
		//	----------------------------------------
		//	For each existing tag found in str...
		//	----------------------------------------

		foreach ( $query->result_array() as $row )
		{
			//	----------------------------------------
			//	Record existing tags found in str
			//	----------------------------------------

			$this->existing[$row['tag_id']]	= $row['tag_name'];

			$tag_ids[]	= $row['tag_id'];

			//	----------------------------------------
			//	Update the existing tag edit date
			//	----------------------------------------

			ee()->db->query( 
				ee()->db->update_string( 
					'exp_tag_tags', 
					array( 'edit_date' 	=> ee()->localize->now ), 
					array( 'tag_id' 	=> $row['tag_id'] ) 
				) 
			);

			//	----------------------------------------
			//	Prep data for exp_tag_entries insert
			//	----------------------------------------

			$data	= array(
				'tag_id'		=> $row['tag_id'],
				'channel_id'	=> $this->channel_id,
				'site_id'		=> $this->site_id,
				'entry_id'		=> $this->entry_id,
				'author_id'		=> ( $this->author_id == '' ) ? 
									ee()->session->userdata['member_id'] : 
									$this->author_id,
				'ip_address'	=> ee()->input->ip_address(),
				'remote'		=> ( $this->remote ) ? 'y': 'n',
				'type'			=> $this->type
			);

			//	----------------------------------------
			// 	Are we in local mode? Meaning are we NOT 
			//	using the tag form to let users submit tags?
			//	----------------------------------------

			if ( $this->remote === FALSE )
			{
				//	----------------------------------------
				//	Claim ownership of a remotely entered tag
				//	----------------------------------------
				// 	We're in the context of tags from our str 
				//	that already exist. If we're in
				// 	local mode and this entry already has a 
				//	reference to this tag, but the tag was
				// 	previously entered remotely, we'll change 
				//	the ownership to the person
				// 	currently editing.
				//	----------------------------------------

				if ( isset( $existing_entries[$row['tag_id']] ) AND 
					 $existing_entries[$row['tag_id']] == 'y' )
				{
					ee()->db->query( 
						ee()->db->update_string( 
							'exp_tag_entries', 
							$data, 
							array( 
								'entry_id' 	=> $this->entry_id, 
								'tag_id' 	=> $row['tag_id'] 
							) 
						) 
					);
				}

				//	----------------------------------------
				// 	Otherwise, if the entry does not have a 
				//	reference to the tag, make it so.
				//	----------------------------------------

				elseif ( isset( $existing_entries[$row['tag_id']] ) === FALSE )
				{
					ee()->db->query( ee()->db->insert_string( 'exp_tag_entries', $data ) );
				}
			}

			// ----------------------------------------
			// If remote mode and no entry exists
			// ----------------------------------------

			elseif ( isset( $existing_entries[$row['tag_id']] ) === FALSE AND 
					 in_array( $row['tag_name'], $this->bad() ) === FALSE )
			{
				ee()->db->query(
					ee()->db->insert_string( 
						'exp_tag_entries', 
						$data 
					) 
				);
			}
		}

		//	----------------------------------------
		//	Add new tags
		//	----------------------------------------
		//	1.	We turn the submitted string of tags into an array.
		//	2.	We remove from that array tags that already exist and tags that are not allowed.
		//	3.	Then we remove duplicate tags within the string.
		//	4.	Then we add the tags.
		//	5.	Then we associate those tags with the entry.
		//	6.	Then we clean-up the DB of orphaned tags.
		//	----------------------------------------

		$new	= array_unique( 
			array_diff( 
				$this->str_arr( TRUE ), 
				$this->existing, 
				$this->bad() 
			) 
		);

		foreach ( $new as $n )
		{
			if ($this->preference('allow_tag_creation_publish') != 'y' && REQ == 'CP') continue;

			if ( $n != '' )
			{
				$n	= ( $this->preference('convert_case') != 'n' ) ? 
						$this->_strtolower( $n ) : $n;

				ee()->db->query( 
					ee()->db->insert_string( 
						'exp_tag_tags', 
						array( 
							'tag_alpha'		=> $this->_first_character($n),
							'tag_name'		=> $n,
							'entry_date' 	=> ee()->localize->now,
							'site_id'		=> ee()->config->item('site_id'),
							'author_id'		=> ee()->session->userdata['member_id'] 
						) 
					) 
				);

				$data	= array(
					'tag_id'		=> ee()->db->insert_id(),
					'site_id'		=> $this->site_id,
					'channel_id'	=> $this->channel_id,
					'entry_id'		=> $this->entry_id,
					'author_id'		=> ( $this->author_id == '' ) ? 
										ee()->session->userdata['member_id'] : 
										$this->author_id,
					'ip_address'	=> ee()->input->ip_address(),
					'remote'		=> ( $this->remote ) ? 'y': 'n',
					'type'			=> $this->type
				);

				$tag_ids[]	= ee()->db->insert_id();

				ee()->db->query( ee()->db->insert_string( 'exp_tag_entries', $data ) );
			}
		}

		//	----------------------------------------
		//	Clean-up dead tags
		//	----------------------------------------

		$this->_clean();

		//	----------------------------------------
		//	Recount
		//	----------------------------------------

		$this->_recount( array( 'tag_id' => $tag_ids ) );

		//	----------------------------------------
		//	Return
		//	----------------------------------------

		return TRUE;
	}
	//	END parse


	/**	----------------------------------------
	/**	 Member Subscribed to this Tag?
	/**	----------------------------------------*/
	
	function subscribed( )
	{	
		$cond		= array();
		
		$marker		= ( ee()->TMPL->fetch_param('marker') ) ? ee()->TMPL->fetch_param('marker'): 'tag';
		
		/**	----------------------------------------
		/**	Member ID Required
		/**	----------------------------------------*/

		if ( 
				ee()->session->userdata['member_id'] == 0 && 
				(ee()->TMPL->fetch_param('member_id') == FALSE OR ctype_digit(ee()->TMPL->fetch_param('member_id')) === FALSE)
		   )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		$member_id = (ee()->TMPL->fetch_param('member_id') !== FALSE) ? ee()->TMPL->fetch_param('member_id') : ee()->session->userdata['member_id'];
		
		/** --------------------------------------------
        /**  Tag ID
        /** --------------------------------------------*/
				
		if ( ee()->TMPL->fetch_param('tag_id') !== FALSE && ctype_digit(ee()->TMPL->fetch_param('tag_id')))
		{
			$tag_id = ee()->TMPL->fetch_param('tag_id');
		}
		else
		{
			if ( ee()->TMPL->fetch_param('tag') !== FALSE )
			{
				$tag	= ee()->TMPL->fetch_param('tag');
			}
	
			elseif ( $key = array_pop(array_keys( ee()->uri->segments, $marker ) ) )
			{
				if ( isset( ee()->uri->segments[ $key + 1 ] ) )
				{
					$tag	= rawurldecode(ee()->uri->segments[ $key + 1 ]);
				}
			}
			
			if ( ! isset($tag))
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
			
			/**	----------------------------------------
			/**	Remove reserved characters and Clean
			/**	----------------------------------------*/
	
			$websafe_separator		= ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' ) ? ee()->TMPL->fetch_param('websafe_separator'): '+';
	
			$tag = str_replace( $websafe_separator, " ", $tag );
			$tag = str_replace( "%20", " ", $tag );
			$tag = $this->_clean_str( $tag );
			
			/** --------------------------------------------
			/**  Find Tag ID
			/** --------------------------------------------*/
			
			$sql		 = "SELECT tag_id FROM exp_tag_tags AS t ";
			
			$binary		 = ( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

			$sql		.= " WHERE t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";

			$sql		.= ee()->functions->sql_andor_string( $tag, $binary.' t.tag_name');
			
			$query = ee()->db->query($sql);
			
			if ($query->num_rows() == 0)
			{
				$this->actions()->db_charset_switch('default');
				
				$cond['subscribed']		= FALSE;
				$cond['not_subscribed']	= TRUE;
				
				return $this->return_data = ee()->functions->prep_conditionals(ee()->TMPL->tagdata, $cond);
			}
			
			$tag_id = $query->row('tag_id');
		}
		
		/** --------------------------------------------
        /**  Check for Subscriptions
        /** --------------------------------------------*/

		$sql	= "SELECT COUNT(*) AS count 
				   FROM exp_tag_subscriptions 
				   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
				   AND tag_id = '".ee()->db->escape_str( $tag_id )."' 
				   AND member_id = '".ee()->db->escape_str( $member_id )."'";
		
		$query = ee()->db->query($sql);
		
		$this->actions()->db_charset_switch('default');
		
		$cond['subscribed']		= ($query->row('count') > 0)  ? TRUE: FALSE;
		$cond['not_subscribed']	= ($query->row('count') == 0) ? TRUE: FALSE;
		
		$tagdata = ee()->functions->prep_conditionals(ee()->TMPL->tagdata, $cond);
		
		/** --------------------------------------------
        /**  Subscribe and Unsubscribe Links
        /** --------------------------------------------*/
			
		if (stristr(ee()->TMPL->tagdata, 'subscribe_link'.RD))
		{
			$tagdata = str_replace(LD.'subscribe_link'.RD,
									ee()->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.ee()->functions->fetch_action_id('Tag', 'subscribe').'&amp;tag_id='.$tag_id,
									$tagdata);
									
			$tagdata = str_replace(LD.'unsubscribe_link'.RD,
								   ee()->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.ee()->functions->fetch_action_id('Tag', 'unsubscribe').'&amp;tag_id='.$tag_id,
								   $tagdata);
		}
		
		return $this->return_data = $tagdata;
	}
	
	/* End tagged() */
	
	
	/**	----------------------------------------
    /**	Subscribe to Tag
    /**	----------------------------------------*/

	function subscribe( )
	{	
		if (ee()->session->userdata['member_id'] == 0 OR ! isset($_GET['tag_id']))
		{
			return FALSE;
		}
		
		/** --------------------------------------------
        /**  Valid Tag ID?  Fetch Tag Name Too...
        /** --------------------------------------------*/
        
        $query = ee()->db->query("SELECT t.tag_id, t.tag_name
						   FROM exp_tag_tags AS t
						   WHERE t.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
						   AND t.tag_id = '".ee()->db->escape_str($_GET['tag_id'])."'");
						   
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		/** --------------------------------------------
        /**  Remove Subscription
        /** --------------------------------------------*/
        
        // Overwrites all other subscriptions
        
        ee()->db->query("DELETE FROM exp_tag_subscriptions 
					WHERE tag_id = ".$query->row('tag_id')."
					AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
					AND member_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'");

        			
        /** --------------------------------------------
        /**  Add Subscription
        /** --------------------------------------------*/
        
        ee()->db->query(ee()->db->insert_string('exp_tag_subscriptions',
									  array('tag_id'	=> $query->row('tag_id'),
											'site_id'	=> ee()->config->item('site_id'),
											'member_id'	=> ee()->session->userdata['member_id'])));
        							  		
		/** --------------------------------------------
        /**  Output Successful Subscribe Message
        /** --------------------------------------------*/
        
        if (isset($_GET['return']))
        {
        	$return = (isset($_GET['return'])) ? ee()->functions->create_url( $_GET['return']) : ee()->functions->fetch_site_index();
        
        	$data = array(	'title' 	=> ee()->lang->line('tag_subscribed'),
							'heading'	=> ee()->lang->line('thank_you'),
							'content'	=> str_replace('%tag_name%', $query->row('tag_name'), ee()->lang->line('successful_tag_subscribe')),
							'link'		=> array($return, ee()->config->item('site_name'))
						 );
										
			ee()->output->show_message($data);
		}
		
		exit(ee()->lang->line('successful_tag_subscribe'));
	}
	/**	END subscribe */
	
	
	/**	----------------------------------------
    /**	UnSubscribe to Tag
    /**	----------------------------------------*/

	function unsubscribe( )
	{

		
		if (ee()->session->userdata['member_id'] == 0 OR ! isset($_GET['tag_id']))
		{
			return FALSE;
		}
		
		/** --------------------------------------------
        /**  Valid Tag ID?  Fetch Tag Name Too...
        /** --------------------------------------------*/
        
        $query = ee()->db->query("SELECT t.tag_id, t.tag_name
						   FROM exp_tag_tags AS t
						   WHERE t.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
						   AND t.tag_id = '".ee()->db->escape_str($_GET['tag_id'])."'");
				   
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		
		/** --------------------------------------------
        /**  Remove Subscription
        /** --------------------------------------------*/
        
        ee()->db->query("DELETE FROM exp_tag_subscriptions 
					WHERE tag_id = ".$query->row('tag_id')."
					AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
					AND member_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'");
						
        /** --------------------------------------------
        /**  Output Successful Subscribe Message
        /** --------------------------------------------*/
        
        if (isset($_GET['return']))
        {
			
			$return = (isset($_GET['return'])) ? ee()->functions->create_url( $_GET['return']) : ee()->functions->fetch_site_index();
			
			$data = array(	'title' 	=> ee()->lang->line('tag_unsubscribed'),
							'heading'	=> ee()->lang->line('thank_you'),
							'content'	=> str_replace('%tag_name%', $query->row('tag_name'), ee()->lang->line('successful_tag_unsubscribe')),
							'link'		=> array($return, ee()->config->item('site_name'))
						 );
											
			ee()->output->show_message($data);
		}
		
		exit(ee()->lang->line('successful_tag_unsubscribe'));
	}
	/**	END subscribe */
	
	
	
	/**	----------------------------------------
    /**	 List of Tags to Which Member is Subscribed
    /**	----------------------------------------*/

	function subscriptions()
	{	
		/**	----------------------------------------
		/**	Member ID Required
		/**	----------------------------------------*/

		if ( 
				ee()->session->userdata['member_id'] == 0 && 
				(ee()->TMPL->fetch_param('member_id') == FALSE OR ctype_digit(ee()->TMPL->fetch_param('member_id')) === FALSE)
		   )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/** --------------------------------------------
        /**  Check for Subscriptions
        /** --------------------------------------------*/
        
		$member_id = (ee()->TMPL->fetch_param('member_id') !== FALSE) ? ee()->TMPL->fetch_param('member_id') : ee()->session->userdata['member_id'];
        
        $sql = "SELECT		t.*, t.tag_name AS tag
				FROM		exp_tag_subscriptions AS ts
				LEFT JOIN	exp_tag_tags AS t ON ts.tag_id = t.tag_id
				WHERE		ts.member_id = '".ee()->db->escape_str($member_id)."'
				AND			ts.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
				
		/**	----------------------------------------
		/**	Exclude?
		/**	----------------------------------------*/

		if ( ee()->TMPL->fetch_param('exclude') !== FALSE AND ee()->TMPL->fetch_param('exclude') != '' )
		{
			$ids	= $this->_exclude( ee()->TMPL->fetch_param('exclude') );

			if ( is_array( $ids ) )
			{
				$sql	.= " AND t.tag_id NOT IN ('".implode( "','", ee()->db->escape_str($ids) )."')";
			}
		}
				
		// --------------------------------------
		//  Pagination
		// --------------------------------------

		$query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(t.tag_id) AS count FROM ', $sql));

		if ($query->row('count') == 0 AND 
			 strpos( ee()->TMPL->tagdata, 'paginate' ) !== FALSE)
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		$this->p_limit  	= ( ! ee()->TMPL->fetch_param('limit'))  ? 20 : ee()->TMPL->fetch_param('limit');
		$this->total_rows 	= $query->row('count');
		$this->p_page 		= ($this->p_page == '' || ($this->p_limit > 1 AND $this->p_page == 1)) ? 0 : $this->p_page;

		if ($this->p_page > $this->total_rows)
		{
			$this->p_page = 0;
		}		
		
		//get pagination info
		$pagination_data = $this->universal_pagination(array(
			'sql'					=> preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(t.tag_id) AS count FROM ', $sql), 
			'total_results'			=> $this->total_rows, 
			'tagdata'				=> ee()->TMPL->tagdata,
			'limit'					=> $this->p_limit,
			'uri_string'			=> ee()->uri->uri_string,
			'current_page'			=> $this->p_page,
		));

		//if we paginated, sort the data
		if ($pagination_data['paginate'] === TRUE)
		{
			$this->paginate			= $pagination_data['paginate'];
			$this->page_next		= $pagination_data['page_next']; 
			$this->page_previous	= $pagination_data['page_previous'];
			$this->p_page			= $pagination_data['pagination_page'];
			$this->current_page		= $pagination_data['current_page'];
			$this->pagination_links = $pagination_data['pagination_links'];
			$this->basepath			= $pagination_data['base_url'];
			$this->total_pages		= $pagination_data['total_pages'];
			$this->paginate_data	= $pagination_data['paginate_tagpair_data'];
			ee()->TMPL->tagdata		= $pagination_data['tagdata'];
		}
	
		/**	----------------------------------------
		/**	Set order by
		/**	----------------------------------------*/

		$ord	= " ORDER BY t.tag_name";
		
		$possible = array(	'random'							=> "rand()", 
							'clicks'							=> "t.clicks",
							'total_entries'						=> 't.total_entries', 
							'channel_entries'					=> 't.channel_entries', 
							'weblog_entries'					=> 't.channel_entries', 
							'gallery_entries'					=> 't.gallery_entries', 
							'tag_name'							=> 't.tag_name' );

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND ee()->TMPL->fetch_param('orderby') != '' )
		{
			foreach ( $possible as $key => $val )
			{
				if ( $key == ee()->TMPL->fetch_param('orderby') )
				{
					$ord	= " ORDER BY ".$val;
					break;
				}
			}
		}

		$sql .= $ord;

		/**	----------------------------------------
		/**	Set sort
		/**	----------------------------------------*/

		if (ee()->TMPL->fetch_param('orderby') !== 'random')
		{
			if ( ee()->TMPL->fetch_param('sort') !== FALSE AND ee()->TMPL->fetch_param('sort') == 'asc' )
			{
				$sql	.= " ASC";
			}
			else
			{
				$sql	.= " DESC";
			}
		}

		/**	----------------------------------------
		/**	Set numerical limit
		/**	----------------------------------------*/

		if ($this->paginate === TRUE && $this->total_rows > $this->p_limit)
		{
			$sql .= " LIMIT ".$this->p_page.', '.$this->p_limit;
		}
		else
		{
			$sql .= ( ctype_digit( ee()->TMPL->fetch_param('limit') ) === TRUE ) ? ' LIMIT '.ee()->TMPL->fetch_param('limit') : ' LIMIT 20';
        }

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/

		$query	= ee()->db->query( $sql );
        					 
        /** --------------------------------------------
        /**  Results?
        /** --------------------------------------------*/
        					 
        if ($query->num_rows() == 0)
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/**	----------------------------------------
		/**	Websafe separator
		/**	----------------------------------------*/

		$websafe_separator	= '+';

		if ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' )
		{
			$websafe_separator	= ee()->TMPL->fetch_param('websafe_separator');
		}
        
        /** --------------------------------------------
        /**  Build Output
        /** --------------------------------------------*/
        
        $r  = '';
        
		$qs	= (ee()->config->item('force_query_string') == 'y') ? '' : '?';
		
		$subscribe_links = (stristr(ee()->TMPL->tagdata, 'subscribe_link'.RD)) ? TRUE : FALSE;
		
		$total_results   = sizeof($query->result_array());

		foreach ( $query->result_array() as $count => $row )
		{
			$tagdata	= ee()->TMPL->tagdata;
			
			$row['count']		  	= $count + 1;
			$row['total_results'] 	= $total_results;

			//for 1.6.x
			$row['weblog_entries'] 	= $row['channel_entries'];

			/**	----------------------------------------
			/**	Add content
			/**	----------------------------------------*/

			$row['websafe_tag']	= str_replace( " ", $websafe_separator, $row['tag'] );

			/**	----------------------------------------
			/**	Parse conditionals
			/**	----------------------------------------*/
			
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $row );
			
			/** --------------------------------------------
			/**  Subscribe/Unsubscribe Links
			/** --------------------------------------------*/
			
			if ($subscribe_links === TRUE)
			{
				$tagdata = str_replace(LD.'subscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'subscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
				$tagdata = str_replace(LD.'unsubscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'unsubscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
			}
			
			/**	----------------------------------------
			/**	Parse singles
			/**	----------------------------------------*/

			foreach ( $row as $key => $val )
			{
				$tagdata	= ee()->TMPL->swap_var_single( $key, $val, $tagdata );
			}

			$r	.= $tagdata;
		}

		$backspace	= ( ctype_digit( ee()->TMPL->fetch_param('backspace') ) === TRUE ) ? ee()->TMPL->fetch_param('backspace'): 0;

		$this->return_data	= ( $backspace > 0 ) ? substr( $r, 0, - $backspace ): $r;
		
		
		/** --------------------------------------------
        /**  Pagination?
        /** --------------------------------------------*/

		if ($this->paginate == TRUE)
        {
			$this->paginate_data = str_replace(LD.'current_page'.RD, $this->current_page, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'total_pages'.RD,	$this->total_pages, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'pagination_links'.RD, $this->pagination_links, $this->paginate_data);

        	if (preg_match("/".LD."if previous_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_previous == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if previous_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_previous, $match['1']);
					$match['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_previous, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
       	 	}

        	if (preg_match("/".LD."if next_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_next == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if next_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_next, $match['1']);
					$match['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_next, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
        	}

			$position = ( ! ee()->TMPL->fetch_param('paginate')) ? '' : ee()->TMPL->fetch_param('paginate');

			switch ($position)
			{
				case "top"	: $this->return_data  = $this->paginate_data.$this->return_data;
					break;
				case "both"	: $this->return_data  = $this->paginate_data.$this->return_data.$this->paginate_data;
					break;
				default		: $this->return_data .= $this->paginate_data;
					break;
			}
        }
        
        /** --------------------------------------------
        /**  All Done, Switch Character Set and Return
        /** --------------------------------------------*/
		
		$this->actions()->db_charset_switch('default');

		return $this->return_data;
	}
	/** End subscriptions() */
	
	
	/**	----------------------------------------
    /**	 List of Tag with Ranking by Number of Subscriptions
    /**	----------------------------------------*/

	function subscriptions_rank()
	{

		
		/** --------------------------------------------
        /**  Start Building Query
        /** --------------------------------------------*/
        
        $sql = "SELECT		t.*, t.tag_name AS tag, COUNT(ts.tag_id) AS subscription_rank 
				FROM		exp_tag_subscriptions AS ts
				INNER JOIN	exp_tag_tags AS t ON ts.tag_id = t.tag_id
				WHERE		ts.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
        
        /** --------------------------------------------
        /**  Member ID?
        /** --------------------------------------------*/
        
        if (ee()->TMPL->fetch_param('member_id') !== FALSE)
        {
        	$sql .= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('member_id'), ' ts.member_id');
        }
        
        /** --------------------------------------------
        /**  Tag ID?
        /** --------------------------------------------*/
        
        if (ee()->TMPL->fetch_param('tag_id') !== FALSE)
        {
        	$sql .= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('tag_id'), ' ts.tag_id');
        }
        
        /** --------------------------------------------
        /**  Tag ID?
        /** --------------------------------------------*/
        
        if (ee()->TMPL->fetch_param('tag') !== FALSE)
        {
        	$sql .= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('tag'), ' t.tag_name');
        }

		// --------------------------------------
		//  Pagination checkeroo! - Do Before GROUP BY!
		// --------------------------------------

		$query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(DISTINCT t.tag_id) AS count FROM ', $sql));

		if ($query->row('count') == 0 AND 
			 strpos( ee()->TMPL->tagdata, 'paginate' ) !== FALSE)
		{
			$this->actions()->db_charset_switch('default');
			return $this->return_data = $this->_no_results('tag');
		}
		
		$this->p_limit  	= ( ! ee()->TMPL->fetch_param('limit'))  ? 20 : ee()->TMPL->fetch_param('limit');
		$this->total_rows 	= $query->row('count');
		$this->p_page 		= ($this->p_page == '' || ($this->p_limit > 1 AND $this->p_page == 1)) ? 0 : $this->p_page;

		if ($this->p_page > $this->total_rows)
		{
			$this->p_page = 0;
		}
		
		//get pagination info
		$pagination_data = $this->universal_pagination(array(
			'sql'					=> preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(DISTINCT t.tag_id) AS count FROM ', $sql), 
			'total_results'			=> $this->total_rows, 
			'tagdata'				=> ee()->TMPL->tagdata,
			'limit'					=> $this->p_limit,
			'uri_string'			=> ee()->uri->uri_string,
			'current_page'			=> $this->p_page,
		));

		//if we paginated, sort the data
		if ($pagination_data['paginate'] === TRUE)
		{
			$this->paginate			= $pagination_data['paginate'];
			$this->page_next		= $pagination_data['page_next']; 
			$this->page_previous	= $pagination_data['page_previous'];
			$this->p_page			= $pagination_data['pagination_page'];
			$this->current_page		= $pagination_data['current_page'];
			$this->pagination_links = $pagination_data['pagination_links'];
			$this->basepath			= $pagination_data['base_url'];
			$this->total_pages		= $pagination_data['total_pages'];
			$this->paginate_data	= $pagination_data['paginate_tagpair_data'];
			ee()->TMPL->tagdata		= $pagination_data['tagdata'];
		}
		
		/**	----------------------------------------
		/**	Set group by
		/**	----------------------------------------*/

		$sql .= " GROUP BY ts.tag_id";

		/**	----------------------------------------
		/**	Set order by
		/**	----------------------------------------*/

		$ord	= " ORDER BY subscription_rank";
		
		$possible = array(	'random'							=> "rand()",
							'total_entries'						=> 't.total_entries',
							'channel_entries'					=> 't.channel_entries', 
							'weblog_entries'					=> 't.channel_entries', 
							'gallery_entries'					=> 't.gallery_entries', 
							'tag_name'							=> 't.tag_name',
							'subscription_rank'					=> 'subscription_rank');

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND ee()->TMPL->fetch_param('orderby') != '' )
		{
			foreach ( $possible as $key => $val )
			{
				if ( $key == ee()->TMPL->fetch_param('orderby') )
				{
					$ord	= " ORDER BY ".$val;
					break;
				}
			}
		}

		$sql .= $ord;


		/**	----------------------------------------
		/**	Set sort
		/**	----------------------------------------*/

		if (ee()->TMPL->fetch_param('orderby') !== 'random')
		{
			if ( ee()->TMPL->fetch_param('sort') !== FALSE AND ee()->TMPL->fetch_param('sort') == 'asc' )
			{
				$sql	.= " ASC";
			}
			else
			{
				$sql	.= " DESC";
			}
		}

		/**	----------------------------------------
		/**	Set numerical limit
		/**	----------------------------------------*/

		if ($this->paginate === TRUE && $this->total_rows > $this->p_limit)
		{
			$sql .= " LIMIT ".$this->p_page.', '.$this->p_limit;
		}
		else
		{
			$sql .= ( ctype_digit( ee()->TMPL->fetch_param('limit') ) === TRUE ) ? ' LIMIT '.ee()->TMPL->fetch_param('limit') : ' LIMIT 20';
        }

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/

		$query	= ee()->db->query( $sql );

		/**	----------------------------------------
		/**	Empty?
		/**	----------------------------------------*/

		if ( $query->num_rows() == 0 )
		{
			$this->actions()->db_charset_switch('default');
			return $this->_no_results('tag');
		}
		
		/**	----------------------------------------
		/**	Websafe separator
		/**	----------------------------------------*/

		$websafe_separator	= '+';

		if ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' )
		{
			$websafe_separator	= ee()->TMPL->fetch_param('websafe_separator');
		}
        
        /** --------------------------------------------
        /**  Build Output
        /** --------------------------------------------*/
        
        $r  = '';
        
		$qs	= (ee()->config->item('force_query_string') == 'y') ? '' : '?';
		
		$subscribe_links = (stristr(ee()->TMPL->tagdata, 'subscribe_link'.RD)) ? TRUE : FALSE;
		
		$total_results   = sizeof($query->result_array());

		foreach ( $query->result_array() as $count => $row )
		{
			$tagdata	= ee()->TMPL->tagdata;
			
			$row['count']		  	= $count + 1;
			$row['total_results'] 	= $total_results;
			$row['absolute_count']	= $this->p_page + $row['count'];
			$row['weblog_entries']	= $row['channel_entries'];

			/**	----------------------------------------
			/**	Add content
			/**	----------------------------------------*/

			$row['websafe_tag']	= str_replace( " ", $websafe_separator, $row['tag'] );

			/**	----------------------------------------
			/**	Parse conditionals
			/**	----------------------------------------*/
			
			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $row );
			
			/** --------------------------------------------
			/**  Subscribe/Unsubscribe Links
			/** --------------------------------------------*/
			
			if ($subscribe_links === TRUE)
			{
				$tagdata = str_replace(LD.'subscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'subscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
				$tagdata = str_replace(LD.'unsubscribe_link'.RD, ee()->functions->fetch_site_index(0, 0).$qs.'ACT='.ee()->functions->fetch_action_id('Tag', 'unsubscribe').'&amp;tag_id='.$row['tag_id'], $tagdata);
			}
			
			/**	----------------------------------------
			/**	Parse singles
			/**	----------------------------------------*/

			foreach ( $row as $key => $val )
			{
				$tagdata	= ee()->TMPL->swap_var_single( $key, $val, $tagdata );
			}

			$r	.= $tagdata;
		}

		$backspace	= ( ctype_digit( ee()->TMPL->fetch_param('backspace') ) === TRUE ) ? ee()->TMPL->fetch_param('backspace'): 0;

		$this->return_data	= ( $backspace > 0 ) ? substr( $r, 0, - $backspace ): $r;
		
		
		/** --------------------------------------------
        /**  Pagination?
        /** --------------------------------------------*/

		if ($this->paginate == TRUE)
        {
			$this->paginate_data = str_replace(LD.'current_page'.RD, $this->current_page, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'total_pages'.RD,	$this->total_pages, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'pagination_links'.RD, $this->pagination_links, $this->paginate_data);

        	if (preg_match("/".LD."if previous_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_previous == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if previous_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_previous, $match['1']);
					$match['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_previous, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
       	 	}

        	if (preg_match("/".LD."if next_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_next == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if next_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_next, $match['1']);
					$match['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_next, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
        	}

			$position = ( ! ee()->TMPL->fetch_param('paginate')) ? '' : ee()->TMPL->fetch_param('paginate');

			switch ($position)
			{
				case "top"	: $this->return_data  = $this->paginate_data.$this->return_data;
					break;
				case "both"	: $this->return_data  = $this->paginate_data.$this->return_data.$this->paginate_data;
					break;
				default		: $this->return_data .= $this->paginate_data;
					break;
			}
        }
        
        /** --------------------------------------------
        /**  All Done, Switch Character Set and Return
        /** --------------------------------------------*/
		
		$this->actions()->db_charset_switch('default');

		return $this->return_data;
	}
	/** End subscriptions_rank() */
	
	
	/**	----------------------------------------
	/**	 Number of Subscriptions to a Tag
	/**	----------------------------------------*/

	function subscriptions_count()
	{


		$marker		= ( ee()->TMPL->fetch_param('marker') ) ? ee()->TMPL->fetch_param('marker'): 'tag';
		$dynamic	= ( ee()->TMPL->fetch_param('dynamic') !== FALSE AND $this->check_no(ee()->TMPL->fetch_param('dynamic'))) ? 'off': 'on';

		$qstring = (ee()->uri->page_query_string != '') ? ee()->uri->page_query_string : ee()->uri->query_string;

		/**	----------------------------------------
		/**	Tag provided?
		/**	----------------------------------------*/
		
		if ( ee()->TMPL->fetch_param('tag_id') !== FALSE && ctype_digit(ee()->TMPL->fetch_param('tag_id')))
		{
			$tag_id = ee()->TMPL->fetch_param('tag_id');
		}
		else
		{
			if ( ee()->TMPL->fetch_param('tag') !== FALSE )
			{
				$tag	= ee()->TMPL->fetch_param('tag');
			}
	
			elseif ( $key = array_pop(array_keys( ee()->uri->segments, $marker ) ) )
			{
				if ( isset( ee()->uri->segments[ $key + 1 ] ) )
				{
					$tag	= rawurldecode(ee()->uri->segments[ $key + 1 ]);
				}
			}
			
			if ( ! isset($tag))
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
			
			/**	----------------------------------------
			/**	Remove reserved characters and Clean
			/**	----------------------------------------*/
	
			$websafe_separator		= ( ee()->TMPL->fetch_param('websafe_separator') !== FALSE AND ee()->TMPL->fetch_param('websafe_separator') != '' ) ? ee()->TMPL->fetch_param('websafe_separator'): '+';
	
			$tag = str_replace( $websafe_separator, " ", $tag );
			$tag = str_replace( "%20", " ", $tag );
			$tag = $this->_clean_str( $tag );
			
			/** --------------------------------------------
			/**  Find Tag ID
			/** --------------------------------------------*/
			
			$sql		 = "SELECT tag_id FROM exp_tag_tags AS t ";
			
			$binary		 = ( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

			$sql		.= " WHERE t.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";

			$sql		.= ee()->functions->sql_andor_string( $tag, $binary.' t.tag_name');
			
			$query = ee()->db->query($sql);
			
			if ($query->num_rows() == 0)
			{
				$this->actions()->db_charset_switch('default');
				return $this->_no_results('tag');
			}
			
			$tag_id = $query->row('tag_id');
		}
		
		/** --------------------------------------------
        /**  Find Subscriptions
        /** --------------------------------------------*/
        
        $sql = "SELECT COUNT(member_id) AS count 
        		FROM 	exp_tag_subscriptions
        		WHERE	tag_id = '".ee()->db->escape_str($tag_id)."' 
        		AND		site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
		
		$query = ee()->db->query($sql);
		
		/** --------------------------------------------
        /**  Output
        /** --------------------------------------------*/
		
		return $this->return_data = str_replace(LD.'subscriptions_count'.RD, $query->row('count'), ee()->TMPL->tagdata);
	}
	/* END subscriptions_count() */


    /**	----------------------------------------
    /**	Delete
    /**	----------------------------------------*/

	function delete( $entry_ids, $type = 'channel' )
	{
		if ($type == 'weblog')
		{
			$type = 'channel';
		}

		if ( ! is_array($entry_ids) OR count( $entry_ids ) == 0 ) return;

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/

        $sql = "SELECT tag_id, entry_id FROM exp_tag_entries 
        		WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."' 
        		AND type = '".ee()->db->escape_str( $type )."' AND
        		entry_id IN ('".implode("','", ee()->db->escape_str( $entry_ids ))."')";

        $query = ee()->db->query($sql);

		/**	----------------------------------------
		/**	Delete entries
		/**	----------------------------------------*/

		if ( $query->num_rows() == 0 ) return;

		$ids = array();

		foreach( $query->result_array() as $row )
		{
			$ids[] = $row['entry_id'];
		}

		ee()->db->query("DELETE FROM exp_tag_entries WHERE entry_id IN ('".implode("','", ee()->db->escape_str( $ids ))."')");

		/**	----------------------------------------
		/**	Clean-up dead tags
		/**	----------------------------------------*/

		$this->_clean();

		/**	----------------------------------------
		/**	Return
		/**	----------------------------------------*/

		return;
	}

	/**	END delete */


    /**	----------------------------------------
    /**	Clean tag
    /**	----------------------------------------*/

    function _clean_str( $str = '' )
    {
    	$not_allowed = array('$', '?', ')', '(', '!', '<', '>', '/');

    	$str = str_replace($not_allowed, '', $str);

		$str	= ( $this->preference('convert_case') != 'n' ) ? $this->_strtolower( $str ): $str;

		if (ee()->config->item('auto_convert_high_ascii') == 'y')
		{
			ee()->load->helper('text');
			
			$str = ascii_to_entities( $str );
		}

		//remove any double spacing
		//$str = preg_replace('/ (?: +)/', ' ', $str);

		return ee()->security->xss_clean( $str );
    }
    /**	END clean tag */


	/**	----------------------------------------
	/**	Clean-up dead tags
	/**	----------------------------------------*/

	function _clean()
	{
		/**	----------------------------------------
		/**	Remove tags with no entries
		/**	----------------------------------------*/

		$query	= ee()->db->query( "SELECT t.tag_id, COUNT(e.tag_id) AS count
								   FROM exp_tag_tags t
								   LEFT JOIN exp_tag_entries e ON e.tag_id = t.tag_id
								   GROUP BY e.tag_id DESC" );

		foreach ( $query->result_array() as $row )
		{
			if ( $row['count'] == '0' )
			{
				ee()->db->query( "DELETE FROM exp_tag_tags WHERE tag_id = '".$row['tag_id']."'" );
				ee()->db->query( "DELETE FROM exp_tag_subscriptions WHERE tag_id = '".$row['tag_id']."'" );
			}
		}
	}

	/**	END clean up */


	/**	----------------------------------------
	/**	 Find First Character
	/**	----------------------------------------*/

	function _first_character($str)
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

	/**	END first character */


	/**	----------------------------------------
	/**	 String to Lower
	/**	----------------------------------------*/

	function _strtolower($str)
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

	/**	Make String into Lower Case */


	/**	----------------------------------------
	/**	Recount
	/**	----------------------------------------*/

	function _recount ( $data = array() )
	{
		if (isset($data['tag_id']))
		{
			$this->actions()->recount_tags($data['tag_id']);
		}
	}

	/**	END recount */


	/**	----------------------------------------
	/**	Count tag
	/**	----------------------------------------*/

	function _count_tag ( $page = 1 )
	{
		if ( $this->tag == '' OR $page > 1 ) return FALSE;

		/**	----------------------------------------
		/**	Get array of tags
		/**	----------------------------------------*/

		$tags	= explode( "|", ee()->db->escape_str( $this->tag ) );

		/**	----------------------------------------
		/**	Get tags
		/**	----------------------------------------*/

		$sql	= "UPDATE exp_tag_tags SET clicks = (clicks + 1) WHERE";

		$sql	.=	( $this->preference('convert_case') != 'n' ) ? "": " BINARY";

		$sql	.= " tag_name IN ('".implode( "','", ee()->db->escape_str($tags) )."')";

		$query	= ee()->db->query( $sql );

		return TRUE;
	}

	/**	END count tag */



	/**	----------------------------------------
	/**	Exclude tags
	/**	----------------------------------------*/

	function _exclude ( $str = '' )
	{

		
		/**	----------------------------------------
		/**	Parse string
		/**	----------------------------------------*/
		
		if ( $str == '' ) return FALSE;
		
		$ids	= array();
		$like	= array();
		$excludes	= preg_split( "/,|\|/", $str );
		
		/** --------------------------------------------
		/**	Begin query
		/** --------------------------------------------*/

		$sql = "SELECT tag_id FROM exp_tag_tags 
				WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."') ";
		
		/** --------------------------------------------
		/**	Check for token so we know what kind of
		/** search to do. % = token
		/** --------------------------------------------*/
		
		foreach ($excludes as $key => $value)
		{
			if ( strpos( $value, '%' ) !== FALSE )
			{
				$like[] = "tag_name LIKE '".ee()->db->escape_str( $value )."'";
				unset($excludes[$key]);
			}
		}
		
		/** --------------------------------------------
		/**	Check for plain Jane tags
		/** --------------------------------------------*/

		if ( count($excludes) > 0 )
		{
			$like[] = "tag_name IN ('".implode( "','", ee()->db->escape_str( $excludes ) )."')";
		}
		
		/** --------------------------------------------
		/**	Tack on LIKE searches
		/** --------------------------------------------*/

		if ( count($like) > 0 )
		{
			$sql .= "AND (".implode(' OR ', $like).")";
		}
		
		/** --------------------------------------------
		/**	Run the query
		/** --------------------------------------------*/

		$query = ee()->db->query($sql);
		
		foreach ( $query->result_array() as $row )
		{
			$ids[]	= $row['tag_id'];
		}
		
		return ( count($ids) > 0 ) ? $ids : FALSE;
	}
	/**	END exclude */

	/**	----------------------------------------
    /**	No results
    /**	----------------------------------------*/

    function _no_results ( $str = '' )
    {
    	$this->actions()->db_charset_switch('default');

    	if( $str != '' AND 
			preg_match( 
				"/" . LD . "if no_" . trim($str, '_') . "_results" . RD .
				"(.*?)". LD . preg_quote(T_SLASH, '/') . "if" . RD . "/s", 
				ee()->TMPL->tagdata, 
				$match 
			) )
    	{
    		return $match['1'];
    	}
    	else
    	{
    		return $this->no_results();
    	}
    }
    // End no results

	/**	----------------------------------------
	/**	Get bad tags
	/**	----------------------------------------*/

	function bad()
	{
		/**	----------------------------------------
		/**	Have we already done this?
		/**	----------------------------------------*/

		if ( $this->bad !== FALSE )
		{
			return $this->bad;
		}
		
		$this->bad = array();

		/**	----------------------------------------
		/**	Do it
		/**	----------------------------------------*/

		$sql	= "SELECT tag_name FROM exp_tag_bad_tags";

		if ( isset( $TMPL ) )
		{
			$sql	.= " WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')";
		}
		else
		{
			$sql	.= " WHERE site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'";
		}

		$query	= ee()->db->query( $sql );

		/**	----------------------------------------
		/*	Adding an empty tag prevents the module from checking the database for every
		/*	single tag in the tag cloud when there are no bad tags registered for the site.
		/**	----------------------------------------*/

		foreach ( $query->result_array() as $row )
		{
			$this->bad[] = $row['tag_name'];
		}

		return $this->bad;
	}

	/**	END get bad tags */


    //	----------------------------------------
    //	String to array
    //	----------------------------------------

    function str_arr ( $remove_slashes = FALSE)
    {	
    	if ($remove_slashes === TRUE)
    	{
    		$this->str = stripslashes($this->str);
    	}

    	$this->str	= ( $this->preference('convert_case') != 'n' ) ? 
						$this->_strtolower( $this->str ) : $this->str;

    	if ( $this->preference('separator') == 'comma' )
    	{
			$arr	= preg_split( "/,|\n|\r/", $this->str, -1, PREG_SPLIT_NO_EMPTY);
    	}
    	elseif ( $this->preference('separator') == 'semicolon' )
    	{
			$arr	= preg_split( "/;|\n|\r/", $this->str, -1, PREG_SPLIT_NO_EMPTY);
    	}
    	elseif ( $this->preference('separator') == 'colon' )
    	{
			$arr	= preg_split( "/:|\n|\r/", $this->str, -1, PREG_SPLIT_NO_EMPTY);
    	}
    	elseif ( $this->preference('separator') == 'space' )
    	{
    		$str		= str_replace( "\\", "", $this->str );

			//why?
    		$quotes		= preg_match_all( '/"([^"]*?)"/s', $str, $match );

    		$str		= str_replace( $match['0'], "", $str );

			$arr		= preg_split( "/\s|\n|\r/", $str, -1, PREG_SPLIT_NO_EMPTY);

			$arr		= array_merge( $arr, $match['1'] );
    	}
    	else
    	{
			$arr	= preg_split( "/\n|\r/", $this->str, -1, PREG_SPLIT_NO_EMPTY);
    	}

    	foreach ( $arr as $key => $val )
    	{
    		$arr[$key]	= trim($val);
    	}

		// Maximum Allowed Tags Check
		if ( $this->preference('publish_entry_tag_limit') != 0 	AND
				is_numeric($this->preference('publish_entry_tag_limit')) 		AND 
			REQ == 'CP' 														AND
			count($arr) >= ceil($this->preference('publish_entry_tag_limit'))	)
		{
			$arr = array_slice($arr, 0, $this->preference('publish_entry_tag_limit'));
		}
    	
    	return $arr;
    }
    //	END string to array


    /**	----------------------------------------
    /**	Array to DB string
    /**	----------------------------------------*/

    function array_dbstr ( $arr )
    {
    	return implode( "','", ee()->db->escape_str($arr) );
    }

    /**	END array to DB string */


    /**	----------------------------------------
    /**	Entry id
    /**	----------------------------------------*/

    function _entry_id( $type = 'channel' )
    {
    	if ($type == 'weblog')
		{
			$type = 'channel';
		}
		
		ee()->load->helper('string');
    
		/**	----------------------------------------
		/**	Prep type
		/**	----------------------------------------*/
		
		$types = array( 'channel'	=> $this->sc->db->channel_titles,
						'gallery'	=> 'exp_gallery_entries');
		
		$type = (isset($types[$type])) ? $types[$type] : $this->sc->db->channel_titles;

		/**	----------------------------------------
		/**	Cat segment
		/**	----------------------------------------*/

		$cat_segment	= ee()->config->item("reserved_category_word");

		/**	----------------------------------------
		/**	Begin matching
		/**	----------------------------------------*/

		$psql	= "SELECT entry_id FROM `".$type."` WHERE entry_id = '%eid'";

		if ( ctype_digit( ee()->TMPL->fetch_param('entry_id') ) === TRUE )
		{
			$sql	= str_replace( "%eid", ee()->db->escape_str( ee()->TMPL->fetch_param('entry_id') ), $psql );

			$query	= ee()->db->query( $sql );

			if ( $query->num_rows() > 0 )
			{
				$this->entry_id	= $query->row('entry_id');

				return TRUE;
			}
		}
		elseif ( ee()->uri->query_string != '' OR ( isset( ee()->uri->page_query_string ) === TRUE AND ee()->uri->page_query_string != '' ) )
		{
			$qstring = ( ee()->uri->page_query_string != '' ) ? ee()->uri->page_query_string : ee()->uri->query_string;

			/**	----------------------------------------
			/**	Do we have a pure ID number?
			/**	----------------------------------------*/

			if ( ctype_digit( $qstring ) === TRUE )
			{
				$sql	= str_replace( "%eid", ee()->db->escape_str( $qstring ), $psql );

				$query	= ee()->db->query( $sql );

				if ( $query->num_rows() > 0 )
				{
					$this->entry_id	= $query->row('entry_id');

					return TRUE;
				}
			}
			else
			{
				/**	----------------------------------------
				/**	Parse day
				/**	----------------------------------------*/

				if (preg_match("#\d{4}/\d{2}/(\d{2})#", $qstring, $match))
				{
					$partial	= substr($match['0'], 0, -3);

					$qstring	= trim_slashes(str_replace($match['0'], $partial, $qstring));
				}

				/**	----------------------------------------
				/**	Parse /year/month/
				/**	----------------------------------------*/

				if (preg_match("#(\d{4}/\d{2})#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['1'], '', $qstring));
				}

				/**	----------------------------------------
				/**	Parse page number
				/**	----------------------------------------*/

				if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['0'], '', $qstring));
				}

				/**	----------------------------------------
				/**	Parse category indicator
				/**	----------------------------------------*/

				// Text version of the category

				if (preg_match("#^".$cat_segment."/#", $qstring, $match) AND ee()->TMPL->fetch_param($this->sc->channel))
				{
					$qstring	= str_replace($cat_segment.'/', '', $qstring);

					$sql		= "SELECT DISTINCT cat_group FROM {$this->sc->db->channels}
								   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."') ";

					$sql	.= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param($this->sc->channel), $this->sc->db->channel_name);

					$query	= ee()->db->query($sql);

					if ($query->num_rows() == 1)
					{
						$result	= ee()->db->query("SELECT cat_id
											  FROM exp_categories
											  WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
											  AND cat_name='".ee()->db->escape_str($qstring)."' AND group_id='".ee()->db->escape_str($query->row('cat_group'))."'");

						if ($result->num_rows() == 1)
						{
							$qstring	= 'C'.$result->row('cat_id');
						}
					}
				}

				/**	----------------------------------------
				/**	Numeric version of the category
				/**	----------------------------------------*/

				if (preg_match("#^C(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['0'], '', $qstring));
				}

				/**	----------------------------------------
				/**	Remove "N"
				/**	----------------------------------------*/

				// The recent comments feature uses "N" as the URL indicator
				// It needs to be removed if presenst

				if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match['0'], '', $qstring));
				}

				/**	----------------------------------------
				/**	Parse URL title
				/**	----------------------------------------*/

				if (strstr($qstring, '/'))
				{
					$xe			= explode('/', $qstring);
					$qstring	= current($xe);
				}

				$sql	= "SELECT wt.entry_id
							FROM {$this->sc->db->channel_titles} AS wt, {$this->sc->db->channels} AS w
							WHERE wt.{$this->sc->db->channel_id} = w.{$this->sc->db->channel_id}
							AND wt.site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
							AND wt.url_title = '".ee()->db->escape_str($qstring)."'";


				$query	= ee()->db->query($sql);

				if ( $query->num_rows() > 0 )
				{
					$this->entry_id = $query->row('entry_id');

					return TRUE;
				}
				
				/** --------------------------------------------
				/**  Entry ID Only?
				/** --------------------------------------------*/
				
				if ( ctype_digit($qstring))
				{
					$this->entry_id = $qstring;
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**	END entry id */




    /**	----------------------------------------
    /**	Parse from cp
    /**	----------------------------------------
    /*	This method is called when the CP needs
    /*	to parse tags.
    /**	----------------------------------------*/

    function _parse_from_gallery_cp( $entry_id ) { $this->_parse_from_cp( $entry_id, array(), 'gallery' ); }

    function _parse_from_gallery_extended_cp( $entry_id ) { $this->_parse_from_cp( $entry_id, array(), 'gallery' ); }

    function _parse_from_cp( $entry_id = '', $tag_data = array(), $type = 'channel' )
    {
    	if ($type == 'weblog')
		{
			$type = 'channel';
		}
    
		$this->type	= $type;

		/**	----------------------------------------
		/**	Branch for bundling
		/**	----------------------------------------
		/*	In case we get bundled, let's branch here. We prefer to take in tags from the tag tab
		/**	----------------------------------------*/

		if ( isset( $_POST['tag_f'] ) )
		{
			/**	----------------------------------------
			/**	Tag field as array?
			/**	----------------------------------------*/

			if ( is_array( $_POST['tag_f'] ) === TRUE )
			{
				foreach ( $_POST['tag_f'] as $str )
				{
					$this->str	.= "\n\r".$str; 
					//WHAT? Carriage returns?Thank god most of the time this is a text input - gf
				}
			}
			else
			{
				$this->str	= ee()->input->post('tag_f');
			}

			/**	----------------------------------------
			/**	Handle type - Channel or Gallery
			/**	----------------------------------------*/

			if ( $type == 'channel' )
			{
				$query	= ee()->db->query( 
					"SELECT {$this->sc->db->channel_id}, site_id 
					 FROM 	{$this->sc->db->channel_titles} 
					 WHERE 	entry_id = '" . ee()->db->escape_str($entry_id) . "'" 
				);

				if ( $query->num_rows() > 0 )
				{
					$this->channel_id	= $query->row($this->sc->db->channel_id);
					$this->site_id		= $query->row('site_id');
					$this->entry_id		= $entry_id;
				}
			}
			elseif ( $type == 'gallery' AND $entry_id != '' )
			{
				$query	= ee()->db->query( 
					"SELECT gallery_id 
					 FROM 	exp_gallery_entries 
					 WHERE 	entry_id = '" . ee()->db->escape_str($entry_id) . "'" 
				);

				if ( $query->num_rows() == 0 )
				{
					return;
				}

				$this->channel_id	= $query->row('gallery_id');
				$this->entry_id		= $entry_id;
				$this->site_id		= ee()->config->item('site_id');
			}
			else
			{
				return;
			}
		}
		elseif ( $this->type == 'channel' )
		{
			/**	----------------------------------------
			/**	Tag field exists?
			/**	----------------------------------------*/

			$query	= ee()->db->query( 
				"SELECT t.{$this->sc->db->channel_id} 
				 FROM 	{$this->sc->db->channel_titles} AS t
		   		 WHERE 	t.entry_id = '".ee()->db->escape_str($entry_id)."'
				 LIMIT 	1" 
			);

			if ( $query->num_rows() == 0 ) return;
			
			$field_id = $this->preference($query->row($this->sc->db->channel_id).'_tag_field');
			
			if (empty($field_id) OR ! $this->column_exists("field_id_{$field_id}", $this->sc->db->channel_data)) return;

			/**	----------------------------------------
			/**	Get the field
			/**	----------------------------------------*/

			$sub	= ee()->db->query( 
				"SELECT {$this->sc->db->channel_id}, site_id, field_id_{$field_id} AS f
				 FROM 	{$this->sc->db->channel_data}
				 WHERE 	entry_id = '" . ee()->db->escape_str($entry_id) . "'
				 LIMIT 	1" 
			);

			if ( $sub->num_rows() == 0 ) return;

			/**	----------------------------------------
			/**	Prep vars
			/**	----------------------------------------*/

			$this->channel_id	= $sub->row($this->sc->db->channel_id);
			$this->site_id		= $sub->row('site_id');
			$this->entry_id		= $entry_id;
			$this->str			= $sub->row('f');
		}

		return $this->parse();
    }

    /**	END parse from cp */


    /**	----------------------------------------
    /**	Stats
	/**	----------------------------------------*/

    function stats()
    {


    	$t_entries = 0;
    	$p_entries = 0;
    	$gt_entries = 0;
    	$pg_entries = 0;
    	$ranked = array();

    	$this->return_data = ee()->TMPL->tagdata;

		/**	----------------------------------------
		/**	Query
		/**	----------------------------------------*/

		$tags = ee()->db->query( "SELECT COUNT(*) AS count FROM exp_tag_tags WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')" );

		if (stristr ( ee()->TMPL->tagdata, $this->sc->channel.'_entries_tagged'.RD ) !== FALSE)
		{
			$t_entries	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_tag_entries
									   WHERE type = 'channel'
									   AND site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
									   GROUP BY entry_id" );

			$t_entries	= ( $t_entries->num_rows() > 0 ) ? $t_entries->num_rows(): 0;

			$entries	= ee()->db->query( "SELECT COUNT(*) AS count FROM {$this->sc->db->channel_titles} WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')" );

			$p_entries	= ( $entries->row('count') != 0 ) ? round( $t_entries / $entries->row('count') * 100, 2): 0;
		}

		/**	----------------------------------------
		/**	Check gallery?
		/**	----------------------------------------*/

		$gt_entries	= 0;
		$pg_entries	= 0;

		if ( ee()->db->table_exists('exp_gallery_entries') === TRUE && stristr ( ee()->TMPL->tagdata, 'gallery_entries_tagged'.RD ) !== FALSE)
		{
			$gt_entries	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_tag_entries WHERE type = 'gallery' AND site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."') GROUP BY entry_id" );

			$gt_entries	= ( $gt_entries->num_rows() > 0 ) ? $gt_entries->num_rows(): 0;

			$g_entries	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_gallery_entries" );

			$pg_entries	= ( $g_entries->row('count') != 0 ) ? round( $gt_entries / $g_entries->row('count') * 100, 2): 0;
		}

		if (preg_match_all("/".preg_quote(LD)."top_([0-9]+)_tags".preg_quote(RD)."/", ee()->TMPL->tagdata, $matches) !== FALSE)
		{
			foreach($matches[1] as $number)
			{
				$top5		= ee()->db->query( "SELECT t.tag_name FROM exp_tag_tags t
										   WHERE site_id IN ('".implode("','", ee()->db->escape_str(ee()->TMPL->site_ids))."')
										   ORDER BY t.total_entries DESC LIMIT ".ceil($number) );

				$ranked = array();

				foreach ( $top5->result_array() as $row )
				{
					$ranked[] = $row['tag_name'];
				}

				$this->return_data = str_replace(LD.'top_'.ceil($number).'_tags'.RD, implode(', ', $ranked), $this->return_data);
			}
		}

		/**	----------------------------------------
		/**	Data
		/**	----------------------------------------*/

		$data = array(LD.'total_tags'.RD						=> $tags->row('count'),
					  LD.'total_channel_entries_tagged'.RD		=> $t_entries,
					  LD.'percent_channel_entries_tagged'.RD	=> $p_entries,
					  LD.'total_weblog_entries_tagged'.RD		=> $t_entries,
					  LD.'percent_weblog_entries_tagged'.RD		=> $p_entries,
					  LD.'total_gallery_entries_tagged'.RD		=> $gt_entries,
					  LD.'percent_gallery_entries_tagged'.RD	=> $pg_entries);

        return $this->return_data = str_replace(array_keys($data), array_values($data), $this->return_data);
    }

    /**	END stats */


    /**	----------------------------------------
    /**	Chars decode
    /**	----------------------------------------*/

    function _chars_decode( $str = '' )
    {
    	if ( $str == '' ) return;

    	$str	= str_replace( array( "'", "\"", "&#47;" ), array( "", "", "/" ), $str );

    	if ( function_exists( 'html_entity_decode' ) === TRUE )
    	{
    		$str	= $this->_html_entity_decode_full( $str, ENT_NOQUOTES );
    	}

    	$str	= stripslashes( $str );

    	return $str;
    }

	function _html_entity_decode_full($string, $quotes = ENT_COMPAT, $charset = 'ISO-8859-1')
	{
		return html_entity_decode(
			preg_replace_callback(
				'/&([a-zA-Z][a-zA-Z0-9]+);/', 
				array(
					$this, 
					'_convert_entity'
				), 
				$string
			), 
			$quotes, 
			$charset
		);
	}

	function _convert_entity($matches, $destroy = TRUE)
	{
		$table = array(
			'Aacute' 	=> '&#193;',	'iacute' 	=> '&#237;',	'plusmn' 	=> '&#177;',
			'aacute' 	=> '&#225;',    'icirc' 	=> '&#238;',    'pound' 	=> '&#163;',
			'acirc' 	=> '&#226;',    'Icirc'  	=> '&#206;',    'prime' 	=> '&#8242;',
			'Acirc'  	=> '&#194;',    'iexcl' 	=> '&#161;',    'Prime' 	=> '&#8243;',
			'acute' 	=> '&#180;',    'Igrave' 	=> '&#204;',    'prod' 		=> '&#8719;',
			'aelig' 	=> '&#230;',    'igrave' 	=> '&#236;',    'prop' 		=> '&#8733;',
			'AElig'  	=> '&#198;',    'image' 	=> '&#8465;',   'Psi' 		=> '&#936;',
			'Agrave' 	=> '&#192;',    'infin' 	=> '&#8734;',   'psi' 		=> '&#968;',
			'agrave' 	=> '&#224;',    'int' 		=> '&#8747;',   'quot' 		=> '&#34;',
			'alefsym' 	=> '&#8501;',   'Iota' 		=> '&#921;',    'radic' 	=> '&#8730;',
			'Alpha' 	=> '&#913;',    'iota' 		=> '&#953;',    'rang' 		=> '&#9002;',
			'alpha' 	=> '&#945;',    'iquest' 	=> '&#191;',    'raquo' 	=> '&#187;',
			'amp' 		=> '&#38;',     'isin'  	=> '&#8712;',   'rarr' 		=> '&#8594;',
			'and' 		=> '&#8743;',   'iuml' 		=> '&#239;',    'rArr' 		=> '&#8658;',
			'ang' 		=> '&#8736;',   'Iuml'   	=> '&#207;',    'rceil' 	=> '&#8969;',
			'aring' 	=> '&#229;',    'Kappa' 	=> '&#922;',    'rdquo' 	=> '&#8221;',
			'Aring'  	=> '&#197;',    'kappa' 	=> '&#954;',    'real' 		=> '&#8476;',
			'asymp' 	=> '&#8776;',   'Lambda' 	=> '&#923;',    'reg' 		=> '&#174;',
			'Atilde' 	=> '&#195;',    'lambda' 	=> '&#955;',    'rfloor' 	=> '&#8971;',
			'atilde' 	=> '&#227;',    'lang' 		=> '&#9001;',   'Rho' 		=> '&#929;',
			'auml' 		=> '&#228;',    'laquo' 	=> '&#171;',    'rho' 		=> '&#961;',
			'Auml'   	=> '&#196;',    'larr' 		=> '&#8592;',   'rlm' 		=> '&#8207;',
			'bdquo' 	=> '&#8222;',   'lArr' 		=> '&#8656;',   'rsaquo' 	=> '&#8250;',
			'beta' 		=> '&#946;',    'lceil' 	=> '&#8968;',   'rsquo' 	=> '&#8217;',
			'Beta'  	=> '&#914;',    'ldquo' 	=> '&#8220;',   'sbquo' 	=> '&#8218;',
			'brvbar' 	=> '&#166;',    'le' 		=> '&#8804;',   'Scaron' 	=> '&#352;',
			'bull' 		=> '&#8226;',   'lfloor' 	=> '&#8970;',   'scaron' 	=> '&#353;',
			'cap' 		=> '&#8745;',   'lowast' 	=> '&#8727;',   'sdot' 		=> '&#8901;',
			'Ccedil' 	=> '&#199;',    'loz' 		=> '&#9674;',   'sect' 		=> '&#167;',
			'ccedil' 	=> '&#231;',    'lrm' 		=> '&#8206;',   'shy' 		=> '&#173;',
			'cedil' 	=> '&#184;',    'lsaquo' 	=> '&#8249;',   'Sigma' 	=> '&#931;',
			'cent' 		=> '&#162;',    'lsquo' 	=> '&#8216;',   'sigma' 	=> '&#963;',
			'Chi' 		=> '&#935;',    'lt' 		=> '&#60;',     'sigmaf' 	=> '&#962;',
			'chi' 		=> '&#967;',    'macr' 		=> '&#175;',    'sim' 		=> '&#8764;',
			'circ' 		=> '&#710;',    'mdash' 	=> '&#8212;',   'slash'		=> '&#47;',
			'clubs' 	=> '&#9827;',   'micro' 	=> '&#181;',    'spades' 	=> '&#9824;',
			'cong' 		=> '&#8773;',   'middot' 	=> '&#183;',    'sub' 		=> '&#8834;',
			'copy' 		=> '&#169;',    'minus' 	=> '&#8722;',   'sube' 		=> '&#8838;',
			'crarr'		=> '&#8629;',   'Mu' 		=> '&#924;',    'sum'		=> '&#8721;',
			'cup' 		=> '&#8746;',   'mu' 		=> '&#956;',    'sup' 		=> '&#8835;',
			'curren' 	=> '&#164;',    'nabla' 	=> '&#8711;',   'sup1' 		=> '&#185;',
			'dagger' 	=> '&#8224;',   'nbsp' 		=> '&#160;',    'sup2' 		=> '&#178;',
			'Dagger' 	=> '&#8225;',   'ndash' 	=> '&#8211;',   'sup3' 		=> '&#179;',
			'darr' 		=> '&#8595;',   'ne' 		=> '&#8800;',   'supe' 		=> '&#8839;',
			'dArr' 		=> '&#8659;',   'ni' 		=> '&#8715;',   'szlig' 	=> '&#223;',
			'deg' 		=> '&#176;',    'not' 		=> '&#172;',    'Tau' 		=> '&#932;',
			'Delta' 	=> '&#916;',    'notin' 	=> '&#8713;',   'tau' 		=> '&#964;',
			'delta' 	=> '&#948;',    'nsub' 		=> '&#8836;',   'there4' 	=> '&#8756;',
			'diams' 	=> '&#9830;',   'Ntilde' 	=> '&#209;',    'Theta' 	=> '&#920;',
			'divide' 	=> '&#247;',    'ntilde' 	=> '&#241;',    'theta' 	=> '&#952;',
			'Eacute' 	=> '&#201;',    'Nu' 		=> '&#925;',    'thetasym' 	=> '&#977;',
			'eacute' 	=> '&#233;',    'nu' 		=> '&#957;',    'thinsp' 	=> '&#8201;',
			'ecirc' 	=> '&#234;',    'Oacute' 	=> '&#211;',    'THORN' 	=> '&#222;',
			'Ecirc'  	=> '&#202;',    'oacute' 	=> '&#243;',    'thorn' 	=> '&#254;',
			'Egrave' 	=> '&#200;',    'Ocirc'  	=> '&#212;',    'tilde' 	=> '&#732;',
			'egrave' 	=> '&#232;',    'ocirc'  	=> '&#244;',    'times'  	=> '&#215;',
			'empty' 	=> '&#8709;',   'oelig'		=> '&#339;',    'trade' 	=> '&#8482;',
			'emsp' 		=> '&#8195;',   'OElig' 	=> '&#338;',    'Uacute' 	=> '&#218;',
			'ensp' 		=> '&#8194;',   'Ograve' 	=> '&#210;',    'uacute' 	=> '&#250;',
			'Epsilon' 	=> '&#917;',    'ograve' 	=> '&#242;',    'uarr' 		=> '&#8593;',
			'epsilon' 	=> '&#949;',    'oline' 	=> '&#8254;',   'uArr' 		=> '&#8657;',
			'equiv' 	=> '&#8801;',   'Omega' 	=> '&#937;',    'Ucirc' 	=> '&#219;',
			'Eta' 		=> '&#919;',    'omega' 	=> '&#969;',    'ucirc' 	=> '&#251;',
			'eta' 		=> '&#951;',    'Omicron'	=> '&#927;',    'Ugrave' 	=> '&#217;',
			'ETH'    	=> '&#208;',    'omicron' 	=> '&#959;',    'ugrave' 	=> '&#249;',
			'eth'    	=> '&#240;',    'oplus' 	=> '&#8853;',   'uml' 		=> '&#168;',
			'euml' 		=> '&#235;',    'or' 		=> '&#8744;',   'upsih' 	=> '&#978;',
			'Euml'   	=> '&#203;',    'ordf' 		=> '&#170;',    'Upsilon' 	=> '&#933;',
			'euro'  	=> '&#8364;',   'ordm' 		=> '&#186;',    'upsilon' 	=> '&#965;',
			'exist' 	=> '&#8707;',   'Oslash' 	=> '&#216;',    'Uuml' 		=> '&#220;',
			'fnof'  	=> '&#402;',    'oslash' 	=> '&#248;',    'uuml' 		=> '&#252;',
			'forall' 	=> '&#8704;',   'Otilde' 	=> '&#213;',    'weierp' 	=> '&#8472;',
			'frac12' 	=> '&#189;',    'otilde' 	=> '&#245;',    'Xi' 		=> '&#926;',
			'frac14' 	=> '&#188;',    'otimes' 	=> '&#8855;',   'xi' 		=> '&#958;',
			'frac34' 	=> '&#190;',    'ouml' 	 	=> '&#246;',    'Yacute' 	=> '&#221;',
			'frasl' 	=> '&#8260;',   'Ouml'   	=> '&#214;',    'yacute' 	=> '&#253;',
			'Gamma' 	=> '&#915;',    'para' 		=> '&#182;',    'yen' 		=> '&#165;',
			'gamma' 	=> '&#947;',    'part'  	=> '&#8706;',   'yuml' 		=> '&#255;',
			'ge' 		=> '&#8805;',   'permil' 	=> '&#8240;',   'Yuml' 		=> '&#376;',
			'gt' 		=> '&#62;',     'perp' 		=> '&#8869;',   'Zeta' 		=> '&#918;',
			'harr' 		=> '&#8596;',   'Phi' 		=> '&#934;',    'zeta' 		=> '&#950;',
			'hArr' 		=> '&#8660;',   'phi' 		=> '&#966;',    'zwj' 		=> '&#8205;',
			'hearts' 	=> '&#9829;',   'Pi' 		=> '&#928;',    'zwnj' 		=> '&#8204;',
			'hellip' 	=> '&#8230;',   'pi' 		=> '&#960;',
			'Iacute' 	=> '&#205;',    'piv' 		=> '&#982;',
		);

		if (isset($table[$matches[1]])) return $table[$matches[1]];
	  	// else
	  	return $destroy ? '' : $matches[0];
	}

	/** End chars decode */

    // --------------------------------------------------------------------

	/**
	 *	Returns the JavaScript for the Publish Form
	 *
	 *	@access		public
	 *	@return		string
	 */

    function tag_js ()
    {
		$this->file_view('publish_tab_block.js');
    }
    /**	END tag_js */
}

/* END CLASS Tag */