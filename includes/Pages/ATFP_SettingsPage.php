<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Pages;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

use ATFP_Includes\Api\Callbacks\ATFP_SettingsPageCallbacks;
use ATFP_Includes\Api\Settings\ATFP_SettingsPageSettings;

class ATFP_SettingsPage extends ATFP_Page {
  public ATFP_SettingsPageCallbacks $callbacks;

  public function __construct () {
    parent::__construct(new ATFP_SettingsPageSettings());

    $this->callbacks = new ATFP_SettingsPageCallbacks();
  }

  public function register () {
    add_action('admin_menu', [$this, 'add_menu_point']);
    add_action('admin_init', [$this, 'registerSettings']);
  }

  public function add_menu_point () {
    add_submenu_page(
      'auto-translator-for-polylang',
      'Settings',
      'Settings',
      'manage_options',
      'auto-translator-for-polylang',
      [$this, 'render_page'],
      100
    );
  }

  public function render_page () {
    require_once ATFP_TEMPLATES . "/settings.php";
  }
}
