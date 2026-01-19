<?php
/**
 * Opentape - Entry Point
 */

set_include_path(get_include_path() . PATH_SEPARATOR . "./code/");
require_once("opentape_common.php");

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    include("code/warning.php");
    exit;
}

// Check if userdata directories are set up and writable
$setup_status = get_setup_status();
if (!$setup_status['settings_writable'] || !$setup_status['songs_writable']) {
    include("code/warning.php");
    exit;
}

// If no password is set, show password creation screen
if (!is_password_set()) {
    include("code/welcome.php");
    exit;
}

// All good - show the mixtape
include("code/mixtape.php");
