<?php

// https://www.algolia.com/doc/integration/wordpress/indexing/importing-content/?language=php

if (! (defined('WP_CLI') && WP_CLI)) {
    return;
}

/**
 * Gets an array of searchable post types
 *
 * @return array
 */
function getSearchablePostTypes() {
    return get_post_types(
        array(
            'public' => true,
            'exclude_from_search' => false
        )
    );
}

class Algolia_Command {
    /**
     * Index posts in Algolia
     * `wp algolia reindex <options>`
     *
     * @return string/bool
     */
    public function reindex($args, $assoc_args) {
        global $algolia;

        // Get post type if flag is passed (--type="type")
        $type = isset($assoc_args['type']) ? $assoc_args['type'] : null;

        $searchable_post_types = getSearchablePostTypes();

        // Bail early if type arg is not valid
        if ($type && !in_array($type, $searchable_post_types)) {
            WP_CLI::error("$type is not a valid post type!");
            return;
        }

        $searchable_post_types = $type ? array($type) : $searchable_post_types;

        // Generates index for global index, or passed --index="" arg
        $index_name = isset($assoc_args['index']) ? $assoc_args['index'] : 'global_search';
        $canonical_index_name = apply_filters('get_algolia_index_name', $index_name);
        $index = $algolia->initIndex($canonical_index_name);

        // If reindexing the global index, clear the entire index
        // https://www.algolia.com/doc/api-reference/api-methods/clear-objects/
        if (!$type) {
            WP_CLI::line('Clearing all records from index: '.WP_CLI::colorize("%p$canonical_index_name%n"));

            $index->clearObjects()->wait();
        }

        // Get all blog IDs in multisite network
        $all_blog_ids = get_sites(array('fields' => 'ids'));

        // Index posts for each site in the multisite network
        foreach ($all_blog_ids as $blog_id) {
            switch_to_blog($blog_id);

            WP_CLI::line("\n".'Indexing posts from '.WP_CLI::colorize("%bBlog $blog_id%n")."\n");

            // Loop through searchable post types and serialize each post
            foreach ($searchable_post_types as $post_type) {
                $this->serialize_records($index, $post_type, $assoc_args);
            }

            restore_current_blog();
        }
    }

    /**
     * Query for posts and and serialize them to be saved as records in Algolia
     *
     * @return void
     */
    private function serialize_records($index, $post_type, $assoc_args) {
        $paged = 1;
        $count = 0;

        do {
            $posts = new WP_Query([
                'posts_per_page' => 100,
                'paged' => $paged,
                'post_type' => $post_type,
                'post_status' => 'publish',
            ]);

            if (!$posts->have_posts()) {
                break;
            }

            try {
                $records = [];

                // Serialize each post to be saved as Algolia records
                foreach ($posts->posts as $post) {
                    if (isset($assoc_args['verbose'])) {
                        WP_CLI::line("Serializing [$post->post_type] $post->post_title");
                    }

                    // Use post type to get corresponding serializer function
                    $filter_name = $post->post_type.'_to_record';

                    // Bail early if filter does not exist
                    if (!has_filter($filter_name)) {
                        throw new Exception("No filter called $filter_name");
                    }

                    // The serialize function will take care of splitting large records
                    $split_records = (array) apply_filters($filter_name, $post);
                    $records = array_merge($records, $split_records);

                    $count++;
                }

                if (isset($assoc_args['verbose'])) {
                    WP_CLI::line('Sending batch...');
                }

            } catch (Exception $e) {
                WP_CLI::error($e->getMessage());
            }

            // Save the records in Algolia!
            // https://www.algolia.com/doc/api-reference/api-methods/save-objects/
            try {
                $index->saveObjects($records);

                WP_CLI::success("$count $post_type records indexed in Algolia");

            } catch (Exception $e) {
                WP_CLI::error($e->getMessage());
            }

            $paged++;

        } while (true);
    }

    /**
     * Get index config and print out in JSON format
     * `wp algolia get_config <options>`
     *
     * @return string/bool
     */
    public function get_config($args, $assoc_args) {
        global $algolia;

        // Generates index for global index, or passed --index="" arg
        $index_name = isset($assoc_args['index']) ? $assoc_args['index'] : 'global_search';
        $canonical_index_name = apply_filters('get_algolia_index_name', $index_name);
        $index = $algolia->initIndex($canonical_index_name);

        // Bail early if index does not exist
        if (!$index->exists()) {
            WP_CLI::error("Index $canonical_index_name does not exist!");
            return;
        }

        // Print out index settings if '--settings' flag exists
        if (isset($assoc_args['settings'])) {
            $settings = $index->getSettings();

            WP_CLI::log(WP_CLI::colorize('%CSettings for index "'.$index->getIndexName().'"%n'));
            print_r(json_encode($settings, JSON_PRETTY_PRINT) . "\n\n");
        }

        // Print out index synonyms if '--synonyms' flag exists
        if (isset($assoc_args['synonyms'])) {
            $synonyms_iterator = $index->browseSynonyms();

            $synonyms = array();
            foreach ($synonyms_iterator as $synonym) {
                $synonyms[] = $synonym;
            }

            WP_CLI::log(WP_CLI::colorize('%CSynonyms for index "'.$index->getIndexName().'"%n'));
            print_r(json_encode($synonyms, JSON_PRETTY_PRINT) . "\n\n");
        }

        // Print out index rules if '--rules' flag exists
        if (isset($assoc_args['rules'])) {
            $rules_iterator = $index->searchRules();

            $rules = array();
            foreach ($rules_iterator as $rule) {
                $rules[] = $rule;
            }

            WP_CLI::log(WP_CLI::colorize('%CRules for index "'.$index->getIndexName().'"%n'));
            print_r(json_encode($rules, JSON_PRETTY_PRINT) . "\n\n");
        }
    }

    /**
     * Set index config based on local JSON config file
     * https://www.algolia.com/doc/integration/wordpress/managing-indices/set-configuration/?language=php
     * `wp algolia set_config <options>`
     *
     * @return string/bool
     */
    public function set_config($args, $assoc_args) {
        global $algolia;

        // Generates index for global index, or passed --index="" arg
        $index_name = isset($assoc_args['index']) ? $assoc_args['index'] : 'global_search';
        $canonical_index_name = apply_filters('get_algolia_index_name', $index_name);
        $index = $algolia->initIndex($canonical_index_name);

        // Bail early if index does not exist
        if (!$index->exists()) {
            WP_CLI::error("Index $canonical_index_name does not exist!");
            return;
        }

        // Set index settings if '--settings' flag exists
        if (isset($assoc_args['settings'])) {
            $settings = (array) apply_filters('algolia_get_settings', $index_name, []);
            if ($settings) {
                $index->setSettings($settings);
                WP_CLI::success('Pushed settings to '.$index->getIndexName());
            }
        }

        // Set index synonyms if '--synonyms' flag exists
        if (isset($assoc_args['synonyms'])) {
            $synonyms = (array)  apply_filters('algolia_get_synonyms', $index_name, []);
            if ($synonyms) {
                $index->replaceAllSynonyms($synonyms);
                WP_CLI::success('Pushed synonyms to '.$index->getIndexName());
            }
        }

        // Set index rules if '--rules' flag exists
        if (isset($assoc_args['rules'])) {
            $rules = (array) apply_filters('algolia_get_rules', $index_name, []);
            if ($rules) {
                $index->replaceAllRules($rules);
                WP_CLI::success('Pushed rules to '.$index->getIndexName());
            }
        }
    }
}

WP_CLI::add_command('algolia', 'Algolia_Command');
