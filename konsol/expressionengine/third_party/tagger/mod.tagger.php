<?php if (!defined('BASEPATH')) die('No direct script access allowed');

/**
 * Tagger Module Tag Methods
 *
 * @package			DevDemon_Tagger
 * @version			2.1.2
 * @author			DevDemon <http://www.devdemon.com> - Lead Developer @ Parscale Media
 * @copyright 		Copyright (c) 2007-2011 Parscale Media <http://www.parscale.com>
 * @license 		http://www.devdemon.com/license/
 * @link			http://www.devdemon.com
 * @see				http://expressionengine.com/user_guide/development/module_tutorial.html#core_module_file
 */
class Tagger
{
	/**
	 * Allowed Order by keywords
	 *
	 * @access private
	 * @var array
	 */
	private $orderby_list		= array('tag_name', 'entry_date', 'hits', 'total_entries', 'order');

	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->site_id = $this->EE->config->item('site_id');
		$this->EE->load->library('tagger_helper');
	}

	// ********************************************************************************* //

	/**
	 * Display a list of tags
	 *
	 * @access public
	 * @return string
	 */
	public function tags()
	{
		// Variable prefix
		$prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';

		// We need an entry_id
		$entry_id = $this->EE->tagger_helper->get_entry_id_from_param();

		if (! $entry_id)
		{
			$this->EE->TMPL->log_item('TAGGER: Entry ID could not be resolved');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
		}

		// Some Params
		$orderby = (in_array($this->EE->TMPL->fetch_param('orderby'), $this->orderby_list)) ? 't.'.$this->EE->TMPL->fetch_param('orderby'): 'tl.order';
		$limit = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('limit')) != FALSE) ? $this->EE->TMPL->fetch_param('limit') : 30;
		$sort = ($this->EE->TMPL->fetch_param('sort') == 'desc' ) ? 'DESC': 'ASC';
		$backspace = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('backspace')) === TRUE) ? $this->EE->TMPL->fetch_param('backspace') : 0;

		// Lets start on the SQL then.
		$this->EE->db->select('t.*');
		$this->EE->db->from('exp_tagger t');
		$this->EE->db->join('exp_tagger_links tl', 'tl.tag_id = t.tag_id', 'left');
		$this->EE->db->where('tl.item_id', $entry_id);
		$this->EE->db->where('tl.type', 1);
		$this->EE->db->order_by($orderby, $sort);
		$this->EE->db->limit($limit);
		$query = $this->EE->db->get();

		// No tags?
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No tags found.');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
		}



		$out = '';
		$count = 0;
		$total = $query->num_rows();

		// Loop through the result
		foreach ($query->result() as $row)
		{
			$count++;
			$vars = array(	$prefix.'tag_name'		=> $row->tag_name,
							$prefix.'urlsafe_tagname' => $this->EE->tagger_helper->urlsafe_tag($row->tag_name),
							$prefix.'total_hits'	=> $row->hits,
							$prefix.'total_items'	=> $row->total_entries,
							$prefix.'count'			=> $count,
							$prefix.'total_tags'	=> $total,
						);

			$out .= $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $vars);
		}

		// Apply Backspace
		$out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

		// Resources are not free
		$query->free_result();

		return $out;
	}

	// ********************************************************************************* //

	/**
	 * Related Tags
	 *
	 * @access public
	 * @return string
	 */
	public function related()
	{
		// Variable prefix
		$this->prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';

		// We need an entry_id
		$entry_id = $this->EE->tagger_helper->get_entry_id_from_param();

		if (! $entry_id)
		{
			$this->EE->TMPL->log_item('TAGGER: Entry ID could not be resolved');
			return $this->EE->tagger_helper->custom_no_results_conditional($this->prefix.'no_tags', $this->EE->TMPL->tagdata);
		}

		// Lets start on the SQL then.
		$this->EE->db->select('t.*');
		$this->EE->db->from('exp_tagger t');
		$this->EE->db->join('exp_tagger_links tl', 'tl.tag_id = t.tag_id', 'left');
		$this->EE->db->where('tl.item_id', $entry_id);
		$this->EE->db->where('tl.type', 1);
		$query = $this->EE->db->get();

		// No tags?
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No tags found.');
			return $this->EE->tagger_helper->custom_no_results_conditional($this->prefix.'no_tags', $this->EE->TMPL->tagdata);
		}


		$tags = array();

		// Loop through the result
		foreach ($query->result() as $row)
		{
			$tags[] = $row->tag_name;
		}

		// Resources are not free
		$query->free_result();

		// For Re-Use Later
		$this->tags = $tags;
		$this->skip_entry = $entry_id;

		return $this->entries();
	}

	// ********************************************************************************* //

	/**
	 * Grouped Tags
	 *
	 * @access public
	 * @return string
	 */
	public function groups()
	{
		// Variable prefix
		$this->prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';

		$groups = NULL;

		// -----------------------------------------
		// Which Groups
		// -----------------------------------------
		if ($this->EE->TMPL->fetch_param('groups') != FALSE)
		{
			$group = $this->EE->TMPL->fetch_param('groups');

			// Multiple Groups?
			if (strpos($group, '|') !== FALSE)
			{
				$group = explode('|', $group);
				$groups = array();

				foreach ($group as $name)
				{
					$groups[] = $name;
				}
			}
			else
			{
				$groups = $this->EE->TMPL->fetch_param('groups');
			}
		}


		// Grab group ids
		$this->EE->db->select('group_id, group_name, group_title, group_desc');
		$this->EE->db->from('exp_tagger_groups');
		if (is_array($groups) == TRUE) $this->EE->db->where_in('group_name', $groups);
		else if (is_string($groups) == TRUE) $this->EE->db->where('group_name', $groups);

		// -----------------------------------------
		// Order By & Sort
		// -----------------------------------------
		$sort = 'asc';
		if ($this->EE->TMPL->fetch_param('sort') == 'desc') $sort = 'desc';

		switch ($this->EE->TMPL->fetch_param('orderby'))
		{
			case 'group_title' :
				$this->EE->db->order_by('group_title', $sort);
			default :
				$this->EE->db->order_by('group_title', $sort);
		}

		$query = $this->EE->db->get();

		// No Groups?
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No Groups Could be Found');
			return $this->EE->tagger_helper->custom_no_results_conditional($this->prefix.'no_groups', $this->EE->TMPL->tagdata);
		}

		// Harvest them
		$groups = array();
		$group_ids = array();
		foreach ($query->result() as $row)
		{
			$groups[$row->group_id] = $row;
			$group_ids[] = $row->group_id;
		}

		// Resources are not free
		$query->free_result();

		// Lets grab all tags within those groups
		$this->EE->db->select('tge.tag_id, tge.group_id, t.tag_name')
				->from('tagger_groups_entries tge')->join('exp_tagger t', 'tge.tag_id = t.tag_id', 'left')
				->where_in('tge.group_id', $group_ids);

		// -----------------------------------------
		// Order By & Sort
		// -----------------------------------------
		$sort = 'asc';
		if ($this->EE->TMPL->fetch_param('tag_sort') == 'desc') $sort = 'desc';

		switch ($this->EE->TMPL->fetch_param('tag_orderby'))
		{
			case 'tag_name' :
				$this->EE->db->order_by('t.tag_name', $sort);
			default :
				$this->EE->db->order_by('t.tag_name', $sort);
		}

		$query = $this->EE->db->get();


		// Add them to the groups array
		foreach ($query->result() as $row)
		{
			if (isset($groups[$row->group_id]->tags) == FALSE) $groups[$row->group_id]->tags = array($row);
			else $groups[$row->group_id]->tags[] = $row;
		}


		// Grab Relations Tagdata
		$tags_tagdata = $this->EE->tagger_helper->fetch_data_between_var_pairs($this->prefix.'tags', $this->EE->TMPL->tagdata);


		$out = '';

		// -----------------------------------------
		// Loop through all groups
		// -----------------------------------------
		foreach ($groups as $group_id => $group)
		{
			$gtemp = '';

			// Parse Group Info
			$vars = array();
			$vars[$this->prefix.'group_title']		= $group->group_title;
			$vars[$this->prefix.'group_name']		= $group->group_name;
			$vars[$this->prefix.'group_desc']		= $group->group_desc;

			// Replace all group info
			$gtemp = $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $vars);


			// Any relations from this group?
			if (isset($group->tags) == FALSE OR empty($group->tags) == TRUE)
			{
				$this->EE->TMPL->log_item('TAGGER: No Tags in Group: ' . $group->group_title);
				$temp = $this->EE->tagger_helper->custom_no_results_conditional($this->prefix.'no_tags', $tags_tagdata);
				$gtemp = $this->EE->tagger_helper->swap_var_pairs($this->prefix.'tags', $temp, $gtemp);
				$out .= $gtemp;
				continue;
			}

			$inner_final = '';

			// -----------------------------------------
			// Loop through all tags and parse
			// -----------------------------------------
			foreach ($group->tags as $tagcount => $tag)
			{
				$vars = array();
				$vars[$this->prefix.'tag_name']			= $tag->tag_name;
				$vars[$this->prefix.'urlsafe_tagname']	= $this->EE->tagger_helper->urlsafe_tag($tag->tag_name);


				$inner_final .= $this->EE->TMPL->parse_variables_row($tags_tagdata, $vars);
			}

			$gtemp = $this->EE->tagger_helper->swap_var_pairs($this->prefix.'tags', $inner_final, $gtemp);

			$out .= $gtemp;
		}


		// Apply Backspace
		//$out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;



		return $out;
	}

	// ********************************************************************************* //

	public function ungrouped_tags()
	{
		// Variable prefix
		$prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';

		// Some Params
		$orderby = (in_array($this->EE->TMPL->fetch_param('orderby'), $this->orderby_list)) ? 't.'.$this->EE->TMPL->fetch_param('orderby'): 't.tag_name';
		$limit = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('limit')) != FALSE) ? $this->EE->TMPL->fetch_param('limit') : 30;
		$sort = ($this->EE->TMPL->fetch_param('sort') == 'desc' ) ? 'DESC': 'ASC';
		$backspace = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('backspace')) === TRUE) ? $this->EE->TMPL->fetch_param('backspace') : 0;

		// Lets start on the SQL then.
		$this->EE->db->select('t.*');
		$this->EE->db->from('exp_tagger t');
		$this->EE->db->join('exp_tagger_groups_entries tge', 'tge.tag_id = t.tag_id', 'left outer');
		$this->EE->db->where('tge.rel_id is null');
		$this->EE->db->order_by($orderby, $sort);
		$this->EE->db->limit($limit);
		$query = $this->EE->db->get();

		// No tags?
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No tags found.');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
		}

		$out = '';
		$count = 0;
		$total = $query->num_rows();

		// Loop through the result
		foreach ($query->result() as $row)
		{
			$count++;
			$vars = array(	$prefix.'tag_name'		=> $row->tag_name,
							$prefix.'urlsafe_tagname' => $this->EE->tagger_helper->urlsafe_tag($row->tag_name),
							$prefix.'total_hits'	=> $row->hits,
							$prefix.'total_items'	=> $row->total_entries,
							$prefix.'count'			=> $count,
							$prefix.'total_tags'	=> $total,
						);

			$out .= $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $vars);
		}

		// Apply Backspace
		$out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

		// Resources are not free
		$query->free_result();

		return $out;
	}

	// ********************************************************************************* //

	/**
	 * Generate a Tag Cloud
	 *
	 * @return string - The Tag Cloud
	 */
	public function cloud()
	{
		// -----------------------------------------
		// Parameters
		// -----------------------------------------
		$params = array();
		$params['rankby']	= $this->EE->TMPL->fetch_param('rankby', 'entries');
		$params['max_size']	= ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('max_size')))  ? $this->EE->TMPL->fetch_param('max_size'): 32;
		$params['min_size']	= ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('max_size')))  ? $this->EE->TMPL->fetch_param('min_size'): 12;
		$params['orderby']	= (in_array($this->EE->TMPL->fetch_param('orderby'), array('tag_name', 'random', 'total_entries'))) ? $this->EE->TMPL->fetch_param('orderby'): 'tag_name';
		$params['sort']		= ($this->EE->TMPL->fetch_param('sort') == 'asc' ) ? 'ASC': 'DESC';
		$params['limit']	= ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('limit')) != FALSE) ? $this->EE->TMPL->fetch_param('limit') : 50;
		$params['backspace'] = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('backspace')) === TRUE) ? $this->EE->TMPL->fetch_param('backspace') : 0;
		$prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';


		// -----------------------------------------
		// Only From Certain Groups?
		// -----------------------------------------
		$groups = NULL;

		// Which Groups
		if ($this->EE->TMPL->fetch_param('groups') != FALSE)
		{
			$group = $this->EE->TMPL->fetch_param('groups');

			// Multiple Groups?
			if (strpos($group, '|') !== FALSE)
			{
				$group = explode('|', $group);
				$groups = array();

				foreach ($group as $name)
				{
					$groups[] = $name;
				}
			}
			else
			{
				$groups = array($this->EE->TMPL->fetch_param('groups'));
			}

			// Grab group id's
			$temp = $this->EE->db->select('group_id')->from('exp_tagger_groups')->where_in('group_name', $groups)->get();
			$groups = array();

			// No Group? Quit
			if ($temp->num_rows() == 0)
			{
				$this->EE->TMPL->log_item('TAGGER: No tags found.');
				return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
			}

			// Harvest Group Ids
			foreach ($temp->result() as $row)
			{
				$groups[] = $row->group_id;
			}

			// Grab tag id's
			$temp = $this->EE->db->distinct('tag_id')->from('tagger_groups_entries')->where_in('group_id', $groups)->limit($params['limit'])->get();

			// No Tags? Quit
			if ($temp->num_rows() == 0)
			{
				$this->EE->TMPL->log_item('TAGGER: No tags found.');
				return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
			}

			// Harvest the IDs
			$tags = array();

			foreach ($temp->result() as $row)
			{
				$tags[] = $row->tag_id;
			}

			$temp->free_result();
		}

		// -----------------------------------------
		// Rank By
		// -----------------------------------------
		switch ($params['rankby'])
		{
			case 'entries':
				$params['rankby'] = 'total_entries';
				break;
			case 'hits':
				$params['rankby'] = 'hits';
				break;
			default: $params['rankby'] = 'total_entries';
		}

		// -----------------------------------------
		// The SQL
		// -----------------------------------------
		$this->EE->db->select('tag_name, hits, total_entries');
		$this->EE->db->from('exp_tagger');
		$this->EE->db->where('total_entries >', 0);
		if (isset($tags)) $this->EE->db->where_in('tag_id', $tags);
		$this->EE->db->order_by($params['rankby'], 'DESC');
		$this->EE->db->limit($params['limit']);
		$query = $this->EE->db->get();

		// No tags?
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No tags found.');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
		}

		// Lets make a new array, actually 2
		$tags = array();
		//$tag_info = array();

		foreach ($query->result() as $row)
		{
			$tags[ $row->tag_name ] = $row->total_entries;
			//$tag_info = $row;
		}

		// largest and smallest array values
		$max_qty = max(array_values($tags));
		$min_qty = min(array_values($tags));

		// find the range of values
		$spread = $max_qty - $min_qty;
		if ($spread == 0) $spread = 1; // we don't want to divide by zero

		// set the font-size increment
		$step = ($params['max_size'] - $params['min_size']) / ($spread);

		// -----------------------------------------
		// Orderby
		// -----------------------------------------
		if ($params['orderby'] == 'random')
		{
			$tags = array_merge( array_flip(array_rand($tags, count($tags))), $tags);
		}
		elseif ($params['orderby'] == 'tag_name')
		{
			if ($params['sort'] == 'ASC') ksort($tags);
			else krsort($tags);;
		}
		elseif ($params['orderby'] == 'total_entries')
		{
			if ($params['sort'] == 'ASC') asort($tags);
			else arsort($tags);;
		}

		// -----------------------------------------
		// Loop through the results
		// -----------------------------------------

		$out = '';
		$count = 0;
		$total = $query->num_rows();

		// Loop through the results
		foreach ($tags as $tag => $value)
		{
			$count++;

			// calculate font-size
			// find the $value in excess of $min_qty
			// multiply by the font-size increment ($size)
			// and add the $params['min_size'] set above

			$vars = array(	$prefix.'tag_name'		=> $tag,
							$prefix.'urlsafe_tagname' => $this->EE->tagger_helper->urlsafe_tag($tag),
							$prefix.'size'	=> round($params['min_size'] + (($value - $min_qty) * $step)),
							$prefix.'total_items'	=> $value,
							$prefix.'count'			=> $count,
							$prefix.'total_tags'	=> $total,
						);

			$out .= $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $vars);
		}

		// Apply Backspace
		$out = ($params['backspace'] > 0) ? substr($out, 0, - $params['backspace']): $out;

		// Resources are not free
		$query->free_result();

		return $out;
	}

	// ********************************************************************************* //

	public function entries()
	{
		// Variable prefix
		$prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';

		$tags = $this->EE->TMPL->fetch_param('tag');

		// We need to keep in mind that related() also calls this function
		if ( isset($this->tags) == TRUE)
		{
			$tags = implode('|', $this->tags);

		}

		// -----------------------------------------
		// Which Tags
		// -----------------------------------------
		if ($tags == FALSE)
		{
			$this->EE->TMPL->log_item('TAGGER: No missing tag="" parameter');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_entries', $this->EE->TMPL->tagdata);
		}
		else
		{
			$temp = $tags;

			// Multiple Tags?
			if (strpos($temp, '|') !== FALSE)
			{
				$temp = explode('|', $temp);
				$tags = array();

				foreach ($temp as $name)
				{
					$tags[] = $this->EE->tagger_helper->urlsafe_tag($name, FALSE);
				}
			}
			else
			{
				$tags = $this->EE->tagger_helper->urlsafe_tag($temp, FALSE);
			}

		}

		$limit = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('limit')) != FALSE) ? $this->EE->TMPL->fetch_param('limit') : 30;
		$backspace = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('backspace')) === TRUE) ? $this->EE->TMPL->fetch_param('backspace') : 0;

		// -----------------------------------------
		// Custom Fields?
		// -----------------------------------------
		if ($this->EE->TMPL->fetch_param('custom_fields') != FALSE)
		{
			$fields	= explode( '|', $this->EE->TMPL->fetch_param('custom_fields') );
			$query = $this->EE->db->select('field_id, field_name')->from('exp_channel_fields')->where_in('field_name', $fields)->get();

			$fields	= array();

			foreach ($query->result() as $row)
			{
				$fields[ $row->field_id ] = $row->field_name;
			}
		}

		// Grab all entries with this tag
		$this->EE->db->select('tl.tag_id, tl.item_id, ct.title, ct.url_title, ct.entry_date, ct.channel_id');
		$this->EE->db->from('exp_tagger_links tl');
		$this->EE->db->join('exp_tagger t', 't.tag_id = tl.tag_id', 'left');
		$this->EE->db->join('exp_channel_titles ct', 'ct.entry_id = tl.item_id', 'left');

		// Any Fields?
		if ( isset($fields) == TRUE AND is_array($fields) == TRUE )
		{
			$this->EE->db->join('exp_channel_data cd', 'cd.entry_id = ct.entry_id', 'left');

			foreach ($fields as $key => $val)
			{
				$this->EE->db->select("cd.field_id_{$key}");
			}
		}

		if (is_array($tags) == TRUE) $this->EE->db->where_in('t.tag_name', $tags);
		else $this->EE->db->where('t.tag_name', $tags);

		// ENTRY STATUS
		if ($this->EE->TMPL->fetch_param('status') != FALSE)
		{
			$status = explode('|', $this->EE->TMPL->fetch_param('status'));
			$this->EE->db->where_in('ct.status', $status);
		}
		else
		{
			$this->EE->db->where('ct.status', 'open');
		}

		// Which entries should we skip?
		if (isset($this->skip_entry) == TRUE) $this->EE->db->where('tl.item_id !=', $this->skip_entry);

		// Limit?
		$this->EE->db->limit($limit);

		// Fetch!!
		$query = $this->EE->db->get();

		// Did we find anything
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No channel entries found');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_entries', $this->EE->TMPL->tagdata);
		}


		// Loop through the results
		$out = '';
		$count = 0;
		$total = $query->num_rows();
		$entries = $query->result();
		$query->free_result();

		// -----------------------------------------
		// Grab Channels
		// -----------------------------------------
		$channels = array();
		$query = $this->EE->db->select('*')->from('exp_channels')->where('site_id', $this->site_id)->get();

		foreach ($query->result() as $row)
		{
			$channels[$row->channel_id] = $row;
		}

		// -----------------------------------------
		// Loop through all entries
		// -----------------------------------------
		foreach ($entries as $row)
		{
			$count++;
			$vars = array(	$prefix.'channel_id'	=> $row->channel_id,
							$prefix.'entry_id'		=> $row->item_id,
							$prefix.'entry_title'	=> $row->title,
							$prefix.'entry_url_title' => $row->url_title,
							$prefix.'entry_date'	=> $row->entry_date,
							$prefix.'count'			=> $count,
							$prefix.'total_entries'	=> $total,
							$prefix.'tags'			=> (is_array($tags)) ? implode(',', $tags) : $tags,
						);


			// Channel Specific Data
			$vars[$prefix.'channel_name'] = $channels[$row->channel_id]->channel_name;
			$vars[$prefix.'channel_title'] = $channels[$row->channel_id]->channel_title;
			$vars[$prefix.'channel_url'] = $channels[$row->channel_id]->channel_url;
			$vars[$prefix.'channel_search_result_url'] = $channels[$row->channel_id]->search_results_url;
			$vars[$prefix.'channel_comment_url'] = $channels[$row->channel_id]->comment_url;

			// Any Custom Field?
			if ( isset($fields) == TRUE AND is_array($fields) == TRUE )
			{
				foreach ($fields as $field_id => $field_name)
				{
					$field_id = 'field_id_'.$field_id;

					$vars[$prefix.$field_name]  = $row->$field_id;
				}
			}

			$out .= $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $vars);
		}


		// Apply Backspace
		$out = ($backspace > 0) ? substr($out, 0, - $backspace): $out;

		// Update stats
		//$this->EE->db->set('hits', "(`hits` + 1)", FALSE)->where('tag_id', $query->row('tag_id'))->update('exp_tagger');

		// Resources are not free
		$query->free_result();
		unset($entries);

		return $out;
	}

	// ********************************************************************************* //

	/**
	 * Entries Quick
	 *
	 * An easy method of getting the channel entry_id's associated with a particular tag.
	 *
	 * @return string
	 */
	public function entries_quick()
	{
		// Variable prefix
		$prefix = $this->EE->TMPL->fetch_param('prefix', 'tagger') . ':';

		// We need a tag param
		if ($this->EE->TMPL->fetch_param('tag') == FALSE)
		{
			$this->EE->TMPL->log_item('TAGGER: No missing tag="" parameter');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_tags', $this->EE->TMPL->tagdata);
		}

		$tag = $this->EE->tagger_helper->urlsafe_tag($this->EE->TMPL->fetch_param('tag'), FALSE);
		$limit = ($this->EE->tagger_helper->is_natural_number($this->EE->TMPL->fetch_param('limit')) != FALSE) ? $this->EE->TMPL->fetch_param('limit') : 30;

		// Grab all entries with this tag
		$this->EE->db->select('tl.tag_id, tl.item_id');
		$this->EE->db->from('exp_tagger_links tl');
		$this->EE->db->join('exp_tagger t', 't.tag_id = tl.tag_id', 'left');
		$this->EE->db->where('t.tag_name', $tag);
		$this->EE->db->where('t.site_id', $this->site_id);
		$this->EE->db->where('tl.type', 1);
		$this->EE->db->limit($limit);
		$query = $this->EE->db->get();

		// Did we find anything
		if ($query->num_rows() == 0)
		{
			$this->EE->TMPL->log_item('TAGGER: No channel entries found');
			return $this->EE->tagger_helper->custom_no_results_conditional($prefix.'no_entries', $this->EE->TMPL->tagdata);
		}

		// Loop through the results
		$items = array();

		foreach ($query->result() as $row)
		{
			$items[] = $row->item_id;
		}

		$vars = array(	$prefix.'entry_ids'	=> implode('|', $items),
						$prefix.'tag_name'	=> $tag,
					);

		$this->EE->TMPL->tagdata = $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $vars);

		// Update stats
		$this->EE->db->set('hits', "(`hits` + 1)", FALSE);
		$this->EE->db->where('tag_id', $query->row('tag_id'));
		$this->EE->db->update('exp_tagger');

		// Resources are not free
		$query->free_result();

		return $this->EE->TMPL->tagdata;
	}

	// ********************************************************************************* //

	public function tagger_router()
	{

		// -----------------------------------------
		// Ajax Request?
		// -----------------------------------------
		if ($this->EE->input->get('tagger_ajax') != FALSE)
		{
			// Load Library
			$this->EE->load->library('tagger_ajax');

			// Shoot the requested method
			$method = $this->EE->input->get_post('ajax_method');
			echo $this->EE->tagger_ajax->$method();
			exit();
		}
	}

} // END CLASS

/* End of file mod.tagger.php */
/* Location: ./system/expressionengine/third_party/tagger/mod.tagger.php */