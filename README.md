# Upstatement Algolia Admin

A WordPress Algolia plugin derived from [Algolia's WordPress integration guide](https://www.algolia.com/doc/integration/wordpress/getting-started/quick-start/?language=php).

UpsAlgolia implements the backend administration of Algolia such as post indexing and deletion, and Algolia index management (e.g. clearing records, reindexing records, pushing settings). The plugin exposes various [WordPress filters](https://developer.wordpress.org/plugins/hooks/filters/) to be theme agnostic and compatible with a Multisite environment.

## Table of Contents

- [System Requirements](#gear-system-requirements)
- [Quick Start](#fire-quick-start)
- [Meet the Filters](#wave-meet-the-filters)
- [WP CLI Commands](#checkered_flag-wp-cli-commands)
- [Examples](#gift-examples)
- [Road Map](#world_map-road-map)

## :gear: System Requirements

- PHP 5.3 or newer (version 7.1+ is highly recommended)
- [WordPress](https://codex.wordpress.org/Installing_WordPress) (up and running instance)
- [WP-CLI](https://make.wordpress.org/cli/handbook/installing/)

## :fire: Quick Start

1. Clone this repository

   ```shell
   git clone git@github.com:Upstatement/algolia-wordpress-integration.git
   ```

2. Add this plugin to your `/wp-content/plugins` directory
3. Install PHP dependencies

   ```shell
   cd plugins/algolia-wordpress-integration && composer install
   ```

   This will install all dependencies in the `vendor` directory at the root of the plugin.

4. Port the example [serializer code](./docs/functions.php) to your theme's `functions.php` OR, if you're using ESK, pull the [AlgoliaManager](./docs/AlgoliaManager.php) into your `Managers` directory and initialize it in `functions.php`. Replace the placeholder `ALGOLIA_APPLICATION_ID` and `ALGOLIA_ADMIN_KEY` with your Algolia application.

   ```php
   /**
    * Get Algolia application id and admin key.
    *
    * @return object
    */
   function get_algolia_application()
   {
     return [
       "application_id" => 'ALGOLIA_APPLICATION_ID',
       "admin_key" => 'ALGOLIA_ADMIN_KEY'
     ];
   }
   ```

5. (Optional) Change the targeted index on Algolia.

   ```php
   /**
    * Get Algolia index name. All posts
    * use the same global index.
    *
    * @param object $post post
    *
    * @return string
    */
   function get_index_name($post)
   {
     return "global_search";
   }
   ```

6. Activate (or [Network Activate](https://premium.wpmudev.org/manuals/wpmu-manual-2/network-enabling-regular-plugins/)) the plugin in your WP admin dashboard
7. Saving posts or pages should index them to your Algolia application.

## :wave: Meet the Filters

### UpsAlgolia\get_algolia_application

Retreives the necessary credentials to an Algolia application. This filter expects the application ID and admin key.

```php
add_filter('UpsAlgolia\get_algolia_application', 'get_algolia_application');

/**
 * Get credentials to Algolia application.
 *
 * @see https://www.algolia.com/doc/guides/security/api-keys/
 *
 * @return object [ "application_id": string, "admin_key": string ]
 */
function get_algolia_application()
```

<br/>

### UpsAlgolia\get_index_name

Retrieve the targeted Algolia index for a given post. You can choose the index based on the post's type or blog id for example if you chose to partition the indices that way. Otherwise, you can return a standard index for all posts.

```php
add_filter('UpsAlgolia\get_index_name', 'get_index_name');

/**
 * Get Algolia index name for given post.
 *
 * @param object $post post
 *
 * @return string
 */
function get_index_name($post)
```

<br/>

### UpsAlgolia\is_indexable

Checks whether given post is to be indexed. If `true`, UpsAlgolia will run the serializer on the post and index it to Algolia. Otherwise, UpsAlgolia will skip the post.

> This filter's default behavior if not overridden is to index posts only if their status is either `publish` or `trash`. Posts that are under revision or autosave are not indexed.

```php
add_filter('UpsAlgolia\is_indexable', 'is_indexable', 10, 2);

/**
 * Can or should this post be indexed?
 *
 * @param string $id    id of post
 * @param object $post  post
 *
 * @return bool
 */
function is_indexable($id, $post)
```

<br/>

### UpsAlgolia\\<post_type>\_to_record

Serialize the given post based on its type (e.g. `post`, `page`, `<your_custom_post_type>`). To maintain consistency in naming the serializer function, UpsAlgolia transforms the post type to standard conventions. For example, the plugin will transform dashes (`-`) to underscores (`_`).

```php
add_filter('UpsAlgolia\<post_type>_to_record', '<post_type>_to_record');

/**
 * Serialize given post into Algolia records
 *
 * @param object $post post
 *
 * @return array one or more Algolia records
 */
function <post_type>_to_record($post)
```

The serializer function should return an array of records to support [content splitting](https://www.algolia.com/doc/integration/wordpress/advanced/splitting-large-records/?language=php).

> UpsAlgolia expects the `distinct_key` attribute in every record. It uses the attribute to clear all preexisting records in Algolia when updating a post in WordPress admin to ensure that there are no duplicate records.

<br/>

### UpsAlgolia\get_algolia_settings

Retrieve an object representing [Algolia settings](https://www.algolia.com/doc/api-reference/settings-api-parameters/). This filter will be used by the `push_config` WP CLI command. Add this filter if you need to maintain consistent settings across your indices.

```php
add_filter('UpsAlgolia\get_algolia_settings', 'get_algolia_settings');

/**
 * Get Algolia settings
 * @see https://www.algolia.com/doc/api-reference/settings-api-parameters/
 *
 * @param string $index index name
 *
 * @return object
 */
function get_algolia_settings($index)
```

<br/>

### UpsAlgolia\get_algolia_rules

Retrieve an object representing [Algolia rules](https://www.algolia.com/doc/guides/managing-results/rules/rules-overview/). This filter will be used by the `push_config` WP CLI command. Add this filter if you need to maintain consistent rules across your indices.

```php
add_filter('UpsAlgolia\get_algolia_rules', 'get_algolia_rules');

/**
 * Get Algolia rules.
 * @see https://www.algolia.com/doc/guides/managing-results/rules/rules-overview/
 *
 * @param string $index index name
 *
 * @return object
 */
function get_algolia_rules($index)
```

<br/>

### UpsAlgolia\get_algolia_synonyms

Retrieve an object representing [Algolia synonyms](https://www.algolia.com/doc/guides/managing-results/optimize-search-results/adding-synonyms/). This filter will be used by the `push_config` WP CLI command. Add this filter if you need to maintain consistent synonyms across your indices.

```php
add_filter('UpsAlgolia\get_algolia_synonyms', 'get_algolia_synonyms');

/**
 * Get Algolia synonyms.
 * @see https://www.algolia.com/doc/guides/managing-results/optimize-search-results/adding-synonyms/
 *
 * @param string $index index name
 *
 * @return object
 */
function get_algolia_synonyms($index)
```

<br />

## :checkered_flag: WP CLI Commands

[WP CLI](https://wp-cli.org/) commands are used to easily manage our WordPress content in Algolia.

If you're using a [Skela](https://github.com/Upstatement/skela-wp-theme) theme, you can run WP CLI commands via the [`./bin/wp` script](https://github.com/Upstatement/skela-wp-theme/blob/master/bin/wp). Otherwise, omit `./bin/` from the following commands.

### reindex

Reindex all posts that match given type and blog ID into the specified index.

```shell
wp algolia reindex <index_name> --type=<post_type> --blog_id=<blog_id>
```

- `index_name`: name of targeted Algolia index
- `post_type`: type of posts (e.g. `post`, `page`, `<your_custom_post_type>`)
- `blog_id`: blog ID in a Multisite environment.

If `post_type` is not specified, this wp-cli command will reindex all searchable post types whose `exclude_from_search` is `false`. If `blog_id` is not specified, this wp-cli command will reindex _all_ available sites.

> Algolia will automatically replace existing records with the same `objectID`. However, this command _will not_ automatically clear records with the same `distinct_key`. You'd have to run the `clear` command below and then run this `reindex` command.

<br />

### clear

Clear records that match given keys and values from the specified index.

```shell
wp algolia clear <index_name> [--<key>=<value>, ...]
```

- `index_name`: name of targeted Algolia index
- `key`: attribute label of targeted records
- `value`: attribute value of targeted records

Specify which subset of records to clear by naming the `key`(s) and their corresponding `value`s. For example, if your records have the `type` key, you can clear all records with `post` as their value like this:

```shell
wp algolia clear global_search --type=post
```

Not specifying any key value pairs will clear _all records_ in the index.

<br/>

### push_config

Push Algolia confiugration to index if provided, otherwise send configuration to all available indices.

```shell
wp algolia push_config <index_name> [--settings] [--synonyms] [--rules]
```

- `index_name`: name of targeted Algolia index
- `settings`: push settings?
- `synonyms`: push synonyms?
- `rules`: push rules?

<br/>

## :gift: Examples

Examples for hooking into UpsAlgolia filters [here](./docs).

## :world_map: Road Map

This plugin is being actively developed. Here's what we have in our road map.

- [ ] WordPress' admin interface to inject Algolia credentials instead of doing it in code.
- [ ] WordPress' admin interface to execute WP CLI commands
- [ ] Host the plugin as a composer package under a private Upstatement registry

## Contributing

We welcome all contributions to our projects! Filing bugs, feature requests, code changes, docs changes, or anything else you'd like to contribute are all more than welcome! More information about contributing can be found in the [contributing guidelines](.github/CONTRIBUTING.md).

## Code of Conduct

Upstatement strives to provide a welcoming, inclusive environment for all users. To hold ourselves accountable to that mission, we have a strictly-enforced [code of conduct](CODE_OF_CONDUCT.md).

## About Upstatement

[Upstatement](https://www.upstatement.com/) is a digital transformation studio headquartered in Boston, MA that imagines and builds exceptional digital experiences. Make sure to check out our [services](https://www.upstatement.com/services/), [work](https://www.upstatement.com/work/), and [open positions](https://www.upstatement.com/jobs/)!
