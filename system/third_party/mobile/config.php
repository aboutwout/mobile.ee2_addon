<?php if ( ! defined('EXT')) { exit('Invalid file request'); }

if ( ! defined('BW_MOBILE_VERSION'))
{
  define('BW_MOBILE_VERSION', '2.0');
  define('BW_MOBILE_NAME', 'Mobile');
  define('BW_MOBILE_DESCRIPTION', 'Serve up a different template if the site is visited from a mobile device.');
  define('BW_MOBILE_DOCS', 'http://www.baseworks.nl/addons/mobile');
  define('BW_MOBILE_SETTINGS_EXIST', 'y');
}

$config['name'] = BW_MOBILE_NAME;
$config['version'] = BW_MOBILE_VERSION;
$config['description'] = BW_MOBILE_DESCRIPTION;
$config['nsm_addon_updater']['versions_xml'] = '';