# Algolia WordPress Integration

A WordPress plugin following [Algolia's WordPress integration guide](https://www.algolia.com/doc/integration/wordpress/getting-started/quick-start/?language=php) with support for WordPress multisite and large record splitting

## Installation

1. Add this plugin to your `/wp-content/plugins` directory
2. Install PHP dependencies `composer install`
3. Activate (or Network Activate) this plugin in your WP admin dashboard

## Setup

### Environment Variables

At the root of your project (not the plugins directory), add an `.env` file and add the following

```shell
ALGOLIA_APPLICATION_ID=
ALGOLIA_ADMIN_API_KEY=
ALGOLIA_SEARCH_ONLY_API_KEY=
ALGOLIA_INDEX_PREFIX=local
```

The `ALGOLIA_APPLICATION_ID`, `ALGOLIA_ADMIN_API_KEY`, and `ALGOLIA_SEARCH_ONLY_API_KEY` keys can be found in your Algolia dashboard under `API Keys`.

The `ALGOLIA_INDEX_PREFIX` is used to prepend the Algolia index name in order to create separate indices for different environments.

- `ALGOLIA_INDEX_PREFIX=local` => `local_wp_global_search`
- `ALGOLIA_INDEX_PREFIX=staging` => `staging_wp_global_search`

To access these environment variables on the front end, we've added the `ALGOLIA_APPLICATION_ID`, `ALGOLIA_SEARCH_ONLY_API_KEY`, and `ALGOLIA_INDEX_PREFIX` keys to the timber context to be accessed via twig files. (Yes, this could be a security issue, but we do not expose the `ALGOLIA_ADMIN_API_KEY`, just the `ALGOLIA_SEARCH_ONLY_API_KEY`.)

If you're using [Timber](https://www.upstatement.com/timber/), in your layout file, you should be able to add a `<script>` tag that adds the keys to an object on the `window`.

```html
<script>
  window.algolia = {
    env: {
      ALGOLIA_APPLICATION_ID: '{{ ALGOLIA_APPLICATION_ID | e("js") }}',
      ALGOLIA_SEARCH_ONLY_API_KEY: '{{ ALGOLIA_SEARCH_ONLY_API_KEY | e("js") }}',
      ALGOLIA_INDEX_PREFIX: '{{ ALGOLIA_INDEX_PREFIX | e("js") }}',
    }
  }
</script>
```

Then, in your JavaScript:

```js
const { ALGOLIA_APPLICATION_ID, ALGOLIA_SEARCH_ONLY_API_KEY } = window.algolia.env;
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

### Get index configuration and print out in JSON format

```shell
./bin/wp algolia get_config --settings
```

### Set index configuration (using local JSON files)

At the root of your project, create an `algolia-json` folder and add a file called `global_search-settings.json`. Below is an example JSON file:

```json
{
  "minWordSizefor1Typo": 4,
  "minWordSizefor2Typos": 8,
  "hitsPerPage": 20,
  "maxValuesPerFacet": 100,
  "version": 2,
  "searchableAttributes": [
    "unordered(title)",
    "unordered(content)",
  ],
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

This JSON file is used to set the configuration of your global search index with the `set_config` command:

```shell
./bin/wp algolia set_config --settings
```
