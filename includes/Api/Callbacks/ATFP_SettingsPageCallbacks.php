<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Api\Callbacks;


if(!defined('ABSPATH')) {
	exit; // Don't access directly.
}

class ATFP_SettingsPageCallbacks implements ATFP_Callbacks {
	public static function pageOptionGroup($input) {
		return $input;
	}

	public static function pageSection() {
	}

	public static function pageField($options) {
		$output = '';

		if($options['type'] == 'textarea') {
			$output .= "<textarea ";
		} else {
			$output .= "<input ";
			$output .= "type='{$options['type']}' ";
		}

		if($options['name'] != '') {
			$output .= "name='{$options['name']}' ";
		}

		if($options['id'] != '') {
			$output .= "id='{$options['id']}' ";
		}

		if($options['class']) {
			$output .= "class='{$options['class']}' ";
		}

		if($options['placeholder']) {
			$output .= "placeholder='{$options['placeholder']}' ";
		}

		if($options['value']) {
			$output .= "value='{$options['value']}' ";
		}

		if($options['type'] == 'textarea') {
			$output .= "></textarea>";
		} else {
			$output .= "> ";
		};

		echo wp_kses($output, [
			'input'    => [
				'type'        => true,
				'name'        => true,
				'value'       => true,
				'placeholder' => true,
				'class'       => true,
				'id'          => true,
			],
			'textarea' => [
				'type'        => true,
				'name'        => true,
				'value'       => true,
				'placeholder' => true,
				'class'       => true,
				'id'          => true,
			],
		]);
	}
}
