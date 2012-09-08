<?php

if (! defined('BW_MOBILE_VERSION'))
{
  define('BW_MOBILE_VERSION', '0.9.6');
  define('BW_MOBILE_NAME', 'Mobile');
  define('BW_MOBILE_DESCRIPTION', 'Serve up a different template if the site is visited from a mobile device.');
}

$config['name'] = BW_MOBILE_NAME;
$config['version'] = BW_MOBILE_VERSION;
$config['description'] = BW_MOBILE_DESCRIPTION;
$config['nsm_addon_updater']['versions_xml'] = '';