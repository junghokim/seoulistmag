<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2010, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Home Page Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */
class Admin_system extends CI_Controller {

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Index function
	 *
	 * @access	public
	 * @return	void
	 */
	function index()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->cp->set_variable('cp_page_title', $this->lang->line('admin_system'));

		$this->load->vars(array('controller'=>'admin'));

		$this->load->view('_shared/overview');
	}

	// --------------------------------------------------------------------

	/**
	 * Email Configuration
	 *
	 * @access	public
	 * @return	void
	 */
	function email_configuration()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('email_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * General Configuration
	 *
	 * @access	public
	 * @return	void
	 */
	function general_configuration()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('general_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Config Manager
	 *
	 * Used to display the various preference pages
	 *
	 * @access	public
	 * @return	void
	 */
	function _config_manager($type, $return_loc)
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->jquery->tablesorter('.mainTable', '{
			widgets: ["zebra"],
			headers: {
				1: { sorter: false }
			},
			textExtraction: function(node) {
				var c = $(node).children();
				
				if (c.length) {
					return c.text();
				}
				else {
					return node.innerHTML;
				}
			}
		}');

		$this->load->helper('form');
		$this->load->library('table');
		$this->load->model('admin_model');

		if ( ! in_array($type, array(
									'general_cfg',
									'cp_cfg',
									'channel_cfg',
									'member_cfg',
									'output_cfg',
									'debug_cfg',
									'db_cfg',
									'security_cfg',
									'throttling_cfg',
									'localization_cfg',
									'email_cfg',
									'cookie_cfg',
									'image_cfg',
									'captcha_cfg',
									'template_cfg',
									'censoring_cfg',
									'mailinglist_cfg',
									'emoticon_cfg',
									'tracking_cfg',
									'avatar_cfg',
									'search_log_cfg',
									'recount_prefs'
									)
						)
		)
		{
			show_error($this->lang->line('unauthorized_access'));
		}
		$vars['type'] = $type;

		$vars['form_action'] = 'C=admin_system'.AMP.'M=update_config';

		$f_data = $this->admin_model->get_config_fields($type);
		$subtext = $this->admin_model->get_config_field_subtext();

		// Blast through the array
		// If we're dealing with a database configuration we need to pull the data out of the DB
		// config file. To make thigs simple we will set the DB config items as general config values
		if ($type == 'db_cfg')
		{
			require $this->config->database_path;

			if ( ! isset($active_group))
			{
				$active_group = 'expressionengine';
			}

			if (isset($db[$active_group]))
			{
				$db[$active_group]['pconnect'] = ($db[$active_group]['pconnect'] === TRUE) ? 'y' : 'n';
				$db[$active_group]['cache_on'] = ($db[$active_group]['cache_on'] === TRUE) ? 'y' : 'n';
				$db[$active_group]['db_debug'] = ($db[$active_group]['db_debug'] === TRUE) ? 'y' : 'n';

				$this->config->set_item('pconnect', $db[$active_group]['pconnect']);
				$this->config->set_item('cache_on', $db[$active_group]['cache_on']);
				$this->config->set_item('cachedir', $db[$active_group]['cachedir']);
				$this->config->set_item('db_debug', $db[$active_group]['db_debug']);
			}
		}

		foreach ($f_data as $name => $options)
		{
			$value = $this->config->item($name);

			$sub = '';
			$details = '';
			$selected = '';

			if (isset($subtext[$name]))
			{
				foreach ($subtext[$name] as $txt)
				{
					$sub .= $this->lang->line($txt);
				}
			}

			switch ($options[0])
			{
				case 's':
					// Select fields
					foreach ($options[1] as $k => $v)
					{
						$details[$k] = $this->lang->line($v);
					}
					$selected = $value;
					break;
				case 'r':
					// Radio buttons
					foreach ($options[1] as $k => $v)
					{
						// little cheat for some values popped into a build update
						if ($value === FALSE)
						{
							// MSM override
							// The key 'multiple_sites_enabled' is listed in admin_model->get_config_fields() as it must be,
							// but its possible that this install doesn't have it available as a config option. In these cases
							// the below code will cause neither "yes" or "no" to be preselected, but instead we want 
							// "enable multiple site manager" in General Configuration to be "no".
							if ($name == 'multiple_sites_enabled' AND $k == 'n')
							{
								$checked = TRUE;
							}
							else
							{
								$checked = (isset($options['2']) && $k == $options['2']) ? TRUE : FALSE;
							}
						}
						else
						{
							$checked = ($k == $value) ? TRUE : FALSE;
						}

						$details[] = array('name' => $name, 'value' => $k, 'id' => $name.'_'.$k, 'label' => $v, 'checked' => $checked);
					}
					break;
				case 't':
					// Textareas

					// The "kill_pipes" index instructs us to turn pipes into newlines
					if (isset($options['1']['kill_pipes']) && $options['1']['kill_pipes'] === TRUE)
					{
						$text = str_replace('|', NL, $value);
					}
					else
					{
						$text = $value;
					}

					$rows = (isset($options['1']['rows'])) ? $options['1']['rows'] : '20';

					$text = str_replace("\\'", "'", $text);

					$details = array('name' => $name, 'class' => 'module_textarea', 'value' => $text, 'rows' => $rows, 'id' => $name);
					break;
				case 'f':
					// Function calls
					switch ($options['1'])
					{
						case 'language_menu'	:
							$options[0] = 's';
							$details = $this->admin_model->get_installed_language_packs();
							$selected = $value;
							break;
						case 'fetch_encoding'	:
							$options[0] = 's';
							$details = $this->admin_model->get_xml_encodings();
							$selected = $value;
							break;
						case 'site_404'			:
							$options[0] = 's';
							$details = $this->admin_model->get_template_list();
							$selected = $value;
							break;
						case 'theme_menu'		:
							$options[0] = 's';
							$details = $this->admin_model->get_cp_theme_list();
							$selected = $value;
							break;
						case 'timezone'			:
							$options[0] = 's';
							foreach ($this->localize->zones as $k => $v)
							{
								$details[$k] = $this->lang->line($k);
							}
							$selected = $value;
							break;
					}
					break;
				case 'i':
					// Input fields
					$details = array('name' => $name, 'value' => str_replace("\\'", "'", $value), 'id' => $name);
					break;
			}

			$vars['fields'][$name] = array('type' => $options[0], 'value' => $details, 'subtext' => $sub, 'selected' => $selected);
		}

		// if this is an update, show the success message
		$vars['return_loc'] = BASE.AMP.'C=admin_system'.AMP.'M='.$return_loc.AMP.'U=1';

		$this->cp->set_variable('cp_page_title', $this->lang->line($type));

		$this->javascript->compile();

		$this->load->view('admin/config_pages', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Update Config
	 *
	 * Handles system and site pref form submissions
	 *
	 * @access	public
	 * @return	void
	 */
	function update_config()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$loc = $this->input->get_post('return_location');

		$config_update = $this->config->update_site_prefs($_POST);

		if ( ! empty($config_update))
		{
			$this->load->helper('html');
			$this->session->set_flashdata('message_failure', ul($config_update, array('class' => 'bad_path_error_list')));
		}
		else
		{
			$this->session->set_flashdata('message_success', $this->lang->line('preferences_updated'));
		}
		
		if ($loc !== FALSE)
		{
			$this->functions->redirect($loc);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Control Panel Settings
	 *
	 * @access	public
	 * @return	void
	 */
	function control_panel_settings()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('cp_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Security and Session Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function security_session_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('security_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Output and Debugging Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function output_debugging_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('output_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Localization Settings
	 *
	 * @access	public
	 * @return	void
	 */
	function localization_settings()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('localization_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Database Settings
	 *
	 * @access	public
	 * @return	void
	 */
	function database_settings()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('db_cfg', __FUNCTION__);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Recount Preferences
	 *
	 * @access public
	 * @return void
	 */
	function recount_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_system_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}
		
		$this->_config_manager('recount_prefs', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Mailing List Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function mailing_list_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('mailinglist_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Image Resizing Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function image_resizing_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('image_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * CAPTCHA Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function captcha_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('captcha_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Referrer Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function tracking_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('tracking_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Cookie Settings
	 *
	 * @access	public
	 * @return	void
	 */
	function cookie_settings()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->lang->loadfile('email');
		$this->_config_manager('cookie_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Search Term Log Configuration
	 *
	 * @access	public
	 * @return	void
	 */
	function search_log_configuration()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('search_log_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Throttling Configuration
	 *
	 * @access	public
	 * @return	void
	 */
	function throttling_configuration()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('throttling_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Word Censoring
	 *
	 * @access	public
	 * @return	void
	 */
	function word_censoring()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('censoring_cfg', __FUNCTION__);
	}

	// --------------------------------------------------------------------

	/**
	 * Emoticon Preferences
	 *
	 * @access	public
	 * @return	void
	 */
	function emoticon_preferences()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->_config_manager('emoticon_cfg', __FUNCTION__);
	}
	
	
	// --------------------------------------------------------------------

	/**
	 * Configuration Editor
	 *
	 * This interface allows for the editing of config.php items through the CP
	 *
	 * @access	public
	 * @return	void
	 */
	function config_editor()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->load->helper(array('form', 'string'));
		$this->cp->set_variable('cp_page_title', $this->lang->line('config_editor'));

		$vars['config_items'] = $this->config->default_ini;
		ksort($vars['config_items']);

		// There are some config keys that we don't want to allow through here, let's go though and unset them
		$blacklist_items = array(
			'app_version',
			'base_url', // doesn't really do anything in EE, removed
			'subclass_prefix',
			'enable_query_strings',
			'directory_trigger',
			'controller_trigger',
			'function_trigger'
		);

		$vars['hidden'] = array();

		foreach ($blacklist_items as $blacklist_item)
		{
			if (isset($vars['config_items'][$blacklist_item]))
			{
				$vars['hidden'][$blacklist_item] = $vars['config_items'][$blacklist_item];
				unset($vars['config_items'][$blacklist_item]);
			}
		}

		$this->javascript->output('
			$("table tbody tr:visible:even").addClass("even");
			$("table tbody tr:visible:odd").addClass("odd");
		');

		$this->javascript->compile();

		$this->load->view('admin/config_editor', $vars);
	}

	// --------------------------------------------------------------------

	/**
	 * CodeIgniter Configuration Process
	 *
	 * Processes and saves the changes from config_editor()
	 *
	 * @access	public
	 * @return	void
	 */
	function config_editor_process()
	{
		if ( ! $this->cp->allowed_group('can_access_admin') OR ! $this->cp->allowed_group('can_access_sys_prefs'))
		{
			show_error($this->lang->line('unauthorized_access'));
		}

		$this->load->helper('security');

		// new config added?
		if ($this->input->post('config_name') != '')
		{
			$_POST[url_title($_POST['config_name'])] = $_POST['config_setting'];
		}

		unset($_POST['config_name'], $_POST['config_setting'], $_POST['update']); // Submit button

		$config = xss_clean($_POST);

		$this->config->_update_config($config);

		$this->session->set_flashdata('message_success', $this->lang->line('preferences_updated'));
		$this->functions->redirect(BASE.AMP.'C=admin_system'.AMP.'M=config_editor');
	}

	
	

}

/* End of file admin_system.php */
/* Location: ./system/expressionengine/controllers/cp/admin_system.php */