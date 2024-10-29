<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

class ATFP_Plugin {
  public function __construct () {
    add_action('admin_menu', [$this, 'add_menu_point']);
  }

  public function register_pages (): void {
    foreach ($this->get_pages() as $class) {
      $pages = new $class();
      $pages->register();
    }
  }

  private function get_pages (): array {
    return [
      Pages\ATFP_SettingsPage::class,
      Pages\ATFP_TranslatedPage::class,
    ];
  }

  public function add_menu_point (): void {
    add_menu_page(
      'Auto Translator for Polylang',
      'Auto Translator for Polylang',
      'manage_options',
      'auto-translator-for-polylang',
      '',
      'dashicons-admin-site',
      70
    );
  }
}
