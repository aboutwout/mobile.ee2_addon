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
  
  private $_mobile_check = TRUE;
  
  private $_cookie_timeout = 2678400; // 1 month
			
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
    
    $this->_mobile_check = ($this->EE->input->cookie(strtolower(__CLASS__).'_on') === 'no') ? FALSE : TRUE;
       
  }
  // END __construct
	
  /**
  * ...
  */
  function sessions_start($SESS)
  {
    // You're in the CP, so don't do anything
    if (defined('REQ') AND REQ == 'CP') return;
        
    $this->EE->session = $SESS;
    
    // If the URL contains 'MOBILE_ACT' execute this code and redirect afterwards
    if (isset($_REQUEST['MOBILE_ACT']) AND in_array($_REQUEST['MOBILE_ACT'], array('STF', 'STM')))
    {
      switch($_REQUEST['MOBILE_ACT'])
      {
        case 'STF':
          $this->_switch_to_full();
          break;
        case 'STM':
          $this->_switch_to_mobile();
          break;
      }      
      $this->EE->functions->redirect($_SERVER['HTTP_REFERER']);
    }
    
    $this->EE->config->_global_vars['mobile:switch_to_full'] = $this->EE->functions->create_url('?MOBILE_ACT=STF');
    $this->EE->config->_global_vars['mobile:switch_to_mobile'] = $this->EE->functions->create_url('?MOBILE_ACT=STM');
    
    
    // Check for mobile and set global vars
    $this->_is_mobile();
    

    // Mobile redirect is disabled
    if ($this->_mobile_check === FALSE) return;

	  $pages = $this->EE->config->config['site_pages'][$this->site_id];
	  $templates = $pages['templates'];
	  $uris = $pages['uris'];
	  
	  if (is_array($uris))
	  {
  	  if ($index = array_search('/'.$this->EE->uri->uri_string, $uris) OR $index = array_search('/'.$this->EE->uri->uri_string.'/', $uris))
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
        $this->EE->config->config['site_pages'][$this->site_id] = $pages;

        return;
  	  }	    
	  }

	  
    $template_group = @$this->EE->uri->segments[1];
    $template_name = @$this->EE->uri->segments[2];
    
    if ( $check = $this->_check_template($template_group, $template_name))
    {
      list($template_group, $template_name) = $check;
    }
            
    if ($this->_template_exists($this->_prefix.'__'.$template_group, $template_name))
    {      
  	  $this->EE->uri->segments[1] = $this->_mobile_template_group;
  	  $this->EE->uri->segments[2] = $template_name;
    }
	  
  }
  // END sessions_start


  function settings()
  {
    $this->EE->load->add_package_path(PATH_THIRD.strtolower(BW_MOBILE_NAME).'/');
    $this->EE->load->library('client');
    $settings = array();

    foreach ($this->EE->client->mobile_clients as $mb)
    {
      $prepped_client = $this->_prep_client_string($mb);
      $val = isset($this->settings[$prepped_client]) ? $this->settings[$prepped_client] : '';
      $settings[$prepped_client] = array('i', '', $val);
    }      
    
    return $settings;
  }

  private function _prep_client_string($str='')
  {
    return strtolower(str_replace(' ', '_', $str));
  }
  
  private function _check_template($template_group='', $template_name='')
  {
    $site_id = $this->site_id;
    $default_group = $this->_fetch_default_template_group();
    $template_name = $template_name ? $template_name : 'index';
    
    $ors = array();

    if ($template_group)
    {
      $ors[] = "(tg.group_name='$template_group' AND t.template_name='$template_name')";
      $ors[] = "(tg.group_name='$default_group' AND t.template_name='$template_group')";
    }
    else
    {
      $ors[] = "(tg.group_name='$default_group' AND t.template_name='index')";
    }
    
    $sql = "SELECT tg.group_name AS template_group, t.template_name AS template_name FROM exp_templates t LEFT JOIN exp_template_groups tg ON t.group_id=tg.group_id WHERE tg.site_id='$site_id'";
    
    if (count($ors) > 0)
    {
      $sql .= " AND (".implode(' OR ', $ors).")";      
    }
    
    $query = $this->EE->db->query($sql);

    if ($query->num_rows() > 0)
    {
      return array($query->row('template_group'), $query->row('template_name'));      
    }

    if ($template_group)
    {
      $sql_fallback = "SELECT tg.group_name AS template_group, t.template_name AS template_name FROM exp_templates t LEFT JOIN exp_template_groups tg ON t.group_id=tg.group_id WHERE tg.site_id='$site_id' AND (tg.group_name='$template_group' AND t.template_name='index')";
      
      $query_fallback = $this->EE->db->query($sql_fallback);
            
      if ($query_fallback->num_rows() > 0)
      {
        return array($query_fallback->row('template_group'), $query_fallback->row('template_name'));      
      }
    }
    
    return array($default_group, 'index');
  }

  private function _template_exists($template_group='', $template_name='')
  { 
    // $template_group = ($template_group == $this->_prefix.'__') ? $this->_prefix.'__'.$this->_fetch_default_template_group() : $template_group;
    // $template_name = ! $template_name ? 'index' : $template_name;
        
    if ( ! $template_group) return FALSE;
    
    $query = $this->EE->db
      ->select('template_id')
 	    ->join('template_groups', 'templates.group_id=template_groups.group_id')
      ->where('template_groups.group_name', $template_group)
      ->where('templates.template_name', $template_name)
 	    ->where('template_groups.site_id', $this->site_id)
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
    $query = $this->EE->db
      ->where('is_site_default', 'y')
      ->where('site_id', $this->site_id)
      ->get('template_groups');
    
    // If there is a default template_group, return it
    if ($query->num_rows() > 0) return $query->row('group_name');
    
    // There is no default template_group. GO AWAY!
    return FALSE;
  }
  // END _fetch_default_template_group
  
  private function _switch_to_full()
  {
    $this->EE->functions->set_cookie(strtolower(__CLASS__).'_on', 'no', $this->_cookie_timeout);
  }
  
  private function _switch_to_mobile()
  {
    $this->EE->functions->set_cookie(strtolower(__CLASS__).'_on', 'yes', $this->_cookie_timeout);
  }
  
  private function _is_mobile()
  {
    
    $agent = $_SERVER['HTTP_USER_AGENT'];
    
    $this->EE->load->library('client');
    
    $is_mobile = $this->EE->client->is_mobile($agent);

    $this->_prefix = isset($this->settings[$this->EE->client->mobile_client]) ? $this->settings[$this->EE->client->mobile_client] : '';
    
    $this->EE->config->_global_vars['is_mobile'] = $is_mobile;
    $this->EE->config->_global_vars['is_desktop'] = ! $is_mobile;
    $this->EE->config->_global_vars['mobile_client'] = $this->EE->client->mobile_client;
    
    return $is_mobile;
    
  }
  // END _is_mobile
  
  function _get_default_settings() {
    $this->EE->load->add_package_path(PATH_THIRD.strtolower(BW_MOBILE_NAME).'/');
    $this->EE->load->library('client');
    $settings = array();

    foreach ($this->EE->client->mobile_clients as $mb)
    {
      $prepped_client = $this->_prep_client_string($mb);
      $settings[$prepped_client] = 'mobile';
    }
    
    return $settings;  
  }

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
        'priority'	=> 10,
        'version'	=> $this->version,
        'enabled'	=> 'y',
        'settings'	=> serialize($this->_get_default_settings())
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
		
		$settings = array();
				
    if ($current == '' OR $current == $this->version)
    {
      return FALSE;
    }
    
    if ($current < $this->version) {
      
      $this->EE->db->where('class', get_class($this));      
      $settings = unserialize($this->EE->db->get('extensions')->row('settings'));
      
      if ( ! $settings || ! is_array($settings))
      {
        $settings = $this->_get_default_settings();
      }
      
    }

    // init data array
    $data = array(
      'settings' => serialize($settings),
      'version' => $this->version
    );

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