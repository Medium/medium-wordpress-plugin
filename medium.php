<?php
/**
 * @package Medium
 * @version 1.4.0
 */
/*
Plugin Name: Medium
Description: Publish posts automatically from your blog to a Medium profile.
Version: 1.4.0
Author: A Medium Corporation
Author URI: https://medium.com
License: Apache
Text Domain: medium
Domain Path: /languages
*/

// Disallow direct access
if (!function_exists("add_action")) {
  echo "Don't call me, I'll call you.";
  exit;
}

define("MEDIUM_VERSION", "1.4.0");
define("MEDIUM_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("MEDIUM_PLUGIN_URL", plugin_dir_url(__FILE__));

if (is_admin()) {
  require_once(MEDIUM_PLUGIN_DIR . "lib/medium-admin.php");
  add_action("init", array("Medium_Admin", "init"));
} else {
  require_once(MEDIUM_PLUGIN_DIR . "lib/medium-site.php");
  add_action("init", array("Medium_Site", "init"));
}
