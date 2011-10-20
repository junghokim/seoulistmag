<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tagger AJAX File
 *
 * @package			DevDemon_Tagger
 * @version			2.1.2
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2010 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com
 */
class Tagger_ajax
{

	public function __construct()
	{
		$this->EE =& get_instance();

		if ($this->EE->input->cookie('cp_last_site_id')) $this->site_id = $this->EE->input->cookie('cp_last_site_id');
		else if ($this->EE->input->get_post('site_id')) $this->site_id = $this->EE->input->get_post('site_id');
		else $this->site_id = $this->EE->config->item('site_id');
	}

	// ********************************************************************************* //

	public function tag_search()
	{
		$this->EE->db->select('tag_name');
		$this->EE->db->from('exp_tagger');
		$this->EE->db->like('tag_name', $this->EE->input->get('q'), 'both');
		$this->EE->db->order_by('tag_name');
		$query = $this->EE->db->get();


		foreach ($query->result() as $row)
		{
			echo "{$row->tag_name} \n";
		}

		exit();
	}

	// ********************************************************************************* //

	public function add_to_group()
	{
		$groups = $this->EE->input->post('groups');
		$tag_id = $this->EE->input->post('tag_id');

		// First check if groups is empty
		if (is_array($groups) == FALSE OR empty($groups) == TRUE OR $groups == FALSE)
		{
			echo "Groups is Empty \n";

			// Delete all groups from this Tag
			$this->EE->db->where('tag_id', $tag_id)->delete('tagger_groups_entries');
		}
		else
		{
			echo "Groups Found \n";

			// Delete all groups from this Tag
			$this->EE->db->where('tag_id', $tag_id)->delete('tagger_groups_entries');

			// Then add only what we need
			foreach ($groups as $group_id)
			{
				$this->EE->db->insert('tagger_groups_entries', array('tag_id' => $tag_id, 'group_id' => $group_id));
			}

		}

		echo 'DONE';
		exit();
	}

} // END CLASS

/* End of file tagger_ajax.php  */
/* Location: ./system/expressionengine/third_party/tagger/modules/libraries/tagger_ajax.php */