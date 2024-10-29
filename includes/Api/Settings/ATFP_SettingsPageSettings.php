<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Api\Settings;


if ( !defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

use ATFP_Includes\Api\Callbacks\ATFP_SettingsPageCallbacks;

class ATFP_SettingsPageSettings extends ATFP_Settings {
  public function __construct () {
    $this->optionGroups = [
      [
        'option_group' => 'pl_option_group',
        'option_name'  => 'atfp_api_key',
        'args'         => '',
      ],
    ];

    $this->settingsSections = [
      [
        'id'       => 'pl_admin_section',
        'title'    => 'Settings',
        'callback' => [ATFP_SettingsPageCallbacks::class, 'pageSection'],
        'page'     => 'auto-translator-for-polylang',
        'args'     => '',
      ],
    ];

    $this->settingsFields = [
      [
        'id'       => 'images_links',
        'title'    => esc_html__('Goggle Translate API key', 'auto-translator-polylang'),
        'callback' => [ATFP_SettingsPageCallbacks::class, 'pageField'],
        'page'     => 'auto-translator-for-polylang',
        'section'  => 'pl_admin_section',
        'args'     => [
          'type'        => 'password',
          'name'        => 'atfp_api_key',
          'id'          => 'atfp_api_key',
          'label_for'   => 'atfp_api_key',
          'class'       => 'regular-text',
          'value'       => get_option("atfp_api_key") ?? "",
          'placeholder' => "Enter your API key",
        ],
      ],
    ];
  }
}
