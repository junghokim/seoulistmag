<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tagger Module Control Panel Class
 *
 * @package			DevDemon_Tagger
 * @version			2.1.2
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2010 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com
 * @see				http://expressionengine.com/user_guide/development/module_tutorial.html#control_panel_file
 */
class Tagger_mcp
{
	/**
	 * Views Data
	 * @access private
	 */
	private $vData = array();

	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		// Creat EE Instance
		$this->EE =& get_instance();

		// Load Models & Libraries & Helpers
		$this->EE->load->library('tagger_helper');
		$this->EE->load->model('tagger_model');

		// Some Globals
		$this->base = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tagger';
		$this->base_short = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=tagger';
		$this->site_id = $this->EE->config->item('site_id');

		// Global Views Data
		$this->vData['base_url'] = $this->base;
		$this->vData['base_url_short'] = $this->base_short;
		$this->vData['method'] = $this->EE->input->get('method');

		if (! defined('DEVDEMON_THEME_URL')) define('DEVDEMON_THEME_URL', $this->EE->config->item('theme_folder_url') . 'third_party/');

		$this->mcp_globals();

		// Add Right Top Menu
		$this->EE->cp->set_right_nav(array(
			'tagger:docs' 			=> $this->EE->cp->masked_url('http://www.devdemon.com/tagger/docs/'),
		));

		// Debug
		//$this->EE->db->save_queries = TRUE;
		//$this->EE->output->enable_profiler(TRUE);
	}

	// ********************************************************************************* //

	/**
	 * MCP PAGE: Index
	 *
	 * @access public
	 * @return string
	 */
	public function index()
	{
		// Page Title & BreadCumbs
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('tagger'));

		$this->EE->cp->add_js_script(array('plugin' => 'dataTables'));

		// Grab all tags
		$this->vData['tags'] = $this->EE->tagger_model->get_tags();
		$this->vData['total_tags'] = count($this->vData['tags']);

		// Grab Groups
		$this->vData['groups'] = $this->EE->tagger_model->get_groups();
		$this->vData['groups_entries'] = $this->EE->tagger_model->get_groups_entries();


		$this->EE->load->library('table');
		$this->EE->jquery->tablesorter('.mainTable', '{widgets: ["zebra"]}');
		$this->EE->javascript->compile();

		return $this->EE->load->view('mcp_index', $this->vData, TRUE);
	}

	// ********************************************************************************* //

	/**
	 * MCP PAGE: Tag Groups
	 * @access public
	 * @return string
	 */
	public function groups()
	{
		// Page Title
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('tagger:groups'));


		$this->vData['groups'] = $this->EE->tagger_model->get_groups();
		$this->vData['total_groups'] = count($this->vData['groups']);


		return $this->EE->load->view('mcp_groups', $this->vData, TRUE);
	}

	// ********************************************************************************* //

	public function add_group()
	{
		// Page Title
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('tagger:create_group'));

		$this->vData['group_id'] = '';
		$this->vData['group_title'] = '';
		$this->vData['group_name'] = '';
		$this->vData['group_desc'] = '';

		// Are we editing?
		if ($this->EE->input->get('group_id') > 0)
		{
			// Grab the group
			$groups = $this->EE->tagger_model->get_groups($this->EE->input->get('group_id'));

			// Do we have any group?
			if (count($groups) == 1)
			{
				// Always grab the first result, just in case
				$group = reset($groups);

				$this->vData['group_id']	= $group->group_id;
				$this->vData['group_title']	= $group->group_title;
				$this->vData['group_name']	= $group->group_name;
				$this->vData['group_desc']	= $group->group_desc;
			}

		}

		return $this->EE->load->view('mcp_groups_add', $this->vData, TRUE);
	}

	// ********************************************************************************* //

	public function update_group()
	{
		//----------------------------------------
		// Create/Updating?
		//----------------------------------------
		if ($this->EE->input->get('delete') != 'yes')
		{
			$this->EE->db->set('group_title', $this->EE->input->post('group_title'));
			$this->EE->db->set('group_name', $this->EE->input->post('group_name'));
			$this->EE->db->set('group_desc', $this->EE->input->post('group_desc'));

			// Are we updating a group?
			if ($this->EE->input->post('group_id') >= 1)
			{
				$this->EE->db->where('group_id', $this->EE->input->post('group_id'));
				$this->EE->db->update('exp_tagger_groups');
			}
			else
			{
				$this->EE->db->insert('exp_tagger_groups');
			}

			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('tagger:updated_group'));
		}
		//----------------------------------------
		// Delete
		//----------------------------------------
		else
		{
			$group_id = $this->EE->input->get('group_id');

			// Delete from exp_tagger_groups
			$this->EE->db->where('group_id', $group_id);
			$this->EE->db->delete('exp_tagger_groups');

			//Delete from exp_tagger_groups_entries
			$this->EE->db->where('group_id', $group_id);
			$this->EE->db->delete('exp_tagger_groups_entries');

			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('tagger:deleted_group'));
		}




		$this->EE->functions->redirect($this->base . '&method=groups');
	}

	// ********************************************************************************* //

	public function edit_tag()
	{
		// Page Title
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('tagger:edit_tag'));


		// Grab the tag
		$tag = $this->EE->tagger_model->get_tags(1, $this->EE->input->get('tag_id'));
		$this->vData['tag_name'] = $tag->tag_name;
		$this->vData['tag_id'] = $tag->tag_id;


		return $this->EE->load->view('mcp_edit_tag', $this->vData, TRUE);
	}

	// ********************************************************************************* //

	public function update_tag()
	{
		$tag_id = $this->EE->input->get_post('tag_id');

		//----------------------------------------
		// Updating?
		//----------------------------------------
		if ($this->EE->input->get('delete') != 'yes')
		{
			$this->EE->db->set('tag_name', $this->EE->input->post('tag_name'));

			// Are we updating a group?
			$this->EE->db->where('tag_id', $tag_id)->update('exp_tagger');


			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('tagger:updated_tag'));
		}
		//----------------------------------------
		// Delete
		//----------------------------------------
		else
		{
			// Delete from exp_tagger
			$this->EE->db->where('tag_id', $tag_id)->delete('exp_tagger');

			//Delete from exp_tagger_links
			$this->EE->db->where('tag_id', $tag_id)->delete('exp_tagger_links');

			//Delete from exp_tagger_groups_entries
			$this->EE->db->where('tag_id', $tag_id)->delete('exp_tagger_groups_entries');

			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('tagger:deleted_tag'));
		}

		$this->EE->functions->redirect($this->base);
	}
	// ********************************************************************************* //

	private function mcp_globals()
	{
		$this->EE->cp->set_breadcrumb($this->base, $this->EE->lang->line('tagger_module_name'));

		$this->EE->tagger_helper->mcp_meta_parser('gjs', '', 'Tagger');
		$this->EE->tagger_helper->mcp_meta_parser('css', DEVDEMON_THEME_URL . 'tagger/tagger_mcp.css', 'tagger-mcp');
		$this->EE->tagger_helper->mcp_meta_parser('js', DEVDEMON_THEME_URL . 'tagger/jquery.multiselect.js', 'jquery.multiselect', 'jquery');
		$this->EE->tagger_helper->mcp_meta_parser('js', DEVDEMON_THEME_URL . 'tagger/tagger_mcp.js', 'tagger-mcp');
	}


} // END CLASS

/* End of file mcp.tagger.php */
/* Location: ./system/expressionengine/third_party/tagger/mcp.tagger.php */