# UpsAlgolia

A WordPress Algolia plugin derived from [Algolia's WordPress integration guide](https://www.algolia.com/doc/integration/wordpress/getting-started/quick-start/?language=php).

UpsAlgolia implements post indexing and deletion, and Algolia index management (e.g. clearing records, pushing settings). The plugin exposes various [WordPress filters](https://developer.wordpress.org/plugins/hooks/filters/) to be theme agnostic and compatible with a Multisite environment.

## Table of Contents

## System Requirements

- PHP 5.3 or newer (version 7.1+ is highly recommended)
- [WordPress](https://codex.wordpress.org/Installing_WordPress) (up and running instance)
- [WP-CLI](https://make.wordpress.org/cli/handbook/installing/)

## Installation

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

#### UpsAlgolia\get_algolia_application

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

#### UpsAlgolia\get_index_name

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

#### UpsAlgolia\is_indexable

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

#### UpsAlgolia\\<post_type>\_to_record

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

#### UpsAlgolia\get_algolia_settings

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

#### UpsAlgolia\get_algolia_rules

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

#### UpsAlgolia\get_algolia_synonyms

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

### Setting up environment variables

At the root of your project (not the plugins directory), add an `.env` file and add the following

```shell
ALGOLIA_APPLICATION_ID=
ALGOLIA_ADMIN_API_KEY=
ALGOLIA_SEARCH_ONLY_API_KEY=
ALGOLIA_INDEX_PREFIX=local
```

The `ALGOLIA_APPLICATION_ID`, `ALGOLIA_ADMIN_API_KEY`, and `ALGOLIA_SEARCH_ONLY_API_KEY` keys can be found in your Algolia dashboard under `API Keys`.

The `ALGOLIA_INDEX_PREFIX` is used to prepend the Algolia index name in order to use separate indices for different environments.

- `ALGOLIA_INDEX_PREFIX=local` => `local_wp_global_search`
- `ALGOLIA_INDEX_PREFIX=staging` => `staging_wp_global_search`

### Accessing environment variables in templates

Here at Upstatement, we use [Timber](https://www.upstatement.com/timber/) with all of our WordPress sites. Timber allows us to write our templates in [Twig](https://twig.symfony.com/).

To access these the Algolia environment variables in Twig templates, we've added the `ALGOLIA_APPLICATION_ID`, `ALGOLIA_SEARCH_ONLY_API_KEY`, and `ALGOLIA_INDEX_PREFIX` keys to the Timber context.

In your layout file (usually located at `templates/layouts/base.twig`), before the closing body tag, you can add a `<script>` tag to add an object containing the keys to the `window`.

```html
<script>
  window.algolia = {
    env: {
      ALGOLIA_APPLICATION_ID: '{{ ALGOLIA_APPLICATION_ID | e("js") }}',
      ALGOLIA_SEARCH_ONLY_API_KEY: '{{ ALGOLIA_SEARCH_ONLY_API_KEY | e("js") }}',
      ALGOLIA_INDEX_PREFIX: '{{ ALGOLIA_INDEX_PREFIX | e("js") }}',
    },
  };
</script>
```

Then, in your JavaScript:

```js
const {
  ALGOLIA_APPLICATION_ID,
  ALGOLIA_SEARCH_ONLY_API_KEY,
  ALGOLIA_INDEX_PREFIX,
} = window.algolia.env;
```

## WP CLI Commands

[WP CLI](https://wp-cli.org/) commands are used to easily index our WordPress content in Algolia.

If you're using a [Skela](https://github.com/Upstatement/skela-wp-theme) theme, you can run WP CLI commands via the [`./bin/wp` script](https://github.com/Upstatement/skela-wp-theme/blob/master/bin/wp). Otherwise, omit `./bin/` from the following commands.

### Indexing records

To reindex ALL records in the **global index**, use the following command

```shell
./bin/wp algolia reindex
```

To reindex all records with more detailed logs, use the `--verbose` flag

```shell
./bin/wp algolia reindex --verbose
```

To reindex all records for a specific post type, use the `--type=""` argument

```shell
./bin/wp algolia reindex --type="monkey"
```

To reindex all records for a specific index, use the `--index=""` argument

```shell
./bin/wp algolia reindex --index="your_index_name"
```

### Pull index configuration (outputs to local JSON files)

The following command will write your Algolia index configuration to a JSON file called `global_search-settings.json` under a new `algolia-json` folder at the root of your project.

```shell
./bin/wp algolia pull_config --settings
```

### Push index configuration (using local JSON files)

If you didn't run the `pull_config` command, then at the root of your project, create an `algolia-json` folder and add a file called `global_search-settings.json`. Below is an example JSON file:

```json
{
  "minWordSizefor1Typo": 4,
  "minWordSizefor2Typos": 8,
  "hitsPerPage": 20,
  "maxValuesPerFacet": 100,
  "version": 2,
  "searchableAttributes": ["unordered(title)", "unordered(content)"],
  "numericAttributesToIndex": null,
  "attributesToRetrieve": null,
  "distinct": true,
  "unretrievableAttributes": null,
  "optionalWords": null,
  "attributesForFaceting": [],
  "attributesToSnippet": ["content:10", "title:10"],
  "attributesToHighlight": null,
  "paginationLimitedTo": 1000,
  "attributeForDistinct": "distinct_key",
  "exactOnSingleWordQuery": "attribute",
  "ranking": ["typo", "geo", "words", "filters", "proximity", "attribute", "exact", "custom"],
  "customRanking": null,
  "separatorsToIndex": "",
  "removeWordsIfNoResults": "none",
  "queryType": "prefixLast",
  "highlightPreTag": "<em>",
  "highlightPostTag": "</em>",
  "snippetEllipsisText": "...",
  "alternativesAsExact": ["ignorePlurals", "singleWordSynonym"]
}
```

If you ran `pull_config`, this JSON file should already exist. It is used to set the configuration of your global search index with the `push_config` command:

```shell
./bin/wp algolia push_config --settings
```

## Contributing

We welcome all contributions to our projects! Filing bugs, feature requests, code changes, docs changes, or anything else you'd like to contribute are all more than welcome! More information about contributing can be found in the [contributing guidelines](.github/CONTRIBUTING.md).

## Code of Conduct

Upstatement strives to provide a welcoming, inclusive environment for all users. To hold ourselves accountable to that mission, we have a strictly-enforced [code of conduct](CODE_OF_CONDUCT.md).

## About Upstatement

[Upstatement](https://www.upstatement.com/) is a digital transformation studio headquartered in Boston, MA that imagines and builds exceptional digital experiences. Make sure to check out our [services](https://www.upstatement.com/services/), [work](https://www.upstatement.com/work/), and [open positions](https://www.upstatement.com/jobs/)!
