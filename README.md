# Upstatement Algolia Admin

A WordPress Algolia plugin derived from [Algolia's WordPress integration guide](https://www.algolia.com/doc/integration/wordpress/getting-started/quick-start/?language=php).

UpsAlgolia implements the backend administration of Algolia such as post indexing and deletion, and Algolia index management (e.g. clearing records, reindexing records, pushing settings). The plugin exposes various [WordPress filters](https://developer.wordpress.org/plugins/hooks/filters/) to be theme agnostic and compatible with a Multisite environment.

## Table of Contents

- [System Requirements](#gear-system-requirements)
- [Quick Start](#fire-quick-start)
- [Meet the Filters](#wave-meet-the-filters)
- [WP CLI Commands](#checkered_flag-wp-cli-commands)
- [Examples](#gift-examples)

## :gear: System Requirements

- PHP 5.3 or newer (version 7.1+ is highly recommended)
- [WordPress](https://codex.wordpress.org/Installing_WordPress) (up and running instance)
- [WP-CLI](https://make.wordpress.org/cli/handbook/installing/)

## :computer: Installation

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

4. Activate (or [Network Activate](https://premium.wpmudev.org/manuals/wpmu-manual-2/network-enabling-regular-plugins/)) the plugin in your WP admin dashboard

## :wave: Meet the Filters

### UpsAlgolia\get_algolia_application

```php
/**
 * Get credentials to Algolia application.
 *
 * @see https://www.algolia.com/doc/guides/security/api-keys/
 *
 * @return object [ "application_id": string, "admin_key": string ]
 */
function get_algolia_application()
```

### UpsAlgolia\get_index_name

```php
/**
 * Get Algolia index name for given post.
 *
 * @param object $post post
 *
 * @return string
 */
function get_index_name($post)
```

### UpsAlgolia\is_indexable

```php
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

### UpsAlgolia\\<post_type>\_to_record

```php
/**
 * Serialize given post into Algolia records
 *
 * @param object $post post
 *
 * @return array one or more Algolia records
 */
function <post_type>_to_record($post)
```

Any dashes `-` will be replaced with underscores `_`.

### UpsAlgolia\get_algolia_settings

```php
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

### UpsAlgolia\get_algolia_rules

```php
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

### UpsAlgolia\get_algolia_synonyms

```php
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

## :checkered_flag: WP CLI Commands

[WP CLI](https://wp-cli.org/) commands are used to easily manage our WordPress content in Algolia.

If you're using a [Skela](https://github.com/Upstatement/skela-wp-theme) theme, you can run WP CLI commands via the [`./bin/wp` script](https://github.com/Upstatement/skela-wp-theme/blob/master/bin/wp). Otherwise, omit `./bin/` from the following commands.

### reindex

```php
/**
 * Reindex the records of a post type from given index.
 *
 * @param string  index_name  name of Algolia index
 * @param string  post_type   post type to reindex
 * @param integer blog_id     blog id to pull posts from
 *
 * `wp algolia reindex <index_name> --type=<post_type> --blog_id=<blog_id>`
 */
```

If `post_type` is not specified, this wp-cli command will reindex all searchable post types whose `exclude_from_search` is `false`. If `blog_id` is not specified, this wp-cli command will reindex all available sites.

> Note: this does not clear or replace records in the index. To do so, run the `clear` command beforehand.

### clear

```php
/**
 * Clear the records from given index and with given filters.
 *
 * @param string  index_name  name of Algolia index
 * @param key     attribute in Algolia records
 * @param value   value mapped to given attribute
 *
 * `wp algolia clear <index_name> [--<key>=<value>, ...]`
 */
```

Specify which subset of records to clear by naming the `key`(s) and their corresponding `value`s. For example, if your records have the `type` key, you can clear all records with `post` as their value like this:

```shell
wp algolia clear global_search --type=post
```

### push_config

```php
/**
 * Push Algolia config to index if provided, otherwise
 * send config to all available indices.
 *
 * @param string index_name  name of Algolia index
 * @param bool   settings    reconfigure settings
 * @param bool   synonyms    reconfigure synonyms
 * @param bool   rules       reconfigure rules
 *
 * `wp algolia push_config <index_name> [--settings] [--synonyms] [--rules]`
 */
```

## :gift: Examples

Examples for hooking into UpsAlgolia filters are [here](./docs).

## Contributing

We welcome all contributions to our projects! Filing bugs, feature requests, code changes, docs changes, or anything else you'd like to contribute are all more than welcome! More information about contributing can be found in the [contributing guidelines](.github/CONTRIBUTING.md).

## Code of Conduct

Upstatement strives to provide a welcoming, inclusive environment for all users. To hold ourselves accountable to that mission, we have a strictly-enforced [code of conduct](CODE_OF_CONDUCT.md).

## About Upstatement

[Upstatement](https://www.upstatement.com/) is a digital transformation studio headquartered in Boston, MA that imagines and builds exceptional digital experiences. Make sure to check out our [services](https://www.upstatement.com/services/), [work](https://www.upstatement.com/work/), and [open positions](https://www.upstatement.com/jobs/)!
