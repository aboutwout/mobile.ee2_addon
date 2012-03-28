<?php

class Client
{
  /**
  * Available Mobile Clients
  *  http://www.zytrax.com/tech/web/mobile_ids.html
  * @var array
  */
  public $mobile_clients = array(
    'android',
    'blackberry',
    'playbook',
    'iphone',
    'ipod',
    'ipad',
    'psp',
    'sprint',
    'nokia',
    'panasonic',
    'windows ce',
    'windows phone',
    'nook',
    'kindle',
    'ppc',
    'opera mini',
    'midp',
    '240x320',
    'ipaq',
    'netfront',
    'portalmmm',
    'sharp',
    'sie-',
    'pie',
    'sonyericsson',
    'symbian',
    'benq',
    'mda',
    'mot-',
    'philips',
    'pocket pc',
    'sagem',
    'sgh-',
    'vodafone',
    'xda',
    'lge'
  );

  /**
  * Check if client is a mobile client
  * @param string $userAgent
  * @return boolean
  */
  public function is_mobile($agent)
  {
    $agent = strtolower($agent);
    foreach ($this->mobile_clients as $mobile_client)
    {
      if (strstr($agent, $mobile_client))
      {
        $this->mobile_client = $mobile_client;
        return TRUE;
      }
    }
    $this->mobile_client = '';
    return FALSE;
  }
  
}