<?php
/**
 * Serialization logic for Algolia records
 */

define('THEME_PATH', get_stylesheet_directory() . '/');

class AlgoliaSerializer {
    /**
     * Runs initialization tasks.
     *
     * @return void
     */
    public function run() {
        // Bail early if Algolia plugin is not activated.
        if (!function_exists('get_algolia_index_name')) {
            return;
        }

        add_filter('timber/context', array($this, 'add_config_to_context'));

        add_filter('page_to_record', array($this, 'algolia_page_to_record'));
        add_filter('post_to_record', array($this, 'algolia_post_to_record'));
        // Demo filter for for custom post type
        add_filter('monkey_to_record', array($this, 'algolia_monkey_to_record'));

        add_filter('algolia_get_settings', array($this, 'algolia_get_settings'));
        add_filter('algolia_get_synonyms', array($this, 'algolia_get_synonyms'));
        add_filter('algolia_get_rules', array($this, 'algolia_get_rules'));

        add_filter('algolia_write_settings', array($this, 'algolia_write_settings'), 10, 2);
        add_filter('algolia_write_synonyms', array($this, 'algolia_write_synonyms'), 10, 2);
        add_filter('algolia_write_rules', array($this, 'algolia_write_rules'), 10, 2);
    }

    /**
     * Pass Algolia environment variables to Timber context
     *
     * @param array $context Timber context
     *
     * @return array
     */
    function add_config_to_context($context) {
        $context['ALGOLIA_APPLICATION_ID'] = getenv('ALGOLIA_APPLICATION_ID');
        $context['ALGOLIA_SEARCH_ONLY_API_KEY'] = getenv('ALGOLIA_SEARCH_ONLY_API_KEY');
        $context['ALGOLIA_INDEX_PREFIX'] = get_algolia_index_name();
        return $context;
    }

    /**
     * Get default attributes for each Algolia record
     *
     * @param object $post    Post to get record of
     * @param object $blog_id ID of current blog
     *
     * @return array
     */
    function getDefaultRecordAttributes($post, $blog_id) {
        return [
            'objectID' => implode('#', [$blog_id, $post->post_type, $post->ID]),
            'distinct_key' => implode('#', [$blog_id, $post->post_type, $post->ID]),
            'blog_id' => $blog_id,
            'type' => $post->post_type,
            'title' => $post->post_title,
            'date' => $post->post_date,
            'url' => get_permalink($post->ID),
        ];
    }

    /**
     * Maps the given post taxonomy terms to the term names
     *
     * @param object $post     Post to get the terms of
     * @param string $taxonomy Name of taxonomy
     *
     * @return array
     */
    function getTermNames($post, $taxonomy) {
        $terms = wp_get_post_terms($post->ID, $taxonomy);

        if (!is_array($terms)) {
            return [];
        }

        return array_map(
            function ($term) {
                return $term->name;
            },
            $terms
        );
    }

    /**
     * Split the content into separate records
     *
     * @param string $attr_name Name of attribute to split
     * @param string $content   Content to split
     *
     * @return array
     */
    function splitContent($attr_name, $content) {
        $char_limit = 1000;

        // Split content into 1000 char chunks
        $split_content = str_split(strip_tags($content), $char_limit);
        // Map each content chunk to a record array
        $content_records = array_map(
            function ($val) use ($attr_name) {
                return array($attr_name => $val);
            }, $split_content
        );

        // Sanitize data to support non UTF-8 content
        // https://github.com/algolia/algoliasearch-wordpress/issues/377
        if (function_exists('_wp_json_sanity_check')) {
            return _wp_json_sanity_check($content_records, 512);
        }

        return $content_records;
    }

    /**
     * Get records for the given post
     *
     * @param object $post          Post to get records for
     * @param array  $post_attrs    Post-specific record attributes
     * @param bool   $split_content Whether or not to split the post content
     *
     * @return array
     */
    function serializeRecord($post, $post_attrs = []) {
        $blog_id = get_current_blog_id();
        $records = [];

        // Split records on post_content
        $records = $this->splitContent('content', $post->post_content);

        // Merge all attributes for each split record and add a unique objectID
        foreach ($records as $key => $split) {
            $records[$key] = array_merge(
                $this->getDefaultRecordAttributes($post, $blog_id),
                $post_attrs,
                $split,
                ['objectID' => implode('-', [$blog_id, $post->post_type, $post->ID, $key])]
            );
        };

        return $records;
    }

    /**
     * Converts a Page to a list of Algolia records
     *
     * @param object $post Post to get record of
     *
     * @return array
     */
    function algolia_page_to_record($post) {
        return $this->serializeRecord($post, []);
    }

    /**
     * Converts a Post to an Algolia record
     *
     * @param object $post Post to get record of
     *
     * @return array
     */
    function algolia_post_to_record($post) {
        $tags = $this->getTermNames($post, 'post_tag');

        $post_record_attrs = [
            'tags' => $tags,
        ];

        return $this->serializeRecord($post, $post_record_attrs);
    }

    /**
     * DEMO: Converts a Monkey type post to an Algolia record
     *
     * @param object $post Post to get record of
     *
     * @return array
     */
    function algolia_monkey_to_record($post) {
        $monkey_types = $this->getTermNames($post, 'monkey_type');

        $record_attrs = [
            'name' => get_field('name', $post->ID),
            'thumbnail' => get_the_post_thumbnail_url($post->ID),
            'monkey_types' => $monkey_types,
        ];

        return $this->serializeRecord($post, $record_attrs);
    }

    /**
     * Gets the settings for the given index from its local JSON config
     *
     * @param string $index_name Unprefixed index name (e.g. `global_search`).
     *
     * @return array
     */
    function algolia_get_settings($index_name) {
        $settings_file_path = THEME_PATH . 'algolia-json/' . $index_name . '-settings.json';

        if (!file_exists($settings_file_path)) {
            return false;
        }

        return json_decode(
            file_get_contents($settings_file_path),
            true
        );
    }

    /**
     * Gets the synonyms for the given index from its local JSON config
     *
     * @param string $index_name Unprefixed index name (e.g. `global_search`).
     *
     * @return array
     */
    function algolia_get_synonyms($index_name) {
        $settings_file_path = THEME_PATH . 'algolia-json/' . $index_name . '-synonyms.json';

        if (!file_exists($settings_file_path)) {
            return false;
        }

        return json_decode(
            file_get_contents($settings_file_path),
            true
        );
    }

    /**
     * Gets the rules for the given index from its local JSON config
     *
     * @param string $index_name Unprefixed index name (e.g. `global_search`).
     *
     * @return array
     */
    function algolia_get_rules($index_name) {
        $settings_file_path = THEME_PATH . 'algolia-json/' . $index_name . '-rules.json';

        if (!file_exists($settings_file_path)) {
            return false;
        }

        return json_decode(
            file_get_contents($settings_file_path),
            true
        );
    }

    /**
     * Writes settings for the given index to its local JSON config file
     *
     * @param string $index_name Unprefixed index name (e.g. `global_search`)
     * @param string $settings   Index settings in JSON format
     *
     * @return void
     */
    function algolia_write_settings($index_name, $settings)
    {
        $settings_file_path = THEME_PATH . 'algolia-json/' . $index_name . '-settings.json';
        file_put_contents($settings_file_path, $settings);
    }

    /**
     * Writes synonyms for the given index to its local JSON config file
     *
     * @param string $index_name Unprefixed index name (e.g. `global_search`)
     * @param string $synonyms   Index synonyms in JSON format
     *
     * @return void
     */
    function algolia_write_synonyms($index_name, $synonyms)
    {
        $synonyms_file_path = THEME_PATH . 'algolia-json/' . $index_name . '-synonyms.json';
        file_put_contents($synonyms_file_path, $synonyms);
    }

    /**
     * Writes rules for the given index to its local JSON config file
     *
     * @param string $index_name Unprefixed index name (e.g. `global_search`)
     * @param string $rules      Index rules in JSON format
     *
     * @return void
     */
    function algolia_write_rules($index_name, $rules)
    {
        $rules_file_path = THEME_PATH . 'algolia-json/' . $index_name . '-rules.json';
        file_put_contents($rules_file_path, $rules);
    }
}
