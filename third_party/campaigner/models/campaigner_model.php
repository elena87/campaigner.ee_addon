<?php if ( ! defined('EXT')) exit('Invalid file request.');

/**
 * Example add-on model.
 *
 * @author			: Stephen Lewis
 * @copyright		: Experience Internet
 * @package			: Campaigner
 */

require_once PATH_THIRD .'campaigner/classes/campaigner_settings' .EXT;

class Campaigner_model extends CI_Model {
	
	/* --------------------------------------------------------------
	 * PRIVATE PROPERTIES
	 * ------------------------------------------------------------ */
	
	/**
	 * API connector.
	 *
	 * @access	private
	 * @var		CMBase
	 */
	private $_api_connector;
	
	/**
	 * ExpressionEngine object reference.
	 *
	 * @access	private
	 * @var		object
	 */
	private $_ee;
	
	/**
	 * Extension class. Assumed to be the package name,
	 * with an `_ext` suffix.
	 *
	 * @access	private
	 * @var		string
	 */
	private $_extension_class;
	
	/**
	 * Extension settings.
	 *
	 * @access	private
	 * @var		Campaigner_settings
	 */
	private $_extension_settings;
	
	/**
	 * Package name.
	 *
	 * @access	private
	 * @var		string
	 */
	private $_package_name;
	
	/**
	 * Package version.
	 *
	 * @access	private
	 * @var		string
	 */
	private $_package_version;
	
	/**
	 * The extension settings.
	 *
	 * @access	private
	 * @var		Campaigner_settings
	 */
	private $_settings;
	
	/**
	 * The site ID.
	 *
	 * @access	private
	 * @var		string
	 */
	private $_site_id;
	
	/**
	 * Package theme URL.
	 *
	 * @access	private
	 * @var		string
	 */
	private $_theme_url;
	
	
	
	/* --------------------------------------------------------------
	 * PUBLIC METHODS
	 * ------------------------------------------------------------ */

	/**
	 * Constructor.
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct()
	{
		parent::CI_Model();

		$this->_ee =& get_instance();
		
		$this->_package_name		= 'Campaigner';
		$this->_package_version		= '0.1.0';
		$this->_extension_class 	= $this->get_package_name() .'_ext';
		
		/**
		 * The model is still loaded even if the extension isn't installed.
		 * If any database action is taking place in the constructor, first
		 * check to ensure that we're live.
		 */
		
		if ( ! isset($this->_ee->extensions->version_numbers[$this->get_extension_class()]))
		{
			return;
		}
	}
	
	
	/**
	 * Activates the extension.
	 *
	 * @access	public
	 * @return	void
	 */
	public function activate_extension()
	{
		$this->_ee->load->dbforge();
		$dbforge = $this->_ee->dbforge;
		
		// Create the settings table.
		$fields = array(
			'site_id'	=> array(
				'constraint'		=> 5,
				'type'				=> 'int',
				'unsigned'			=> TRUE
			),
			'api_key'	=> array(
				'constraint'			=> 20,
				'type'				=> 'varchar'
			),
			'client_id'	=> array(
				'constraint'		=> 20,
				'type'				=> 'varchar'
			)
		);
		
		$dbforge->add_field($fields);
		$dbforge->add_key('site_id', TRUE);
		$dbforge->create_table('campaigner_settings');
		
		
		// Create the mailing lists table.
		$fields = array(
			'list_id' => array(
				'constraint'	=> 20,
				'type'			=> 'varchar'
			),
			'site_id' => array(
				'constraint'	=> 5,
				'type'			=> 'int',
				'unsigned'		=> TRUE
			),
			'custom_fields' => array(
				'type'			=> 'text'
			),
			'trigger_field_id' => array(
				'constraint'	=> 4,
				'type'			=> 'int',
				'unsigned'		=> TRUE
			),
			'trigger_value' => array(
				'constraint'	=> 255,
				'type'			=> 'varchar'
			)
		);
		
		$dbforge->add_field($fields);
		$dbforge->add_key('list_id', TRUE);
		$dbforge->create_table('campaigner_mailing_lists');
		
		
		// Insert the extension hooks.
		$class = $this->get_extension_class();
		$version = $this->get_package_version();
		
		$hooks = array(
			'cp_members_validate_members',
			'member_member_register',
			'member_register_validate_members',
			'user_edit_end',
			'user_register_end'
		);
		
		for ($count = 0; $count < count($hooks); $count++)
		{
			$data = array(
				'class'		=> $class,
				'enabled'	=> 'y',
				'hook'		=> $hooks[$count],
				'method'	=> 'on_' .$hooks[$count],
				'priority'	=> 10,
				'settings'	=> '',
				'version'	=> $version
			);
			
			$this->_ee->db->insert('extensions', $data);
		}
	}
	
	
	/**
	 * Disables the extension.
	 *
	 * @access	public
	 * @return	void
	 */
	public function disable_extension()
	{
		$this->_ee->db->delete('extensions', array('class' => $this->get_extension_class()));
		
		$this->_ee->load->dbforge();
		$this->_ee->dbforge->drop_table('campaigner_settings');
		$this->_ee->dbforge->drop_table('campaigner_mailing_lists');
	}
	
	
	/**
	 * Wrapper for the CMBase::userGetClients method.
	 *
	 * @access	public
	 * @return	void
	 */
	public function get_clients()
	{
		if ( ! $this->_api_connector)
		{
			throw new Exception('API connector not set.');
		}
		
		$this->_api_connector->userGetClients();
	}
	
	
	/**
	 * Retrieves the custom fields from the supplied POST input array.
	 *
	 * @access	public
	 * @param	array		$custom_fields_data		The POST input array.
	 * @return	array
	 */
	public function get_custom_fields_from_input_array(Array $custom_fields_data = array())
	{
		$custom_fields = array();

		foreach ($custom_fields_data AS $cm_field_id => $ee_field_id)
		{
			if ($ee_field_id)
			{
				$custom_fields[] = new Campaigner_custom_field(array(
					'id'		=> $cm_field_id,
					'field_id'	=> $ee_field_id
				));
			}
		}
		
		return $custom_fields;
	}
	
	
	/**
	 * Returns the extension class name. Assumed to be the package name,
	 * with a `_ext` suffix.
	 *
	 * @access	public
	 * @return	string
	 */
	public function get_extension_class()
	{
		return $this->_extension_class;
	}
	
	
	/**
	 * Returns the extension settings.
	 *
	 * @access	public
	 * @return	Campaigner_settings
	 */
	public function get_extension_settings()
	{
		if ( ! $this->_settings)
		{
			$this->_settings = $this->get_settings_from_db();
			$this->_settings->set_mailing_lists($this->get_mailing_lists_from_db());
		}
		
		return $this->_settings;
	}
	
	
	/**
	 * Retrieves the mailing lists from the `campaigner_mailing_lists` table.
	 *
	 * @access	public
	 * @return	array
	 */
	public function get_mailing_lists_from_db()
	{
		$db_mailing_lists = $this->_ee->db->get_where(
			'campaigner_mailing_lists',
			array('site_id' => $this->get_site_id())
		);
		
		$mailing_lists = array();
		
		foreach ($db_mailing_lists->result_array() AS $db_mailing_list)
		{
			// Extract the custom fields data.
			$custom_fields = unserialize($db_mailing_list['custom_fields']);
			unset($db_mailing_list['custom_fields']);
			
			// Create the basic mailing list object.
			$mailing_list = new Campaigner_mailing_list($db_mailing_list);
			
			// Add the custom fields.
			if (is_array($custom_fields))
			{
				foreach ($custom_fields AS $custom_field)
				{
					$mailing_list->add_custom_field(new Campaigner_custom_field($custom_field));
				}
			}
			
			$mailing_lists[] = $mailing_list;
		}
		
		return $mailing_lists;
	}
	
	
	/**
	 * Retrieves the mailing lists from POST data.
	 *
	 * @access	public
	 * @return	array
	 */
	public function get_mailing_lists_from_input()
	{
		$input 					= $this->_ee->input;
		$active_mailing_lists	= $input->post('active_mailing_lists');
		$all_mailing_lists 		= $input->post('all_mailing_lists');
		$ret_mailing_lists		= array();
		
		foreach ($active_mailing_lists AS $list_id)
		{
			$ret_mailing_list = new Campaigner_mailing_list(array('list_id' => $list_id));
			
			// Trigger field ID.
			if (isset($all_mailing_lists[$list_id]['trigger_field_id']))
			{
				$ret_mailing_list->set_trigger_field_id($all_mailing_lists[$list_id]['trigger_field_id']);
			}
			
			// Trigger value.
			if (isset($all_mailing_lists[$list_id]['trigger_value']))
			{
				$ret_mailing_list->set_trigger_value($all_mailing_lists[$list_id]['trigger_value']);
			}
			
			// Custom fields.
			if (isset($all_mailing_lists[$list_id]['custom_fields']))
			{
				$ret_mailing_list->set_custom_fields(
					$this->get_custom_fields_from_input_array($all_mailing_lists[$list_id]['custom_fields'])
				);
			}
			
			$ret_mailing_lists[] = $ret_mailing_list;
		}
		
		return $ret_mailing_lists;
	}
	
	
	/**
	 * Returns the package name.
	 *
	 * @access	public
	 * @return	string
	 */
	public function get_package_name()
	{
		return $this->_package_name;
	}
	
	
	/**
	 * Returns the package version.
	 *
	 * @access	public
	 * @return	string
	 */
	public function get_package_version()
	{
		return $this->_package_version;
	}
	
	
	/**
	 * Retrieves the settings from the `campaigner_settings` table.
	 *
	 * @access	public
	 * @return	Campaigner_settings
	 */
	public function get_settings_from_db()
	{
		$db_settings = $this->_ee->db->get_where(
			'campaigner_settings',
			array('site_id' => $this->get_site_id()),
			1
		);
		
		$settings_data = $db_settings->num_rows()
			? $db_settings->row_array()
			: array();
		
		return new Campaigner_settings($settings_data);
	}
	
	
	/**
	 * Returns the site ID.
	 *
	 * @access	public
	 * @return	string
	 */
	public function get_site_id()
	{
		if ( ! $this->_site_id)
		{
			$this->_site_id = $this->_ee->config->item('site_id');
		}
		
		return $this->_site_id;
	}
	
	
	/**
	 * Returns the package theme URL.
	 *
	 * @access	public
	 * @return	string
	 */
	public function get_theme_url()
	{
		if ( ! $this->_theme_url)
		{
			$theme_url = $this->_ee->config->item('theme_folder_url');
			$theme_url = substr($theme_url, -1) == '/'
				? $theme_url .'third_party/'
				: $theme_url .'/third_party/';
			
			$this->_theme_url = $theme_url .strtolower($this->get_package_name()) .'/';
		}
		
		return $this->_theme_url;
	}
	
	
	/**
	 * Saves the extension settings.
	 *
	 * @access	public
	 * @param 	Campaigner_settings 	$settings		The settings to save.
	 * @return	void
	 */
	public function save_extension_settings(Campaigner_settings $settings)
	{
		if ( ! $this->save_settings_to_db($settings))
		{
			throw new Exception($this->_ee->lang->line('settings_not_saved'));
		}
		
		if ( ! $this->save_mailing_lists_to_db($settings))
		{
			throw new Exception($this->_ee->lang->line('mailing_lists_not_saved'));
		}
	}
	
	
	/**
	 * Saves the supplied mailing lists to the database.
	 *
	 * @access	public
	 * @param	Campaigner_settings		$settings		The settings.
	 * @return	bool
	 */
	public function save_mailing_lists_to_db(Campaigner_settings $settings)
	{
		$db = $this->_ee->db;
		$site_id = $this->_ee->config->item('site_id');
		
		// Delete the existing settings.
		$db->delete('campaigner_mailing_lists', array('site_id' => $site_id));
		
		// Add the mailing lists.
		$mailing_lists = $settings->get_mailing_lists();
		$success = TRUE;
		
		foreach ($mailing_lists AS $mailing_list)
		{
			$data = $mailing_list->to_array();
			$data['custom_fields'] = serialize($data['custom_fields']);
			$data = array_merge(array('site_id' => $site_id), $data);
			
			$db->insert('campaigner_mailing_lists', $data);
			
			if ($db->affected_rows() !== 1)
			{
				$success = FALSE;
				break;
			}
		}
		
		// One bad badger sullies the set.
		if ( ! $success)
		{
			$db->delete('campaigner_mailing_lists', array('site_id' => $site_id));
		}
		
		return $success;
	}
	
	
	/**
	 * Saves the supplied settings to the database.
	 *
	 * @access	public
	 * @param	Campaigner_settings		$settings		The settings.
	 * @return	bool
	 */
	public function save_settings_to_db(Campaigner_settings $settings)
	{
		$db	= $this->_ee->db;
		$site_id = $this->_ee->config->item('site_id');
		
		// Delete any existing site settings.
		$db->delete('campaigner_settings', array('site_id' => $site_id));
		
		/**
		 * Retrieve the basic settings, remove the mailing lists (which
		 * are handled separately), and add the site ID.
		 */
		
		$settings_data = $settings->to_array();
		unset($settings_data['mailing_lists']);
		$settings_data = array_merge(array('site_id' => $site_id), $settings_data);
		
		// Save the settings to the database.
		$db->insert('campaigner_settings', $settings_data);
		
		return (bool) $db->affected_rows();
	}
	
	
	/**
	 * Sets the API connector object.
	 *
	 * @access	public
	 * @param	CMBase		$api_connector		The API connector object.
	 * @return	void
	 */
	public function set_api_connector(CMBase $api_connector)
	{
		$this->_api_connector = $api_connector;
	}
	
	
	/**
	 * Updates the extension.
	 *
	 * @access	public
	 * @param	string		$installed_version		The installed version.
	 * @param 	string		$package_version		The package version.
	 * @return	bool|void
	 */
	public function update_extension($installed_version = '', $package_version = '')
	{
		if ( ! $installed_version OR version_compare($installed_version, $package_version, '>='))
		{
			return FALSE;
		}
		
		// Update the extension version in the database.
		$this->_ee->db->update(
			'extensions',
			array('version' => $package_version),
			array('class' => $this->get_extension_class())
		);
	}
	
	
	/**
	 * Updates the settings from any POST input.
	 *
	 * @access	public
	 * @param 	Campaigner_settings 	$settings 		The settings object to update.
	 * @return	Campaigner_settings
	 */
	public function update_extension_settings_from_input(Campaigner_settings $settings)
	{
		$settings = $this->update_settings_from_input($settings);
		$settings->set_mailing_lists($this->get_mailing_lists_from_input());
		
		return $settings;
	}
	
	
	/**
	 * Updates the basic settings using POST data.
	 *
	 * @access	public
	 * @param	Campaigner_settings		$settings		The settings object to update.
	 * @return	Campaigner_settings
	 */
	public function update_settings_from_input(Campaigner_settings $settings)
	{
		$input 	= $this->_ee->input;
		$props	= array('api_key', 'client_id');
		
		foreach ($props AS $prop)
		{
			if ($prop_val = $input->post($prop))
			{
				$prop_method = 'set_' .$prop;
				$settings->$prop_method($prop_val);
			}
		}
		
		return $settings;
	}

}

/* End of file		: campaigner_model.php */
/* File location	: third_party/campaigner/models/campaigner_model.php */