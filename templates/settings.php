<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

?>

<div class="settings">
  <?php settings_errors(); ?>
  <form method="post" action="options.php">
    <?php
    settings_fields('pl_option_group');
    do_settings_sections('auto-translator-for-polylang');
    ?>
    <p><a target="_blank" href="https://cloud.google.com/translate/docs/setup">Google Translate API docs</a></p>
    <div class="flex">
      <button type="button" id="test_api_key" class="button button-primary">Test connection</button>

      <div class="flex">
        <svg id="loader" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px"
             width="30px" height="30px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;"
             xml:space="preserve">
            <path fill="#000"
                  d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z">
              <animateTransform attributeType="xml"
                                attributeName="transform"
                                type="rotate"
                                from="0 25 25"
                                to="360 25 25"
                                dur="0.6s"
                                repeatCount="indefinite"/>
            </path>
          </svg>

        <p id="connection-successful">Connection successful</p>
        <p id="connection-error"></p>
      </div>
    </div>

    <?php submit_button(); ?>
  </form>
</div>
