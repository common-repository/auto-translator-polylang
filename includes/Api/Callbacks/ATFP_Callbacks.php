<?php
/**
 * @package AutoTranslatorForPolylang
 */

namespace ATFP_Includes\Api\Callbacks;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

interface ATFP_Callbacks {
  public static function pageOptionGroup ($input);

  public static function pageSection ();

  public static function pageField ($options);
}
