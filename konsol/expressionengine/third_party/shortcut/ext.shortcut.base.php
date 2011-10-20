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
 * Shortcut - Extension
 *
 * @package 	Solspace:Shortcut
 * @author		Solspace DevTeam
 * @filesource 	./system/modules/shortcut/ext.shortcut.php
 */
 
if (APP_VER < 2.0)
{
	require_once PATH.'bridge/lib/addon_builder/extension_builder.php';
}
else
{
	require_once PATH_THIRD . 'bridge/lib/addon_builder/extension_builder.php';
}

class Shortcut_extension_base extends Extension_builder_bridge {

	var $settings		= array();
	
	var $name			= '';
	var $version		= '';
	var $description	= '';
	var $settings_exist	= 'n';
	var $docs_url		= '';
	
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 
	 * @access	public
	 * @return	null
	 */
    
	function Shortcut_extension_base($settings = array())
    {	
    	parent::Extension_builder_bridge('shortcut');
    	
    	/** --------------------------------------------
        /**  Settings
        /** --------------------------------------------*/
    	
    	$this->settings = $settings;
        
        /** --------------------------------------------
        /**  Set Required Extension Variables
        /** --------------------------------------------*/
        
        if ( is_object( ee()->lang ) )
        {
        	// ee()->lang->load('shortcut');
        
        	$this->name			= ee()->lang->line('shortcut_module_name');
        	$this->description	= ee()->lang->line('shortcut_module_description');
        }
        
        $this->docs_url		= SHORTCUT_DOCS_URL;
        $this->version		= SHORTCUT_VERSION;
	}
	/* END Shortcut_extension_base() */	
	
	// --------------------------------------------------------------------

	/**
	 * Adds the JS code for making the ShortenURL link work
	 *
	 * @access	public
	 * @return	null
	 */
	 
	function add_extra_header($out)
	{
		global $EXT;
	
		if ( $EXT->last_call !== FALSE  )
		{
			$out = $EXT->last_call;
		}
		
		$url = BASE.'&C=shortcut_url&M=ajax';
		$is_text = ee()->lang->line('is');
		$enter_text = ee()->lang->line('enter_url');
		$shortened_text = ee()->lang->line('shortened_url_for');
		
		$script =	"
<script type=\"text/javascript\">			
function buildShortyURL()
{	
	var longURL = prompt(\"{$enter_text}\" + ':', '');
	
	if ( ! longURL || longURL == null || longURL == '')
	{
		return; 
	}
	
	// Do AJAX
	
	jQuery.ajax(
	{
		type: 'POST',
		datatype: 'text',
		url: '{$url}',
		data: 'XID={XID_HASH}&full_url='+longURL,
		success: function(msg)
				 {
				 	prompt('{$shortened_text}' + '\\n' + longURL + ' {$is_text}: ', msg);
				 },
		error: function(XMLHttpRequest, textStatus, errorThrown)
			   {
					alert('Error! ' + errorThrown + ' ' + textStatus);
			   }
	});
}
</script>";
		
		// echo $out;

		$out	= str_replace( '</body>', $script . '</body>', $out);
		
		return $out;
		
	}
	/* END add_extra_header() */	
	
	// --------------------------------------------------------------------

	/**
	 * AJAX Request from ShortenURL Quick Link
	 *
	 *
	 * @access	public
	 * @return	null
	 */
	 
	function ajax_request($obj)
	{		
		/** --------------------------------------------
        /**  Valid Request for new Shortened URL?
        /** --------------------------------------------*/
        
		if ( REQ != 'CP' OR 
			! isset($_GET['C'], $_GET['M']) OR
			$_GET['C'] != 'shortcut_url' OR
			$_GET['M'] != 'ajax')
		{
			return;
		}
		
		if ( ! isset($_POST['full_url']) OR $_POST['full_url'] == '' OR
			strlen($_POST['full_url']) < 5 OR ! preg_match('/[a-z].[a-z]/', $_POST['full_url']))
		{
			@header("HTTP/1.0 200 OK");
			@header("HTTP/1.1 200 OK");
			exit('Invalid URL');
		}
		
		$SESS = $obj;
		
		if (strncasecmp($_POST['full_url'], 'http', 4) != 0)
		{
			$_POST['full_url'] = 'http://'.$_POST['full_url'];
		}
		
		/** --------------------------------------------
        /**  Is there a shortcut base URL that overrides?
        /** --------------------------------------------*/
        
        $shortcut_base	= $this->data->get_preference( ee()->config->item('site_id'), 'shortcut_base' );
		
		/** --------------------------------------------
        /**  Check for Duplicate
        /** --------------------------------------------*/
        
        if ( ( $shortcut = $this->data->get_shortcut( $_POST['full_url'] ) ) !== FALSE )
        {
        	@header("HTTP/1.0 200 OK");
			@header("HTTP/1.1 200 OK");
			
			if ( $shortcut_base == '' )
			{
				exit( ee()->functions->create_url( $shortcut ));
			}
			else
			{
				exit( $shortcut_base . $shortcut );
			}
        }
        
        /** --------------------------------------------
        /**  Get next shortcut id
        /** --------------------------------------------*/
        
        $shortcut	= $this->data->get_next_shortcut();
        														
        $id			= $this->data->cached['get_next_shortcut_id'];
        
        /** --------------------------------------------
        /**  Set shortcut
        /** --------------------------------------------*/
        
        $this->data->set_shortcut( $id, $shortcut, '', $_POST['full_url'] );
        
        @header("HTTP/1.0 200 OK");
		@header("HTTP/1.1 200 OK");
			
		if ( $shortcut_base == '' )
		{
			exit( ee()->functions->create_url( $shortcut ));
		}
		else
		{
			exit( $shortcut_base . $shortcut );
		}
	}
	/* END ajax_request() */
	
	// --------------------------------------------------------------------

	/**
	 * Creates a Quick Link for a JS Shoren URL Creator
	 *
	 *
	 * @access	public
	 * @return	null
	 */
	 
	function create_js_link($out)
	{
		global $EXT;
	
		if ( $EXT->last_call !== FALSE  )
		{
			$out = $EXT->last_call;
		}
		
		$out = preg_replace("@<a\s+[^>]+>ShortcutURL</a>@", '<a href="#" onclick="buildShortyURL(); return false;">ShortcutURL</a>', $out);
		
		return $out;
		
	}
	/* END create_js_link() */
	
	// --------------------------------------------------------------------

	/**
	 * If user side request, it looks for the Segment Prefix and Attemtps to do a Redirect
	 *
	 *
	 * @access	public
	 * @return	null
	 */
	 
	function redirect_short_url()
	{
		if (
				REQ == 'PAGE' AND 
				isset( ee()->uri->segments[1] ) AND 
				strncasecmp(
					ee()->uri->segments[1],
					$this->data->get_preference(
						ee()->config->item( 'site_id' ),
						'shortcut_prefix'
					),
					strlen(
						$this->data->get_preference(
							ee()->config->item( 'site_id' ),
							'shortcut_prefix'
						)
					)
				) == 0
		   )
		{
		
			if ( ( $url = $this->data->get_url( ee()->config->item('site_id'), ee()->uri->segments[1] ) ) !== FALSE )
			{
				if ( ( $shortcut_id = $this->data->get_shortcut_id( ee()->config->item('site_id'), ee()->uri->segments[1] ) ) !== FALSE )
				{
					$this->data->set_hits( $shortcut_id );
				}
				
				header( "Location: ".$url );
				exit;
			}
		}
	}
	/* END redirect_short_url() */	
		
	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension enabled, they have to install the module.
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
	
	// --------------------------------------------------------------------
	

	/**
	 * Error Page
	 *
	 * @access	public
	 * @param	string	$error	Error message to display
	 * @return	null
	 */
	
	function error_page($error = '')
	{	
		$this->cached_vars['error_message'] = $error;
		
		$this->cached_vars['page_title'] = $this->EE->lang->line('error');
		
		/** -------------------------------------
		/**  Output
		/** -------------------------------------*/
		
		$this->ee_cp_view('error_page.html');
	}
	/* END error_page() */
	
}
/* END Class Shortcut_extension */

?>