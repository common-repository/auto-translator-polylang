<?php

namespace ATFP_Includes\Base;

if (!defined('ABSPATH')) {
  exit; // Don't access directly.
}

use WP_List_Table;

class ATFP_TranslatedTable extends WP_List_Table
{
  private array $table_data = [];

  public function __construct($args = [])
  {
    parent::__construct($args);
  }

  function prepare_items()
  {
    $s         = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $post_type = isset($_GET['post-type']) ? sanitize_text_field($_GET['post-type']) : '';
    $language  = isset($_GET['language']) ? sanitize_text_field($_GET['language']) : '';
    $status    = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    $query_parameters = [
      's'         => $s,
      'post-type' => $post_type,
      'language'  => $language,
      'status'    => $status
    ];

    $this->table_data = $this->get_table_data($query_parameters);

    $this->_column_headers = [$this->get_columns(), [], false, 'title'];

    $this->set_pagination();

    $this->items = $this->table_data;
  }

  function get_columns()
  {
    return [
      'cb'         => '<input type="checkbox" />',
      'post_title' => esc_html__('Title', 'auto-translator-polylang'),
      'post_type'  => esc_html__('Post type', 'auto-translator-polylang'),
      'language'   => esc_html__('Language', 'auto-translator-polylang'),
      'status'     => esc_html__('Status', 'auto-translator-polylang'),
    ];
  }

  private function get_table_data($query_parameters)
  {
    global $wpdb;
    $pref = $wpdb->prefix;

    $query = "
  SELECT {$pref}posts.ID,
         {$pref}posts.post_title,
         {$pref}posts.post_type,
         {$pref}posts.post_status,
         {$pref}postmeta_language.meta_value as language,
         {$pref}postmeta_status.meta_value as status
  FROM {$pref}posts
  LEFT JOIN {$pref}postmeta as {$pref}postmeta_language 
      ON {$pref}postmeta_language.post_id = {$pref}posts.ID
          AND {$pref}postmeta_language.meta_key = 'atfp_poly_language'
  LEFT JOIN {$pref}postmeta as {$pref}postmeta_status
      ON {$pref}postmeta_status.post_id = {$pref}posts.ID
          AND {$pref}postmeta_status.meta_key = 'atfp_reviewed'
  WHERE {$pref}postmeta_language.meta_value IS NOT NULL
  AND {$pref}postmeta_status.meta_value IS NOT NULL
  AND ({$pref}posts.post_status = 'draft'
  OR {$pref}posts.post_status = 'publish')";

    if (!empty($query_parameters['s'])) {
      $query .= $wpdb->prepare(" AND {$pref}posts.post_title LIKE %s", '%' . esc_sql($query_parameters['s']) . '%');
    }

    if (!empty($query_parameters['post-type'])) {
      $query .= $wpdb->prepare(" AND {$pref}posts.post_type = %s", esc_sql($query_parameters['post-type']));
    }

    if (!empty($query_parameters['language'])) {
      $query .= $wpdb->prepare(" AND {$pref}postmeta_language.meta_value = %s", esc_sql($query_parameters['language']));
    }

    if (!empty($query_parameters['status'])) {
      $query .= $wpdb->prepare(" AND {$pref}postmeta_status.meta_value = %s", esc_sql($query_parameters['status']));
    }

    return $wpdb->get_results($query, ARRAY_A);
  }

  function extra_tablenav($which)
  {
    if ($which == 'top') {
      echo '<div class="alignleft actions">';
      $this->render_post_types_select();
      $this->render_languages_select();
      $this->render_status_select();
      submit_button('Filter', 'primary', 'filter_action', false);
      echo '</div>';
    }
  }

  function render_post_types_select()
  {
    $post_types       = $this->get_post_types();
    $default_selected = isset($_GET['post-type']) ? sanitize_text_field($_GET['post-type']) : '';

    echo "<select id='post-type' name='post_type'>";
    echo "<option value='' " . esc_attr($default_selected) . ">" . esc_html__('All post types', 'auto-translator-polylang') . "</option>";

    foreach ($post_types as $postType) {
      $selected = isset($_GET['post-type']) && sanitize_text_field($_GET['post-type']) == $postType->name ? 'selected' : '';

      echo "<option value='" . esc_attr(sanitize_text_field($postType->name)) . "' " . esc_attr($selected) . ">"
        . sprintf(
          esc_html__('%s', 'auto-translator-polylang') . "</option>",
          esc_html(sanitize_text_field(($postType->label)))
        );
    }

    echo "</select>";
  }

  function render_languages_select()
  {
    if (function_exists('pll_the_languages')) {
      global $wpdb;
      $pref = $wpdb->prefix;

      $query = "
        SELECT DISTINCT pm.meta_value as language
        FROM {$pref}postmeta pm
        WHERE pm.meta_key = 'atfp_poly_language'";

      $languages_results = $wpdb->get_results($query);

      $languages = pll_the_languages([
        'raw'           => true,
        'show_flags'    => 1,
        'hide_if_empty' => 0,
      ]);

      echo '<select id="language" name="language">';
      echo "<option value=''>" . esc_html__('All languages', 'auto-translator-polylang') . "</option>";

      foreach ($languages as $language) {
        $selected = (isset($_GET['language']) && sanitize_text_field($_GET['language']) === $language['slug']) ? 'selected' : '';

        foreach ($languages_results as $language_result) {
          if ($language['slug'] === $language_result->language) {
            echo '<option value="' . esc_attr($language['slug']) . '" ' . esc_attr($selected) . '>' .
              sprintf(
                esc_html__('%s', 'auto-translator-polylang'),
                esc_html($language['name'])
              )
              . '</option>';
          }
        }
      }

      echo '</select>';
    }
  }

  function render_status_select()
  {
    $statuses         = ['not_reviewed', 'reviewed'];
    $default_selected = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    echo "<select id='status' name='status'>";
    echo "<option value='' " . esc_attr($default_selected) . ">" . esc_html__('All statuses', 'auto-translator-polylang') . "</option>";

    foreach ($statuses as $status) {
      $status_name = $status == 'not_reviewed'
        ? esc_html__('Not Reviewed', 'auto-translator-polylang')
        : esc_html__('Reviewed', 'auto-translator-polylang');
      $selected    = isset($_GET['status']) && sanitize_text_field($_GET['status']) == $status ? 'selected' : '';

      echo "<option value='" . esc_attr($status) . "' " . esc_attr($selected) . ">" . sprintf(
          esc_html__('%s', 'auto-translator-polylang'),
          esc_html($status_name)
        ) . "</option>";
    }

    echo "</select>";
  }

  function get_post_types()
  {
    $post_types = get_post_types([
      'public'   => true,
      '_builtin' => false,
    ], 'objects');

    $default_post_types = get_post_types([
      'public'   => true,
      '_builtin' => true,
    ], 'objects');

    $post_types = array_merge($post_types, $default_post_types);

    unset($post_types['attachment']);

    return $post_types;
  }

  function column_default($item, $column_name)
  {
    switch ($column_name) {
      case 'post_title':
        return $this->column_post_title($item);
      case 'post_type':
        return $this->format_post_type($item);
      case 'language':
        return $this->format_language($item);
      case 'status':
        return $this->format_status($item);
      default:
        return $item[$column_name];
    }
  }

  function format_post_title($item)
  {
    $post_status = sanitize_text_field($item['post_status']) == 'draft'
      ? esc_html__('Draft', 'auto-translator-polylang')
      : esc_html__('Published', 'auto-translator-polylang');

    $title = sprintf(
      esc_html__('%1$s - %2$s', 'auto-translator-polylang'),
      esc_html(sanitize_text_field($item['post_title'])),
      esc_html($post_status)
    );

    $edit_link = get_edit_post_link(sanitize_text_field($item['ID']));

    return '<a href="' . esc_url($edit_link) . '"><strong>' . esc_html($title) . '</strong></a>';
  }

  function format_post_type($item)
  {
    $post_type_object = get_post_type_object(get_post_type(sanitize_text_field($item['ID'])));

    if ($post_type_object) {
      return $post_type_object->label;
    }

    return '';
  }

  function format_language($item)
  {
    if (function_exists('pll_the_languages')) {
      $languages = pll_the_languages([
        'raw'           => true,
        'hide_if_empty' => 0,
      ]);

      foreach ($languages as $language) {
        if ($language['slug'] == $item['language']) {
          return '<img src="' . esc_url($language['flag']) . '" alt="flag">';
        }
      }
    }
    return '';
  }

  function format_status($item)
  {
    return $item['status'] == 'not_reviewed'
      ? esc_html__('Not reviewed', 'auto-translator-polylang')
      : esc_html__('Reviewed', 'auto-translator-polylang');
  }

  function column_cb($item)
  {
    return sprintf(
      '<input type="checkbox" name="element[]" value="%s" />',
      esc_html($item['ID'])
    );
  }

  function set_pagination()
  {
    $per_page     = $this->get_items_per_page('elements_per_page');
    $current_page = $this->get_pagenum();
    $total_items  = count($this->table_data);

    $this->table_data = array_slice($this->table_data, (($current_page - 1) * $per_page), $per_page);

    $this->set_pagination_args([
      'total_items' => $total_items,
      'per_page'    => $per_page,
      'total_pages' => ceil($total_items / $per_page),
    ]);
  }

  function column_post_title($item)
  {
    $actionBaseURL = admin_url('admin-post.php?action=');
    $postID        = sanitize_text_field($item['ID']);

    $markAsReviewedNonce = wp_create_nonce('mark_as_reviewed_' . $postID);
    $markAsReviewedURL   = add_query_arg(
      [
        'action'   => 'mark_as_reviewed',
        'post_id'  => $postID,
        '_wpnonce' => $markAsReviewedNonce,
      ],
      $actionBaseURL
    );

    $markAsNotReviewedNonce = wp_create_nonce('mark_as_not_reviewed_' . $postID);
    $markAsNotReviewedURL   = add_query_arg(
      [
        'action'   => 'mark_as_not_reviewed',
        'post_id'  => $postID,
        '_wpnonce' => $markAsNotReviewedNonce,
      ],
      $actionBaseURL
    );

    $deleteNonce = wp_create_nonce('delete_' . $postID);
    $deleteURL   = add_query_arg(
      [
        'action'   => 'delete',
        'post_id'  => $postID,
        '_wpnonce' => $deleteNonce,
      ],
      $actionBaseURL
    );

    $actions = [];
    if ($item['status'] == 'reviewed') {
      $actions['mark_as_not_reviewed'] = '<a href="' . esc_url($markAsNotReviewedURL) . '">' . esc_html__('Mark as not reviewed', 'auto-translator-polylang') . '</a>';
    } else {
      $actions['mark_as_reviewed'] = '<a href="' . esc_url($markAsReviewedURL) . '">' . esc_html__('Mark as reviewed', 'auto-translator-polylang') . '</a>';
    }
    $actions['delete'] = '<a href="' . esc_url($deleteURL) . '">' . esc_html__('Delete', 'auto-translator-polylang') . '</a>';

    return sprintf(
      '%1$s %2$s',
      $this->format_post_title($item),
      $this->row_actions($actions)
    );
  }


  function get_bulk_actions()
  {
    return [
      'mark_as_reviewed'     => esc_html__('Mark as reviewed', 'auto-translator-polylang'),
      'mark_as_not_reviewed' => esc_html__('Mark as not reviewed', 'auto-translator-polylang'),
      'delete'               => esc_html__('Delete', 'auto-translator-polylang'),
    ];
  }
}
