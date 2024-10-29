<?php

if (!defined('ABSPATH')) {
  exit; // Don't access directly.
}

$nonce = wp_create_nonce('bulk_translate_taxonomies');

?>

<script>
  window.addEventListener('DOMContentLoaded', () => {
    const bulkActionsForm = document.getElementById('posts-filter');

    if (bulkActionsForm) {
      const nonceField = document.createElement('input');
      nonceField.type = 'hidden';
      nonceField.name = 'bulk_translate_nonce';
      nonceField.value = <?php echo wp_json_encode($nonce); ?>;

      bulkActionsForm.appendChild(nonceField);
    }
  });
</script>
