<?php

namespace ATFP_Includes\Base;

if (!defined('ABSPATH')) {
  exit; // Don't access directly.
}

use ATFP_simple_html_dom;

class ATFP_Translation {
  public function __construct () {
    $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
    $taxonomy  = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';

    add_filter('post_row_actions', [$this, 'posts_row_actions'], 10, 2);
    add_filter('page_row_actions', [$this, 'posts_row_actions'], 10, 2);

    add_filter('bulk_actions-edit-' . $post_type, [$this, 'add_bulk_action']);
    add_filter('bulk_actions-edit-post', [$this, 'add_bulk_action']);
    add_filter('handle_bulk_actions-edit-' . $post_type, [$this, 'process_bulk_translate_posts'], 10, 3);

    add_filter('bulk_actions-edit-' . $taxonomy, [$this, 'add_bulk_action']);
    add_filter('handle_bulk_actions-edit-' . $taxonomy, [$this, 'process_bulk_translate_taxonomies'], 10, 3);

    add_filter('category_row_actions', [$this, 'terms_row_actions'], 10, 2);
    add_filter('tag_row_actions', [$this, 'terms_row_actions'], 10, 2);

    add_action('restrict_manage_posts', [$this, 'add_custom_select_to_posts_page']);

    if (get_option('atfp_response_error')) {
      add_action('admin_notices', [$this, 'error_notice']);
    }

    if (get_option('atfp_translated_entities')) {
      add_action('admin_notices', [$this, 'bulk_translate_posts_notice']);
    }

    if (get_option('atfp_translated_taxonomies')) {
      add_action('admin_notices', [$this, 'bulk_translate_terms_notice']);
    }

    add_action('admin_init', [$this, 'translate_post_from_edit']);
  }

  public function generate_translated_post ($post_id, $target_language) {
    $new_post_data = $this->handle_post_data($post_id, $target_language);

    if (get_option('atfp_response_error')) {
      return '';
    }

    $new_post_id = wp_insert_post($new_post_data);

    $this->handle_insert_data_with_new_post_id($post_id, $new_post_id, $target_language);

    wp_set_post_categories($new_post_id, wp_get_post_categories($post_id));
    wp_set_post_tags($new_post_id, wp_list_pluck(wp_get_post_tags($post_id), 'term_id'));

    $this->add_post_meta($new_post_id, $target_language);

    return $new_post_id;
  }

  private function handle_post_data ($post_id, $target_language) {
    $original_post = get_post($post_id);

    $translated_content = $this->get_text_from_html($original_post->post_content, $target_language);
    $translated_title   = $this->translate_text($original_post->post_title, $target_language);
    $translated_excerpt = $this->translate_text($original_post->post_excerpt, $target_language);

    $original_post_attributes = get_object_vars($original_post);

    $excluded_attributes = ['ID', 'guid', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_name', 'post_parent'];

    $new_post_attributes = array_diff_key($original_post_attributes, array_flip($excluded_attributes));

    return array_merge($new_post_attributes, [
      'post_title'   => $translated_title,
      'post_content' => $translated_content,
      'post_status'  => 'draft',
      'post_excerpt' => $translated_excerpt,
    ]);
  }

  private function handle_insert_data_with_new_post_id ($post_id, $new_post_id, $target_language) {
    $original_post_thumbnail = get_post_thumbnail_id($post_id);

    if ($original_post_thumbnail) {
      set_post_thumbnail($new_post_id, $original_post_thumbnail);
    }

    if (function_exists('pll_set_post_language')) {
      pll_set_post_language($new_post_id, $target_language);

      pll_save_post_translations(array_merge(pll_get_post_translations($post_id), [
        $target_language => $new_post_id,
      ]));
    }

    $this->translate_acf_fields($post_id, $new_post_id, $target_language);

    $categories_relations = $this->build_categories_relations(wp_get_post_categories($post_id));

    $this->handle_determine_parent_category($categories_relations, $target_language);

    if (!$categories_relations) {
      $this->handle_translate_categories(get_the_category($post_id), $target_language);
    }

    $this->handle_translate_tags(get_the_tags($post_id), $target_language);

    if (is_plugin_active('wordpress-seo/wp-seo.php')) {
      $this->translate_yoast_fields($post_id, $new_post_id, $target_language);
    }
  }

  private function add_post_meta ($new_post_id, $target_language) {
    if (!get_post_meta($new_post_id, 'atfp_poly_language')) {
      add_post_meta($new_post_id, 'atfp_poly_language', $target_language);
      add_post_meta($new_post_id, 'atfp_reviewed', 'not_reviewed');
    }
  }

  private function build_categories_relations ($categories) {
    $categories_relations = [];

    foreach ($categories as $category) {
      $category_object = get_category($category);
      $parent_id       = $category_object->category_parent;

      if ($parent_id) {
        $categories_relations[$parent_id][] = $category_object->term_id;
      }
    }

    return $categories_relations;
  }

  private function handle_determine_parent_category ($categories, $target_language) {
    foreach ($categories as $parent_id => $child_categories) {

      if (!pll_get_term($parent_id, $target_language)) {
        $category_object = get_category($parent_id);
        $new_parent_id   = $this->handle_create_term_translation($category_object, $target_language, pll_get_term($category_object->category_parent, $target_language));

        foreach ($child_categories as $child_category) {
          if (
            !array_key_exists($child_category, $categories)
            && !pll_get_term($child_category, $target_language)
          ) {
            $category_object = get_category($child_category);
            $this->handle_create_term_translation($category_object, $target_language, $new_parent_id);
          }
        }
      }
    }
  }

  private function handle_create_term_translation ($term_object, $target_language, $parent_id = 0) {
    $translated_term_name        = $this->translate_text($term_object->name, $target_language);
    $translated_term_description = $this->translate_text($term_object->description, $target_language);

    $term_data = [
      'name'        => $translated_term_name,
      'description' => $translated_term_description,
      'taxonomy'    => $term_object->taxonomy,
      'parent'      => $parent_id,
    ];

    $new_term = wp_insert_term($translated_term_name, $term_object->taxonomy, $term_data);

    if (!is_wp_error($new_term)) {
      $new_term_id = $new_term['term_id'];
      pll_set_term_language($new_term_id, $target_language);

      $term_translations = array_merge(
        pll_get_term_translations($term_object->term_id),
        [$target_language => $new_term_id]
      );

      pll_save_term_translations($term_translations);

      return $new_term_id;
    }

    return null;
  }

  private function handle_translate_tags ($tags, $target_language) {
    if (!$tags) {
      return;
    }

    foreach ($tags as $tag) {
      if (!pll_get_term($tag->term_id, $target_language)) {
        $this->handle_create_term_translation($tag, $target_language);
      }
    }
  }

  private function handle_translate_categories ($categories, $target_language) {
    if (!$categories) {
      return;
    }

    foreach ($categories as $category) {
      if (!pll_get_term($category, $target_language)) {
        $this->handle_create_term_translation($category, $target_language);
      }
    }
  }

  private function get_taxonomy_object_with_parent ($taxonomy_id, $language) {
    $taxonomy_object = get_term($taxonomy_id);
    $parent_id       = pll_get_term($taxonomy_object->parent, $language);
    return $this->handle_create_term_translation($taxonomy_object, $language, $parent_id);
  }

  private function translate_yoast_fields ($post_id, $new_post_id, $target_language) {
    global $wpdb;
    $pref                  = $wpdb->prefix;
    $postmeta_prefix       = '_yoast_wpseo_';
    $original_yoast_fields = ['title', 'description', 'primary_focus_keyword', 'open_graph_title', 'open_graph_description', 'twitter_title', 'twitter_description',];
    $postmeta_fields       = ['title', 'metadesc', 'focuskw', 'opengraph-title', 'opengraph-description', 'twitter-title', 'twitter-description'];

    foreach ($original_yoast_fields as $originalYoastField) {
      $original_field = $wpdb->get_var($wpdb->prepare("SELECT %i FROM {$pref}yoast_indexable WHERE `object_id` = %d", esc_sql($originalYoastField), $post_id));

      preg_match_all('/%%(.*?)%%/', $original_field, $matches);

      $placeholders = [];
      foreach ($matches[0] as $index => $variable) {
        $placeholder                = '%VAR_' . $index . '%';
        $original_field             = str_replace($variable, $placeholder, $original_field);
        $placeholders[$placeholder] = $variable;
      }

      $translated_field = $this->translate_text($original_field, $target_language);
      $translated_field = str_replace(array_keys($placeholders), array_values($placeholders), $translated_field);

      $translated_fields[] = $translated_field;

      $wpdb->update(
        "{$pref}yoast_indexable",
        [$originalYoastField => $translated_field],
        ['object_id' => $new_post_id]
      );
    }

    foreach ($postmeta_fields as $index => $postmetaField) {
      $wpdb->insert(
        "{$pref}postmeta",
        [
          'post_id'    => $new_post_id,
          'meta_key'   => $postmeta_prefix . $postmetaField,
          'meta_value' => $translated_fields[$index],
        ],
      );
    }
  }

  public function handle_create_single_term () {
    if (isset($_GET['target_language']) && isset($_GET['term_id']) && isset($_GET['taxonomy'])) {
      $this->get_taxonomy_object_with_parent(sanitize_text_field($_GET['term_id']), sanitize_text_field($_GET['target_language']));
      wp_redirect(admin_url('edit-tags.php?taxonomy=' . sanitize_text_field($_GET['taxonomy'])));
    }
  }

  public function translate_post_from_edit () {
    if (isset($_GET['language_select']) && isset($_GET['post'])) {
      $new_page_id = $this->generate_translated_post(sanitize_text_field($_GET['post']), sanitize_text_field($_GET['language_select']));

      if (!get_option('atfp_response_error')) {
        wp_redirect(admin_url('post.php?post=' . esc_url($new_page_id) . '&action=edit'));
        exit;
      }
    }
  }

  public function process_bulk_translate_posts ($redirect_to, $doaction, $bulklinks) {
    if ($doaction == 'translate') {
      $translated_entities = [];
      $target_language     = isset($_GET['bulk_language_select']) ? sanitize_text_field($_GET['bulk_language_select']) : '';

      foreach ($bulklinks as $post_id) {
        if (
          pll_get_post_language($post_id) != $target_language
          && !pll_get_post($post_id, $target_language) && !get_option('atfp_response_error')
        ) {
          $translated_entities[] = $this->generate_translated_post($post_id, $target_language);
        }
      }

      $translated_entities_length = count($translated_entities);

      if ($translated_entities_length) {
        add_option('atfp_translated_entities', $translated_entities_length);
      }
    }

    wp_redirect($redirect_to);
    exit;
  }

  public function process_bulk_translate_taxonomies ($redirect_to, $doaction, $bulklinks) {
    if (!isset($_POST['bulk_translate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bulk_translate_nonce'])), 'bulk_translate_taxonomies') || !current_user_can('edit_posts')) {
      wp_die('Security check failed');
    }

    $language              = get_term(sanitize_text_field($_POST['bulk_language_select']))->slug ?? '';
    $post_type             = sanitize_text_field($_POST['post_type']) ?? '';
    $translated_taxonomies = [];

    if ($doaction == 'translate') {
      foreach ($bulklinks as $taxonomy_id) {
        if (pll_get_term_language($taxonomy_id) != $language && !pll_get_term($taxonomy_id, $language)) {
          $translated_taxonomies[] = $this->get_taxonomy_object_with_parent($taxonomy_id, $language);
        }
      }
    }

    if ($translated_taxonomies) {
      add_option('atfp_translated_taxonomies', $translated_taxonomies);
    }

    wp_redirect(admin_url("edit-tags.php?taxonomy=" . esc_attr(sanitize_text_field($_GET['taxonomy'])) . '&post_type=' . esc_attr(sanitize_text_field($post_type))));
    exit;
  }

  public function posts_row_actions ($actions, $post) {
    $languages   = pll_the_languages(['raw' => true, 'hide_if_empty' => false]);
    $action_link = '<p class="translate-to">' . esc_html__('Translate', 'auto-translator-polylang') . ':';
    $post_type   = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
    $flag        = false;

    if ($post_type !== 'acf-field-group') {
      foreach ($languages as $language) {
        if (!pll_get_post($post->ID, $language['slug'])) {
          $flag         = true;
          $nonce        = wp_create_nonce('generate_translated_page_' . $post->ID . '_' . $language['slug']);
          $translateURL = add_query_arg(
            [
              'action'          => 'generate_translated_page',
              'post_id'         => $post->ID,
              'target_language' => $language['slug'],
              '_wpnonce'        => $nonce,
            ],
            admin_url('admin-post.php')
          );

          $action_link .= '<a class="translate-link" href="' . esc_url($translateURL) . '">'
            . '<img class="translate-flag" src="' . esc_url($language['flag']) . '" alt="flag">'
            . '</a>';
        }
      }
    }

    $action_link .= '</p>';

    if ($flag) {
      $actions['generate_translated_page'] = $action_link;
    }

    return $actions;
  }

  public function terms_row_actions ($actions, $tag) {
    $languages   = pll_the_languages(['raw' => true, 'hide_if_empty' => false]);
    $action_link = '<p class="translate-to">' . esc_html__('Translate', 'auto-translator-polylang') . ':';
    $taxonomy    = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';
    $flag        = false;

    foreach ($languages as $language) {
      if (!pll_get_term($tag->term_id, $language['slug'])) {
        $flag         = true;
        $nonce        = wp_create_nonce('translate_taxonomy_' . $tag->term_id . '_' . $language['slug']);
        $translateURL = add_query_arg(
          [
            'taxonomy'        => $taxonomy,
            'action'          => 'translate_taxonomy',
            'term_id'         => $tag->term_id,
            'target_language' => $language['slug'],
            '_wpnonce'        => $nonce,
          ],
          admin_url('admin-post.php')
        );

        $action_link .= '<a class="translate-link" href="' . esc_url($translateURL) . '">'
          . '<img class="translate-flag" src="' . esc_url($language['flag']) . '" alt="flag">'
          . '</a>';
      }
    }

    $action_link .= '</p>';

    if ($flag) {
      $actions['translate'] = $action_link;
    }

    return $actions;
  }


  public function add_custom_select_to_posts_page () {
    $languages = pll_the_languages([
      'raw'           => true,
      'hide_if_empty' => false,
    ]);

    echo "<select style='display: none' id='language' name='language'>";

    foreach ($languages as $language) {
      $slug = $language['slug'];
      $name = $language['name'];

      echo "<option value='" . esc_attr($slug) . "'>" . sprintf(esc_html__('%s', 'auto-translator-polylang'), esc_html($name)) . "</option>";
    }

    echo "</select>";
  }

  private function get_text_from_html ($html, $target_language) {
    include_once('simplehtmldom/simple_html_dom.php');
    $dom = new ATFP_simple_html_dom();
    $dom->load(wp_kses_post($html));

    foreach ($dom->find('text') as $textNode) {
      $trimmedInnerText = trim($textNode->innertext);

      if (!empty($trimmedInnerText) && $trimmedInnerText !== '</p>') {
        $translated_text = $this->translate_text($trimmedInnerText, $target_language);

        if ($translated_text) {
          $newNode             = $dom->createTextNode($translated_text);
          $textNode->innertext = $newNode->innertext;
        }
      }
    }

    return $dom->save();
  }

  private function translate_text ($text, $targetLanguage) {
    $api_key      = get_option('atfp_api_key');
    $url          = 'https://translation.googleapis.com/language/translate/v2';
    $query_params = "?q=" . esc_html(urlencode($text)) . "&target=" . esc_html($targetLanguage) . "&format=html&key=" . esc_html($api_key) . " ";

    $response = wp_remote_post($url . $query_params);

    if (is_wp_error($response)) {
      add_option('atfp_response_error', sprintf(
        esc_html__('%s', 'auto-translator-polylang'),
        esc_html($response->get_error_message())
      ) ?: esc_html__('Something went wrong', 'auto-translator-polylang'));
      return 'atfp_response_error';
    }

    $decoded = json_decode($response['body']);

    if (isset($decoded->data->translations) && is_array($decoded->data->translations)) {
      return $decoded->data->translations[0]->translatedText;
    } else {
      add_option('atfp_response_error', sprintf(
        esc_html__('%s', 'auto-translator-polylang'),
        esc_html($decoded->error->message)
      ) ?: esc_html__('Something went wrong', 'auto-translator-polylang'));
    }

    return '';
  }

  private function translate_acf_fields ($post_id, $new_page_id, $target_language) {
    if (function_exists('get_fields')) {
      $fields = get_fields($post_id);

      foreach ($fields as $field_name => $field_value) {
        if (is_string($field_value)) {
          $translated_field_value = $this->get_text_from_html($field_value, $target_language);
          update_field($field_name, $translated_field_value, $new_page_id);
        } elseif (is_array($field_value)) {
          $translated_array = $this->translate_array_acf_fields($field_value, $target_language);
          update_field($field_name, $translated_array, $new_page_id);
        } else {
          update_field($field_name, $field_value, $new_page_id);
        }
      }
    }
  }

  private function translate_array_acf_fields ($array, $target_language) {
    $translated_array = [];

    foreach ($array as $key => $value) {
      if (is_string($value)) {
        $translated_array[$key] = $this->get_text_from_html($value, $target_language);
      } elseif (is_array($value)) {
        $translated_array[$key] = $this->translate_array_acf_fields($value, $target_language);
      } else {
        $translated_array[$key] = $value;
      }
    }

    return $translated_array;
  }

  public function add_bulk_action ($bulk_actions) {
    $bulk_actions['translate'] = 'Translate';
    return $bulk_actions;
  }

  public function error_notice () {
    $error = get_option('atfp_response_error');
    echo "<div class='notice notice-error is-dismissible' ><p>" . sprintf(esc_html__('%s', 'auto-translator-polylang'), esc_html($error)) . "</p></div> ";
    delete_option('atfp_response_error');
  }

  public function bulk_translate_posts_notice () {
    $num_changed = get_option('atfp_translated_entities');
    sprintf('<div id="message" class="updated notice is - dismissable"><p>' . esc_html__('Translated %d posts.', 'auto-translator-polylang')
      . '</p></div>', esc_html(count($num_changed)));

    delete_option('atfp_translated_entities');
  }

  public function bulk_translate_terms_notice () {
    $num_changed = get_option('atfp_translated_taxonomies');
    sprintf('<div id="message" class="updated notice is - dismissable"><p>' . esc_html__('Translated %d terms.', 'auto-translator-polylang')
      . '</p></div>', esc_html(count($num_changed)));

    delete_option('atfp_translated_taxonomies');
  }
}
