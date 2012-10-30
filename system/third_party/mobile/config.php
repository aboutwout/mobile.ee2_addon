<?php

if (! defined('BW_MOBILE_VERSION'))
{
  define('BW_MOBILE_VERSION', '1.0.3');
  define('BW_MOBILE_NAME', 'Mobile (for v2.4.0+)');
  define('BW_MOBILE_DESCRIPTION', 'Serve up a different template if the site is visited from a mobile device.');
}

$config['name'] = BW_MOBILE_NAME;
$config['version'] = BW_MOBILE_VERSION;
$config['description'] = BW_MOBILE_DESCRIPTION;
$config['nsm_addon_updater']['versions_xml'] = '';