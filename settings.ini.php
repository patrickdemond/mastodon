<?php
/**
 * settings.ini.php
 * 
 * Defines initialization settings for mastodon.
 * DO NOT edit this file, to override these settings use settings.local.ini.php instead.
 * Any changes in the local ini file will override the settings found here.
 */

global $SETTINGS;

// tagged version
$SETTINGS['general']['application_name'] = 'mastodon';
$SETTINGS['general']['instance_name'] = $SETTINGS['general']['application_name'];
$SETTINGS['general']['version'] = '2.0.0';
$SETTINGS['general']['build'] = 'f7d1210';

// always leave as false when running as production server
$SETTINGS['general']['development_mode'] = false;

// the location of mastodon internal path
$SETTINGS['path']['APPLICATION'] = str_replace( '/settings.ini.php', '', __FILE__ );

// the location of new forms which need to be processed
$SETTINGS['path']['FORM_IN'] = $SETTINGS['path']['APPLICATION'].'/doc/form_in';

// the location of new forms which have been processed but not transferred to form system yet
$SETTINGS['path']['FORM_OUT'] = $SETTINGS['path']['APPLICATION'].'/doc/form_out';
