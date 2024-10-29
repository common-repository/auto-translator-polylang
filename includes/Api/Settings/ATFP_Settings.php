<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Api\Settings;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

abstract class ATFP_Settings {
  protected array $optionGroups;
  protected array $settingsSections;
  protected array $settingsFields;

  abstract public function __construct ();

  public function registerSettings () {
    $this->registerOptionGroups();
    $this->registerSettingsSections();
    $this->registerSettingsFields();
  }

  private function registerOptionGroups () {
    foreach ($this->optionGroups as $optionGroup) {
      register_setting(
        $optionGroup['option_group'],
        $optionGroup['option_name'],
        $optionGroup['args']
      );
    }
  }

  private function registerSettingsSections () {
    foreach ($this->settingsSections as $settingsSection) {
      add_settings_section(
        $settingsSection['id'],
        $settingsSection['title'],
        $settingsSection['callback'],
        $settingsSection['page'],
        $settingsSection['args']
      );
    }
  }

  private function registerSettingsFields () {
    foreach ($this->settingsFields as $settingsField) {
      add_settings_field(
        $settingsField['id'],
        $settingsField['title'],
        $settingsField['callback'],
        $settingsField['page'],
        $settingsField['section'],
        $settingsField['args']
      );
    }
  }
}
