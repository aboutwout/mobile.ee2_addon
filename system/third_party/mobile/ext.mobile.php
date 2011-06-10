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
  public $settings_exist      = 'n';
  public $docs_url            = '';
			
	private $_template_id = FALSE;		
			
	// -------------------------------
	// Constructor
	// -------------------------------
	function Mobile_ext($settings='')
	{
	  $this->__construct($settings);
	}
	
	function __construct($settings='')
	{

    $this->EE =& get_instance();
	  
		$this->settings = $settings;
		
	}
	// END Mobile_ext
	
  /**
  * ...
  */
  function sessions_start($SESS)
  {
    // You're in the CP, so don't do anything
    if ($SESS->EE->input->get('D')) return;
    
    // Not a mobile browser
    if ( ! $this->_is_mobile()) return;
    
	  $pages = $SESS->EE->config->config['site_pages'][$SESS->EE->config->item('site_id')];
	  $templates = $pages['templates'];
	  $uris = $pages['uris'];

	  if ($index = array_search('/'.$SESS->EE->uri->uri_string, $uris))
	  {	    
	    $query = $this->EE->db
	                      ->select('template_groups.group_name, templates.template_name')
	                      ->where('templates.template_id', $templates[$index])
	                      ->join('template_groups', 'templates.group_id=template_groups.group_id')
	                      ->from('templates')
	                      ->get();
	    
	    // No template was found, exit here
	    if ($query->num_rows() === 0)
	    {
	      return;
      }

	    $mobile_group_name = 'mobile__'.$query->row('group_name');
	    $mobile_template_name = $query->row('template_name');

      // No mobile template was found, exit here
      if ( $this->_template_exists($mobile_group_name, $mobile_template_name) === FALSE)
      {
        return;
      }
      
      $templates[$index] = $this->_template_id;
  	    
      $pages['templates'] = $templates;
      $SESS->EE->config->config['site_pages'][$SESS->EE->config->item('site_id')] = $pages;
      
      return;
	  }
	  
	  if (isset($SESS->EE->uri->segments[1]))
	  {
	    $template_group = @$SESS->EE->uri->segments[1];
	    $template_name = @$SESS->EE->uri->segments[2];
	    
	    if ($this->_template_exists('mobile__'.$template_group, $template_name))
	    {
    	  $SESS->EE->uri->segments[1] = 'mobile__'.$SESS->EE->uri->segments[1];	    	      
	    }
	  }
	  
  }
  // END sessions_start

  private function _template_exists($template_group='', $template_name='')
  {
    $template_group = ! $template_group ? $this->_fetch_default_template_group() : $template_group;
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
      return TRUE;
    }
    else
    {
      return FALSE;
    }

  }
  // END _template_exists
  
  private function _fetch_default_template_group()
  {
    $query = $this->EE->db->where('is_site_default', 'y')->get('template_groups');
    
    if ($query->num_rows() > 0)
    {
      return $query->row('group_name');
    }
    
    return FALSE;
  }
  // END _fetch_default_template_group
  
  private function _is_mobile()
  {
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $client = new Client();
        
    return $client->isMobileClient($agent);
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


class Client
{
  /**
  * Available Mobile Clients
  *  http://www.zytrax.com/tech/web/mobile_ids.html
  * @var array
  */
  private $_mobileClients = array("midp", "240x320", "blackberry", "netfront", "nokia", "panasonic", "portalmmm", "sharp", "sie-", "sonyericsson", "symbian", "windows ce", "benq", "mda", "mot-", "opera mini", "philips", "pocket pc", "sagem", "samsung", "sda", "sgh-", "vodafone", "xda", "iphone", "android", "ipad");

  /**
  * Check if client is a mobile client
  * @param string $userAgent
  * @return boolean
  */
  public function isMobileClient($userAgent)
  {
    $userAgent = strtolower($userAgent);
    foreach ($this->_mobileClients as $mobileClient)
    {
      if (strstr($userAgent, $mobileClient))
      {
        return TRUE;
      }
    }
    return FALSE;
  }
    
}