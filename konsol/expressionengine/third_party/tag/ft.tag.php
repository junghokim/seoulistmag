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
 * Solspace Tag Fieldtype Class
 *
 * Handles the adding of a specific Field Type to the Publish Tabs for Tag
 *
 * @package		Solspace:Tag
 * @subpackage	Fieldtypes
 * @author		Solspace Dev Team
 * @filesource 	./system/expressionengine/third_party/modules/tag/ft.tag.php
 */
 
class Tag_ft extends EE_Fieldtype
{
	var $info = array( 'name' => 'Tag Field Type', 'version' => '1.0');

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Tag_ft()
	{
		parent::EE_Fieldtype();
	}
	/* END constructor */

	// --------------------------------------------------------------------

	function display_field($data)
	{
		require_once PATH_THIRD . 'bridge/lib/addon_builder/module_builder.php';
		
		$MB = new Module_builder_bridge('tag');
	
		return $MB->view('publish_tab_block.html', array(), TRUE);
	}
	/* END display_field() */
}

// END Tag_ft class

/* End of file ft.tag.php */