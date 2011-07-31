<?php if ( ! defined('EXT')) { exit('Invalid file request'); }

/**
* @package ExpressionEngine
* @author Wouter Vervloet
* @copyright  Copyright (c) 2010, Baseworks
* @license    http://creativecommons.org/licenses/by-sa/3.0/
* 
* This work is licensed under the Creative Commons Attribution-Share Alike 3.0 Unported.
* To view a copy of this license, visit http://creativecommons.org/licenses/by-sa/3.0/
* or send a letter to Creative Commons, 171 Second Street, Suite 300,
* San Francisco, California, 94105, USA.
* 
*/

require PATH_THIRD.'mobile/config.php';

class Mobile_ext
{
  public $settings            = array();

  public $name                = BW_MOBILE_NAME;
  public $version             = BW_MOBILE_VERSION;
  public $description         = BW_MOBILE_DESCRIPTION;
  public $settings_exist      = 'y';
  public $docs_url            = '';

  private $_template_id = FALSE;		

  private $_prefix = 'mobile';
			
  // -------------------------------
  // Constructor
  // -------------------------------
  function Mobile_ext($settings='')
  {
    $this->__construct($settings);
  }
  // END Mobile_ext
  
  function __construct($settings='')
  {
    $this->EE =& get_instance();
    $this->settings = $settings;
    
    $this->site_id = $this->EE->config->item('site_id');
    
  }
  // END __construct
	
  /**
  * ...
  */
  function sessions_start($SESS)
  {
    // You're in the CP, so don't do anything
    if (defined('REQ') AND REQ == 'CP') return;
        
    // Not a mobile browser
    if ( ! $this->_is_mobile()) return;
    
	  $pages = $this->EE->config->config['site_pages'][$this->EE->config->item('site_id')];
	  $templates = $pages['templates'];
	  $uris = $pages['uris'];
	  
	  if (is_array($uris))
	  {
  	  if ($index = array_search('/'.$this->EE->uri->uri_string, $uris))
  	  {	      	    
  	    $query = $this->EE->db
  	                      ->select('template_groups.group_name, templates.template_name')
  	                      ->where('templates.template_id', $templates[$index])
  	                      ->join('template_groups', 'templates.group_id=template_groups.group_id')
  	                      ->from('templates')
  	                      ->get();

  	    // No template was found, exit here
  	    if ($query->num_rows() === 0) return;

  	    $mobile_group_name = $this->_prefix.'__'.$query->row('group_name');
  	    $mobile_template_name = $query->row('template_name');

        // No mobile template was found, exit here
        if ( $this->_template_exists($mobile_group_name, $mobile_template_name) === FALSE) return;

        $templates[$index] = $this->_template_id;

        $pages['templates'] = $templates;
        $this->EE->config->config['site_pages'][$this->EE->config->item('site_id')] = $pages;

        return;
  	  }	    
	  }
	  
    $template_group = @$this->EE->uri->segments[1];
    $template_name = @$this->EE->uri->segments[2];
    
    if ($this->_template_exists($this->_prefix.'__'.$template_group, $template_name))
    {
  	  $this->EE->uri->segments[1] = $this->_mobile_template_group;
    }
	  
  }
  // END sessions_start
/*  
  function settings_form($current='')
  {
    $vars = array();
    $vars['hidden'] = array();
    $vars['fields'] = $this->settings();

    return $this->EE->load->view('extension_settings', $vars, true);
  }
*/
  function settings()
  {
    $this->EE->load->add_package_path(PATH_THIRD.strtolower(BW_MOBILE_NAME).'/');
    $this->EE->load->library('client');
    $settings = array();

    foreach ($this->EE->client->mobile_clients as $mb)
    {
      $prepped_client = $this->_prep_client_string($mb);
      $val = isset($this->settings[$prepped_client]) ? $this->settings[$prepped_client] : 'mobile';
      $settings[$prepped_client] = array('i', '', $val);
    }      
    
    return $settings;
  }

  private function _prep_client_string($str='')
  {
    return strtolower(str_replace(' ', '_', $str));
  }

  private function _template_exists($template_group='', $template_name='')
  { 
    
    $template_group = ($template_group == $this->_prefix.'__') ? $this->_prefix.'__'.$this->_fetch_default_template_group() : $template_group;
    $template_name = ! $template_name ? 'index' : $template_name;
        
    if ( ! $template_group) return FALSE;
    
    $query = $this->EE->db
                         ->select('template_id')
                         ->where('template_groups.group_name', $template_group)
                         ->where('templates.template_name', $template_name)
 	                      ->join('template_groups', 'templates.group_id=template_groups.group_id')
 	                      ->from('templates')
 	                      ->get();

    if ($query->num_rows() === 1)
    {
      $this->_template_id = $query->row('template_id');
      $this->_mobile_template_group = $template_group;
      return TRUE;
    }

    return FALSE;

  }
  // END _template_exists
  
  private function _fetch_default_template_group()
  {
    $query = $this->EE->db->where('is_site_default', 'y')->get('template_groups');
    
    // If there is a default template_group, return it
    if ($query->num_rows() > 0) return $query->row('group_name');
    
    // There is no default template_group. GO AWAY!
    return FALSE;
  }
  // END _fetch_default_template_group
  
  private function _is_mobile()
  {
    $agent = $_SERVER['HTTP_USER_AGENT'];
    
    $this->EE->load->library('client');
    
    $is_mobile = $this->EE->client->is_mobile($agent);
    
    if ($is_mobile)
    {
      $this->_prefix = $this->settings[$this->EE->client->mobile_client];
      $this->EE->config->_global_vars['is_mobile'] = TRUE;
      $this->EE->config->_global_vars['mobile_client'] = $this->EE->client->mobile_client;
      return TRUE;
    }
    
    return FALSE;
    
  }
  // END _is_mobile

	// --------------------------------
	//  Activate Extension
	// --------------------------------
	function activate_extension()
	{

    // hooks array
    $hooks = array(
      'sessions_start' => 'sessions_start'
    );

    // insert hooks and methods
    foreach ($hooks AS $hook => $method)
    {
      // data to insert
      $data = array(
        'class'		=> get_class($this),
        'method'	=> $method,
        'hook'		=> $hook,
        'priority'	=> 1,
        'version'	=> $this->version,
        'enabled'	=> 'y',
        'settings'	=> ''
      );

      // insert in database
      $this->EE->db->insert('exp_extensions', $data);
    }

    return true;
	}
	// END activate_extension
	 
	 
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	function update_extension($current='')
	{
		
    if ($current == '' OR $current == $this->version)
    {
      return FALSE;
    }
    
    if($current < $this->version) { }

    // init data array
    $data = array();

    // Add version to data array
    $data['version'] = $this->version;    

    // Update records using data array
    $this->EE->db->where('class', get_class($this));
    $this->EE->db->update('exp_extensions', $data);
  }
  // END update_extension

	// --------------------------------
	//  Disable Extension
	// --------------------------------
	function disable_extension()
	{		
    // Delete records
    $this->EE->db->where('class', get_class($this));
    $this->EE->db->delete('exp_extensions');
  }
  // END disable_extension

	 
}
// END CLASS