<?php if (!defined('BASEPATH')) die('No direct script access allowed');

/**
 * Tagger Module FieldType
 *
 * @package			DevDemon_Tagger
 * @version			2.1.5
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2011 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com
 * @see				http://expressionengine.com/user_guide/development/fieldtypes.html
 */
class Tagger_ft extends EE_Fieldtype
{
	/**
	 * Field info (Required)
	 *
	 * @var array
	 * @access public
	 */
	var $info = array(
		'name' 		=> 'Tagger',
		'version'	=> '2.1.5'
	);


	/**
	 * Constructor
	 *
	 * @access public
	 *
	 * Calls the parent constructor
	 */
	public function __construct()
	{
		parent::EE_Fieldtype();

		$this->EE->load->add_package_path(PATH_THIRD . 'tagger/');

		$this->EE->lang->loadfile('tagger');
		$this->EE->load->library('tagger_helper');

		$this->site_id = $this->EE->config->item('site_id');
		if (! defined('TAGGER_THEME_URL')) define('TAGGER_THEME_URL', $this->EE->config->item('theme_folder_url') . 'third_party/tagger/');
	}

	// ********************************************************************************* //

	/**
	 * Display the field in the publish form
	 *
	 * @access public
	 * @param $data String Contains the current field data. Blank for new entries.
	 * @return String The custom field HTML
	 *
	 * $this->settings =
	 *  Array
	 *  (
	 *      [field_id] => nsm_better_meta__nsm_better_meta
	 *      [field_label] => NSM Better Meta
	 *      [field_required] => n
	 *      [field_data] =>
	 *      [field_list_items] =>
	 *      [field_fmt] =>
	 *      [field_instructions] =>
	 *      [field_show_fmt] => n
	 *      [field_pre_populate] => n
	 *      [field_text_direction] => ltr
	 *      [field_type] => nsm_better_meta
	 *      [field_name] => nsm_better_meta__nsm_better_meta
	 *      [field_channel_id] =>
	 *  )
	 */
	public function display_field($data)
	{

		$vData = array();
		$vData['dupe_field'] = FALSE;
		$vData['field_name'] = $this->field_name;
		$vData['field_id'] = $this->field_id;

		// We only want 1 tagger field (for now)
		if (isset( $this->EE->session->cache['Tagger']['Dupe_Field'] ) == FALSE)
		{
			$this->EE->session->cache['Tagger']['Dupe_Field'] = TRUE;
		}
		else
		{
			// It's a dupe field, show a message
			$vData['dupe_field'] = TRUE;
			return $this->EE->load->view('pbf_field', $vData, TRUE);
		}

		// Post DATA?
		if (isset($_POST[$this->field_name])) {
			$data = $_POST[$this->field_name];
		}

		// Add Global JS & CSS & JS Scripts
		$this->EE->tagger_helper->mcp_meta_parser('gjs', '', 'Tagger');
		$this->EE->tagger_helper->mcp_meta_parser('css', TAGGER_THEME_URL . 'tagger_pbf.css', 'tagger-pbf');
		$this->EE->tagger_helper->mcp_meta_parser('js', TAGGER_THEME_URL . 'jquery.autocomplete.js', 'jquery.autocomplete', 'jquery');
		$this->EE->tagger_helper->mcp_meta_parser('js', TAGGER_THEME_URL . 'tagger_pbf.js', 'tagger-pbf');
		$this->EE->cp->add_js_script(array('ui' => array('sortable', 'tabs')));


		// Some Globals
		$vData['assigned_tags'] = array();
		$vData['most_used_tags'] = array();
		$vData['channel_id'] = ($this->EE->input->get_post('channel_id') != FALSE) ? $this->EE->input->get_post('channel_id') : 0;

		// Grab most used tags
		$this->EE->db->select('tag_name');
		$this->EE->db->from('exp_tagger');
		$this->EE->db->where('total_entries >', 0);
		$this->EE->db->order_by('total_entries', 'desc');
		$this->EE->db->limit(25);
		$query = $this->EE->db->get();

		foreach ($query->result() as $row)
		{
			$vData['most_used_tags'][] = $row->tag_name;
		}

		// Sometimes you forget to fill in field
		// and you will send back to the form
		// We need to fil lthe values in again.. *Sigh* (anyone heard about AJAX!)
		if (is_array($data) == TRUE && isset($data['tags']) == TRUE)
		{
			foreach ($data['tags'] as $tag)
			{
				$vData['assigned_tags'][] = $tag;
			}

			return $this->EE->load->view('pbf_field', $vData, TRUE);
		}

		// Grab assigned tags
		if ($this->EE->input->get_post('entry_id') != FALSE)
		{
			$this->EE->db->select('t.tag_name');
			$this->EE->db->from('exp_tagger_links tp');
			$this->EE->db->join('exp_tagger t', 'tp.tag_id = t.tag_id', 'left');
			$this->EE->db->where('tp.item_id', $this->EE->input->get_post('entry_id'));
			$this->EE->db->where('tp.site_id', $this->site_id);
			$this->EE->db->where('tp.type', 1);
			$this->EE->db->order_by('tp.order');
			$query = $this->EE->db->get();

			foreach ($query->result() as $row)
			{
				$vData['assigned_tags'][] = $row->tag_name;
			}
		}


		return $this->EE->load->view('pbf_field', $vData, TRUE);
	}

	// ********************************************************************************* //

	/**
	 * Validates the field input
	 *
	 * @param $data Contains the submitted field data.
	 * @return mixed Must return TRUE or an error message
	 */
	public function validate($data)
	{
		// Is this a required field?
		if ($this->settings['field_required'] == 'y')
		{
			if (isset($data['tags']) == FALSE OR empty($data['tags']) == TRUE)
			{
				return $this->EE->lang->line('tagger:required_field');
			}
		}

		return TRUE;
	}

	// ********************************************************************************* //

	/**
	 * Preps the data for saving
	 *
	 * @param $data Contains the submitted field data.
	 * @return string Data to be saved
	 */
	public function save($data)
	{
		$this->EE->session->cache['Tagger']['FieldData'] = $data;

		if (isset($data['tags']) == FALSE OR empty($data['tags']) == TRUE)
		{
			return '';
		}
		else
		{
			return 'Tagger';
		}
	}

	// ********************************************************************************* //

	/**
	 * Handles any custom logic after an entry is saved.
	 * Called after an entry is added or updated.
	 * Available data is identical to save, but the settings array includes an entry_id.
	 *
	 * @param $data Contains the submitted field data. (Returned by save())
	 * @return void
	 */
	public function post_save($data)
	{
		$data = $this->EE->session->cache['Tagger']['FieldData'];
		$entry_id = $this->settings['entry_id'];
		$this->EE->load->library('tagger_helper');

		// Do we need to skip?
		if ($data['skip'] == 'y') return;

		// Grab all existing tag links
		$this->EE->db->select('tag_id, rel_id');
		$this->EE->db->from('exp_tagger_links');
		$this->EE->db->where('item_id', $entry_id);
		$query = $this->EE->db->get();

		// Our array empty?
		if (isset($data['tags']) == FALSE OR empty($data['tags']) == TRUE)
		{
			foreach ($query->result() as $row)
			{
				// Delete tag association
				$this->EE->db->where('rel_id', $row->rel_id);
				$this->EE->db->delete('exp_tagger_links');

				// Update total_items
				$this->EE->db->set('total_entries', '(`total_entries` - 1)', FALSE);
				$this->EE->db->where('tag_id', $row->tag_id);
				$this->EE->db->where('site_id', $this->site_id);
				$this->EE->db->update('exp_tagger');
			}

			return;
		}

		// Which ones do we already have?
		$dbtags = array();

		foreach ($query->result() as $row)
		{
			$dbtags[ $row->rel_id ] = $row->tag_id;
		}

		// Loop over all assigned tags
		foreach ($data['tags'] as $i => $tag)
		{
			// Format the tag
			$tag = $this->EE->tagger_helper->format_tag($tag);

			// No "empty" tags
			if ($tag == FALSE) continue;

			// Does it already exist?
			$this->EE->db->select('tag_id');
			$this->EE->db->from('exp_tagger');
			$this->EE->db->where('tag_name', $tag);
			$this->EE->db->where('site_id', $this->site_id);
			$this->EE->db->limit(1);
			$q2 = $this->EE->db->get();

			if ($q2->num_rows() == 0) $tag_id = $this->EE->tagger_helper->create_tag($tag);
			else $tag_id = $q2->row('tag_id');

			// Is it already assigned (to this entry)
			if (in_array($tag_id, $dbtags) == FALSE)
			{
				// Data array for insert
				$data =	array(	'item_id'	=>	$entry_id,
								'tag_id'	=>	$tag_id,
								'site_id'	=>	$this->site_id,
								'author_id'	=>	$this->EE->session->userdata['member_id'],
								'type'		=>	1,
								'`order`'	=>	$i + 1
						);

				// Insert
				$this->EE->db->insert('exp_tagger_links', $data);

				// Update total_items
				$this->EE->db->set('total_entries', '(`total_entries` + 1)', FALSE);
				$this->EE->db->where('tag_id', $tag_id);
				$this->EE->db->where('site_id', $this->site_id);
				$this->EE->db->update('exp_tagger');
			}
			else
			{
				// Get Rel_ID
				$rel_id = array_search($tag_id, $dbtags);

				// Update
				$this->EE->db->set('`order`', $i + 1);
				$this->EE->db->where('rel_id', $rel_id);
				$this->EE->db->update('exp_tagger_links');

				// We need to unset the "dupe" tag
				unset($dbtags[$rel_id]);
			}
		}

		// Remove old ones
		foreach ($dbtags as $rel_id => $tag_id)
		{
			// Delete tag association
			$this->EE->db->where('rel_id', $rel_id);
			$this->EE->db->delete('exp_tagger_links');

			// Update total_items
			$this->EE->db->set('total_entries', '(`total_entries` - 1)', FALSE);
			$this->EE->db->where('tag_id', $tag_id);
			$this->EE->db->where('site_id', $this->site_id);
			$this->EE->db->update('exp_tagger');
		}

		return;
	}

	// ********************************************************************************* //

	/**
	 * Handles any custom logic after an entry is deleted.
	 * Called after one or more entries are deleted.
	 *
	 * @param $ids array is an array containing the ids of the deleted entries.
	 * @return void
	 */
	public function delete($ids)
	{
		foreach ($ids as $item_id)
		{
			// Grab the Tag ID
			$this->EE->db->select('tag_id, rel_id');
			$this->EE->db->where('item_id', $item_id);
			$this->EE->db->where('type', 1);
			$this->EE->db->where('site_id', $this->site_id);
			$query = $this->EE->db->get('exp_tagger_links');

			foreach ($query->result() as $row)
			{
				// Delete tag association
				$this->EE->db->where('rel_id', $row->rel_id);
				$this->EE->db->delete('exp_tagger_links');

				// Update total_items
				$this->EE->db->set('total_entries', '(`total_entries` - 1)', FALSE);
				$this->EE->db->where('tag_id', $row->tag_id);
				$this->EE->db->where('site_id', $this->site_id);
				$this->EE->db->update('exp_tagger');
			}

			// Resources are not free
			$query->free_result();
		}
	}

	// ********************************************************************************* //

}

/* End of file ft.tagger.php */
/* Location: ./system/expressionengine/third_party/tagger/ft.tagger.php */