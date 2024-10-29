<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Pages;

if (!defined('ABSPATH')) {
  exit; // Don't access directly.
}

use ATFP_Includes\Api\Settings\ATFP_Settings;

abstract class ATFP_Page {
  public ATFP_Settings $settings;

  public function __construct (ATFP_Settings $settings) {
    $this->settings = $settings;

    if (strpos(sanitize_text_field($_SERVER['QUERY_STRING']), 'page=auto-translator-for-polylang') !== false) {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_settings']);
    }

    if (strpos(sanitize_text_field($_SERVER['QUERY_STRING']), 'page=auto-translated-for-polylang') !== false) {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_translated']);
    }

    if (strpos(sanitize_text_field($_SERVER['QUERY_STRING']), 'action=edit') !== false) {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_post_translation']);
    }

    add_action('admin_enqueue_scripts', [$this, 'enqueue_edit']);
  }

  public function registerSettings () {
    $this->settings->registerSettings();
  }

  public function enqueue_settings () {
    wp_enqueue_script('test_connection_script', plugins_url('/assets/js/testConnection.min.js', ATFP_PLUGIN));
    wp_enqueue_style('test_connection_style', plugins_url('/assets/css/testConnection.min.css', ATFP_PLUGIN));
  }

  public function enqueue_translated () {
    wp_enqueue_script('table_filters_script', plugins_url('/assets/js/tableFilters.min.js', ATFP_PLUGIN));
  }

  public function enqueue_post_translation () {
    wp_enqueue_script('edit_page_translate_form_script', plugins_url('/assets/js/editPageTranslateForm.min.js', ATFP_PLUGIN));
  }

  public function enqueue_edit ($hook_suffix) {
    if ($hook_suffix == 'edit.php' || $hook_suffix == 'edit-tags.php') {
      wp_enqueue_script('edit_language_select_script', plugins_url('/assets/js/targetLanguageSelect.min.js', ATFP_PLUGIN));
      wp_enqueue_script('loader-script', plugins_url('/assets/js/loader.min.js', ATFP_PLUGIN));
      wp_enqueue_style('main-styles', plugins_url('/assets/css/main.min.css', ATFP_PLUGIN));
      require_once ATFP_PLUGIN_DIR . '/includes/utils/ATFP_BulkTranslateNonce.php';
    }
  }

  abstract public function render_page ();

  abstract public function add_menu_point ();

  abstract public function register ();
}
