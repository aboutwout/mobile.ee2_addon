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

  public $version             = BW_MOBILE_VERSION;
  public $name                = BW_MOBILE_NAME;
  public $description         = BW_MOBILE_DESCRIPTION;
  public $docs_url            = BW_MOBILE_DOCS;
  public $settings_exist      = BW_MOBILE_SETTINGS_EXIST;

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
    
    $this->EE->load->add_package_path(PATH_THIRD.'mobile/');
    $this->EE->load->library('client');
    
    // You're in the CP, so don't do anything
    if (defined('REQ') AND REQ == 'CP') return;
    
    $this->site_id = $this->EE->config->item('site_id');
        
    $this->_mobile_check = ($this->EE->input->cookie('mobile_on') === 'no') ? FALSE : TRUE;
    
    // $this->_is_mobile();
    // $this->_set_global_vars();
  }
  // END __construct
  
  public function core_template_route($current_uri=NULL)
  {
    $this->_is_mobile();
    $this->_set_global_vars();

    if (is_null($current_uri) OR ! $this->_mobile_check)
    {
      return;
    }

    return $this->_get_mobile_template($current_uri);
  }
	
  /**
  * ...
  */
  function sessions_start()
  {
    // You're in the CP, so don't do anything
    if (defined('REQ') AND REQ == 'CP') return;
            
    // If the URL contains 'MOBILE_ACT' execute this code and redirect afterwards
    if (isset($_GET['MOBILE_ACT']) AND in_array($_GET['MOBILE_ACT'], array('STF', 'STM')))
    {
      switch($_GET['MOBILE_ACT'])
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
  }
  // END sessions_start


  function settings()
  {
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
  
  private function _get_mobile_template($uri='')
  {    
    $template = $this->_find_template($uri);
    
    if ($this->_template_exists($this->_prefix.'__'.$template['template_group'], $template['template']))
    {
      return array($this->_prefix.'__'.$template['template_group'], $template['template']);
    }
    
    return FALSE;
  }
  
  
  private function _find_template($uri='')
  {
		$pages		= $this->EE->config->item('site_pages');
		$site_id	= $this->EE->config->item('site_id');
		$entry_id	= FALSE;
		
		// If we have pages, we'll look for an entry id
		if ($pages && isset($pages[$site_id]['uris']))
		{
			$match_uri = '/'.trim($uri, '/');	// will result in '/' if uri_string is blank
			$page_uris = $pages[$site_id]['uris'];
			
			$entry_id = array_search($match_uri, $page_uris);
			
			if ( ! $entry_id AND $match_uri != '/')
			{
				$entry_id = array_search($match_uri.'/', $page_uris);
			}
		}
		
		// Found an entry - grab related template
		if ($entry_id)
		{
			$qry = $this->EE->db->select('t.template_name, tg.group_name')
				->from(array('templates t', 'template_groups tg'))
				->where('t.group_id', 'tg.group_id', FALSE)
				->where('t.template_id', $pages[$site_id]['templates'][$entry_id])
				->get();
			
			if ($qry->num_rows() > 0)
			{
				$template = $qry->row('template_name');
				$template_group = $qry->row('group_name');
				$this->EE->uri->page_query_string = $entry_id;
			}
		}
		
		if ($parsed = $this->_parse_template_uri())
		{
		  list($template_group, $template) = $parsed;
		}
		
		return array(
		  'template_group' => $template_group,
		  'template' => $template
		);
  }
  
  private function _parse_template_uri()
  {
		// Does the first segment exist?  No?  Show the default template   
		if ($this->EE->uri->segment(1) === FALSE)
		{
		  $default_group = $this->_fetch_default_template_group();
      return array($default_group, 'index');
    }
    // Is only the pagination showing in the URI?
    elseif(count($this->EE->uri->segments) == 1 && 
    		preg_match("#^(P\d+)$#", $this->EE->uri->segment(1), $match))
    {
    	$this->EE->uri->query_string = $match['1'];
		  $default_group = $this->_fetch_default_template_group();
      return array($default_group, 'index');
    }
        
    // Set the strict urls pref
    if ($this->EE->config->item('strict_urls') !== FALSE)
    {
    	$strict_urls = ($this->EE->config->item('strict_urls') == 'y') ? TRUE : FALSE;
    }

		// Load the string helper
		$this->EE->load->helper('string');
		
		// At this point we know that we have at least one segment in the URI, so
		// let's try to determine what template group/template we should show
		
		// Is the first segment the name of a template group?
		$this->EE->db->select('group_id');
		$this->EE->db->where('group_name', $this->EE->uri->segment(1));
		$this->EE->db->where('site_id', $this->EE->config->item('site_id'));
		$query = $this->EE->db->get('template_groups');
		
		// Template group found!
		if ($query->num_rows() == 1)
		{
			// Set the name of our template group
			$template_group = $this->EE->uri->segment(1);

      // $this->log_item("Template Group Found: ".$template_group);
			
			// Set the group_id so we can use it in the next query
			$group_id = $query->row('group_id');
		
			// Does the second segment of the URI exist? If so...
			if ($this->EE->uri->segment(2) !== FALSE)
			{
				// Is the second segment the name of a valid template?
				$this->EE->db->select('COUNT(*) as count');
				$this->EE->db->where('group_id', $group_id);
				$this->EE->db->where('template_name', $this->EE->uri->segment(2));
				$query = $this->EE->db->get('templates');
			
				// We have a template name!
				if ($query->row('count') == 1)
				{
					// Assign the template name
					$template = $this->EE->uri->segment(2);
					
					// Re-assign the query string variable in the Input class so the various tags can show the correct data
					$this->EE->uri->query_string = ( ! $this->EE->uri->segment(3) AND $this->EE->uri->segment(2) != 'index') ? '' : trim_slashes(substr($this->EE->uri->uri_string, strlen('/'.$this->EE->uri->segment(1).'/'.$this->EE->uri->segment(2))));
					
					
				}
				else // A valid template was not found
				{
					
					// Set the template to index
					$template = 'index';
				   
					// Re-assign the query string variable in the Input class so the various tags can show the correct data
					$this->EE->uri->query_string = ( ! $this->EE->uri->segment(3)) ? $this->EE->uri->segment(2) : trim_slashes(substr($this->EE->uri->uri_string, strlen('/'.$this->EE->uri->segment(1))));
				}
			}
			// The second segment of the URL does not exist
			else
			{
				// Set the template as "index"
				$template = 'index';
			}
		}
		// The first segment in the URL does NOT correlate to a valid template group.  Oh my!
		else 
		{
			// If we are enforcing strict URLs we need to show a 404
			if ($strict_urls == TRUE)
			{
        return FALSE;
			}
			
			// We we are not enforcing strict URLs, so Let's fetch the the name of the default template group
			$result = $this->EE->db->select('group_name, group_id')
				->get_where(
					'template_groups',
					array(
						'is_site_default' => 'y',
						'site_id' => $this->EE->config->item('site_id')
					)
				);

			// No result?  Bail out...
			// There's really nothing else to do here.  We don't have a valid
			// template group in the URL and the admin doesn't have a template
			// group defined as the site default.
			if ($result->num_rows() == 0)
			{
			  return FALSE;
			}
			
			// Since the first URI segment isn't a template group name,
			// could it be the name of a template in the default group?
			$this->EE->db->select('COUNT(*) as count');
			$this->EE->db->where('group_id', $result->row('group_id'));
			$this->EE->db->where('template_name', $this->EE->uri->segment(1));
			$query = $this->EE->db->get('templates');

			// We found a valid template!
			if ($query->row('count') == 1)
			{ 
				// Set the template group name from the prior query result (we
				// use the default template group name)
				$template_group	= $result->row('group_name');

				// Set the template name
				$template = $this->EE->uri->segment(1);

				// Re-assign the query string variable in the Input class so the
				// various tags can show the correct data
				if ($this->EE->uri->segment(2))
				{
					$this->EE->uri->query_string = trim_slashes(substr(
						$this->EE->uri->uri_string,
						strlen('/'.$this->EE->uri->segment(1))
					));
				}
				
				return array($template_group, $template);
        
			}
			// A valid template was not found. At this point we do not have
			// either a valid template group or a valid template name in the URL
			else
			{
			  return FALSE;
			}
		}

		// Fetch the template!
    return array($template_group, $template);
  }

  private function _template_exists($template_group='', $template_name='')
  { 
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
    $this->EE->functions->set_cookie('mobile_on', 'no', $this->_cookie_timeout);
  }
  
  private function _switch_to_mobile()
  {
    $this->EE->functions->set_cookie('mobile_on', 'yes', $this->_cookie_timeout);
  }
  
  private function _is_mobile()
  { 
    $agent = $_SERVER['HTTP_USER_AGENT'];
            
    $is_mobile = $this->EE->client->is_mobile($agent);

    $this->_prefix = isset($this->settings[$this->EE->client->mobile_client]) ? $this->settings[$this->EE->client->mobile_client] : '';      

    $this->_is_mobile = $is_mobile;
   
    if ($is_mobile === FALSE)
    {
      $this->_mobile_check = FALSE;
    }
    
  }
  // END _is_mobile
  
  private function _set_global_vars()
  {
    $this->EE->config->_global_vars['is_mobile'] = $this->_is_mobile;
    $this->EE->config->_global_vars['is_desktop'] = ! $this->_is_mobile;
    $this->EE->config->_global_vars['mobile_client'] = $this->EE->client->mobile_client;
    
    $this->EE->config->_global_vars['mobile:switch_to_full'] = $this->EE->functions->create_url('?MOBILE_ACT=STF');
    $this->EE->config->_global_vars['mobile:switch_to_mobile'] = $this->EE->functions->create_url('?MOBILE_ACT=STM');    
  }
  
  function _get_default_settings() {

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
      'sessions_start' => 'sessions_start',
      'core_template_route' => 'core_template_route'
    );

    // insert hooks and methods
    foreach ($hooks AS $hook => $method)
    {
      // data to insert
      $data = array(
        'class'		=> get_class($this),
        'method'	=> $method,
        'hook'		=> $hook,
        'priority'	=> 11,
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