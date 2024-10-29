<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Pages;

if (!defined('ABSPATH')) {
  exit; // Don't access directly.
}

use ATFP_Includes\Api\Callbacks\ATFP_SettingsPageCallbacks;
use ATFP_Includes\Api\Settings\ATFP_TranslatedPagesSettings;
use ATFP_Includes\Base\ATFP_TranslatedTable;
use ATFP_Includes\Base\ATFP_Translation;

class ATFP_TranslatedPage extends ATFP_Page {
  public ATFP_SettingsPageCallbacks $callbacks;
  private ATFP_Translation $translation;

  public function __construct () {
    parent::__construct(new ATFP_TranslatedPagesSettings());

    $this->translation = new ATFP_Translation();

    add_action('admin_post_mark_as_reviewed', [$this, 'mark_as_reviewed_callback']);
    add_action('admin_post_mark_as_not_reviewed', [$this, 'mark_as_reviewed_callback']);
    add_action('admin_post_delete', [$this, 'delete_callback']);
    add_action('admin_post_generate_translated_page', [$this, 'generate_translated_page']);
    add_action('admin_post_translate_taxonomy', [$this, 'translate_term']);
    add_action('admin_init', [$this, 'process_table_bulk_actions']);

    if (get_option('atfp_translated_single_post')) {
      add_action('admin_notices', [$this, 'single_translate_post_notice']);
      delete_option('atfp_translated_single_post');
    }

    if (get_option('atfp_translated_single_term')) {
      add_action('admin_notices', [$this, 'single_translate_term_notice']);
      delete_option('atfp_translated_single_term');
    }
  }

  public function render_page () {
    $this->poly_translator_list_init();
  }

  public function add_menu_point () {
    add_submenu_page(
      'auto-translator-for-polylang',
      'Translated entities',
      'Translated entities',
      'manage_options',
      'auto-translated-for-polylang',
      [$this, 'render_page'],
      0
    );
  }

  public function register () {
    add_action('admin_menu', [$this, 'add_menu_point']);
  }

  private function poly_translator_list_init () {
    echo '<div class="wrap"><h2>' . esc_html__('Translated Entities', 'auto-translator-polylang') . '</h2>';
    echo '<form id="filter" method="get">';

    $table = new ATFP_TranslatedTable();
    $table->prepare_items();
    $table->search_box('Search', 'search_id');
    $table->display();

    echo '</div></form>';
  }

  public function mark_as_reviewed_callback () {
    if (
      isset($_GET['action'])
      && ($_GET['action'] == 'mark_as_reviewed' || $_GET['action'] == 'mark_as_not_reviewed')
      && isset($_GET['post_id'])
      && sanitize_text_field(is_numeric($_GET['post_id']))
      && isset($_GET['_wpnonce'])
      && (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mark_as_reviewed_' . sanitize_text_field($_GET['post_id']))
        || wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mark_as_not_reviewed_' . sanitize_text_field($_GET['post_id'])))
      && current_user_can('edit_post', sanitize_text_field($_GET['post_id']))
    ) {
      $post_id         = sanitize_text_field((int)($_GET['post_id']));
      $post            = get_post($post_id);
      $reviewed_status = sanitize_text_field($_GET['action']) == 'mark_as_reviewed' ? 'reviewed' : 'not_reviewed';
      $post_status     = sanitize_text_field($_GET['action']) == 'mark_as_reviewed' ? 'publish' : 'draft';

      if ($post) {
        update_post_meta($post_id, 'atfp_reviewed', $reviewed_status);
        $post->post_status = $post_status;
        wp_update_post($post);
      }
    }

    wp_safe_redirect(wp_get_referer());
    exit;
  }

  public function generate_translated_page () {
    if (
      isset($_GET['post_id'])
      && isset($_GET['target_language'])
      && sanitize_text_field(is_numeric($_GET['post_id']))
      && current_user_can('manage_options')
      && isset($_GET['_wpnonce'])
      && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'generate_translated_page_' . sanitize_text_field($_GET['post_id']) . '_' . sanitize_text_field($_GET['target_language']))
    ) {
      $post_id         = sanitize_text_field((int)($_GET['post_id']));
      $target_language = sanitize_text_field($_GET['target_language']);

      add_option('atfp_translated_single_post', 1);
      $this->translation->generate_translated_post($post_id, $target_language);
    }

    wp_safe_redirect(wp_get_referer());
    exit;
  }

  public function translate_term () {
    if (
      isset($_GET['target_language'])
      && isset($_GET['term_id'])
      && isset($_GET['taxonomy'])
      && current_user_can('manage_options')
      && isset($_GET['_wpnonce'])
      && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'translate_taxonomy_' . sanitize_text_field($_GET['term_id']) . '_' . sanitize_text_field($_GET['target_language']))
    ) {
      $taxonomy = sanitize_text_field($_GET['taxonomy']);

      add_option('atfp_translated_single_term', 1);
      $this->translation->handle_create_single_term();
      wp_redirect(admin_url('edit-tags.php?taxonomy=' . esc_url($taxonomy)));
      exit;
    }

    wp_safe_redirect(wp_get_referer());
    exit;
  }

  public function delete_callback () {
    if (
      isset($_GET['action'])
      && sanitize_text_field($_GET['action']) == 'delete'
      && isset($_GET['post_id'])
      && current_user_can('manage_options')
      && isset($_GET['_wpnonce'])
      && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_' . sanitize_text_field($_GET['post_id']))
    ) {
      $post_id = sanitize_text_field((int)($_GET['post_id']));

      delete_post_meta($post_id, 'atfp_reviewed');
    }

    wp_safe_redirect(wp_get_referer());
    exit;
  }


  public function single_translate_post_notice () {
    printf('<div id="message" class="updated notice is-dismissable"><p>' . esc_html__('Post translated', 'auto-translator-polylang')
      . '</p></div>');
  }

  public function single_translate_term_notice () {
    printf('<div id="message" class="updated notice is-dismissable"><p>' . esc_html__('Term translated', 'auto-translator-polylang')
      . '</p></div>');
  }

  public function process_table_bulk_actions () {
    if (isset($_GET['action']) && current_user_can('manage_options') && isset($_GET['element'])) {

      if (sanitize_text_field($_GET['action']) == 'mark_as_reviewed' || sanitize_text_field($_GET['action']) == 'mark_as_not_reviewed') {
        $elements        = array_map('sanitize_text_field', $_GET['element']);
        $reviewed_status = sanitize_text_field($_GET['action']) == 'mark_as_reviewed' ? 'reviewed' : 'not_reviewed';
        $post_status     = sanitize_text_field($_GET['action']) == 'mark_as_reviewed' ? 'publish' : 'draft';

        foreach ($elements as $post_id) {
          $post_id = intval($post_id);
          $post    = get_post($post_id);
          if ($post) {
            $post->post_status = $post_status;
            wp_update_post($post);
            update_post_meta($post_id, 'atfp_reviewed', $reviewed_status);
          }
        }

        wp_safe_redirect(wp_get_referer());
        exit;
      }

      if (sanitize_text_field($_GET['action']) == 'delete') {
        $elements = array_map('sanitize_text_field', $_GET['element']);

        foreach ($elements as $post_id) {
          $post_id = intval($post_id);
          delete_post_meta($post_id, 'atfp_reviewed');
        }

        wp_safe_redirect(wp_get_referer());
        exit;
      }
    }
  }
}


