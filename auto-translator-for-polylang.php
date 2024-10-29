<?php
/**
 * @package AutoTranslatorForPolylang
 *
 * Plugin Name: Auto Translator for Polylang
 * Description: Plugin to translate your pages and posts working with Polylang
 * Version: 1.0.4
 * Author: UAPP GROUP
 * Author URI: https://uapp.group/
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License: GPLv3
 * Text Domain: auto-translator-polylang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

const ATFP_PLUGIN = __FILE__;

define('ATFP_PLUGIN_DIR', untrailingslashit(dirname(ATFP_PLUGIN)));

define('ATFP_PLUGIN_BASENAME', plugin_basename(ATFP_PLUGIN));

define('ATFP_PLUGIN_NAME', trim(dirname(ATFP_PLUGIN_BASENAME), '/'));

const ATFP_VERSION = '1.0.4';

const ATFP_TEMPLATES = ATFP_PLUGIN_DIR . "/templates";

if (file_exists(ATFP_PLUGIN_DIR . "/vendor/autoload.php")) {
  require_once ATFP_PLUGIN_DIR . "/vendor/autoload.php";
}

$active_plugins = get_option('active_plugins');

if (in_array('polylang/polylang.php', $active_plugins)) {
  if (class_exists("ATFP_Includes\\ATFP_Plugin")) {
    $plugin = new ATFP_Includes\ATFP_Plugin();
    $plugin->register_pages();
  }
} else {
  add_action('admin_notices', 'atfp_required_notice');
}

function atfp_required_notice () {
  deactivate_plugins(plugin_basename(ATFP_PLUGIN));

  printf(
    sprintf(
      '<div class="notice notice-error is-dismissible"><p><strong>"%1$s"</strong> requires <strong>"%2$s"</strong> plugin to be installed and activated.</p></div>',
      'Auto Translator for Polylang',
      'Polylang'
    )
  );

  echo '<style>#message { display: none; }</style>';
}

