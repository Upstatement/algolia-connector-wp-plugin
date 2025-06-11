# Algolia Connector WordPress plugin

Hooks up a connection from WordPress to Algolia.

> [!WARNING]
> Note that this plugin is in active development and may have missing features or bugs. It is intended primarily as a starting point for a WordPress to Algolia integration. Use with caution!

## System Requirements

- PHP 8.2 or newer.
- WordPress version 6.6 or newer.
- [Composer](https://getcomposer.org/)

## Installation

1. Download this plugin and add it to the `/wp-content/plugins/` directory of your WordPress environment.
2. Install PHP dependencies by running `composer install` in the plugin directory.

## Usage

Once you have set up your Algolia application, retrieve your account credentials in the [API Keys](https://www.algolia.com/account/api-keys/all) section of your Algolia account. This plugin provides a settings page that can be used to store these values, but it is recommended that you use environment variables (you can also save the values as constants). The following

| Value          | Variable name            |
| -------------- | ------------------------ |
| Application ID | `ALGOLIA_APPLICATION_ID` |
| Search API key | `ALGOLIA_SEARCH_API_KEY` |
| Admin API key  | `ALGOLIA_ADMIN_API_KEY`  |
| Index name     | `ALGOLIA_INDEX_NAME`     |

## Configuring your theme

The plugin exposes a few hooks that allow you to customize what and how data gets sent to the Algolia index.

### `do_action( "algolia_indexable_post_types", array $post_types )`

By default, no content types will be indexed. You can add which post types should be indexed by returning them from the `algolia_indexable_post_types` filter:

#### Parameters

- `$post_types` array

  The list of post types that can be indexed.

#### Example

```php
add_filter( 'algolia_indexable_post_types', function( $post_types ) {
	$indexable_types = array( 'post', 'page', 'some_custom_type' );
	return array_merge( $post_types, $indexable_types );
} );
```

### `do_action( "algolia_{$post->post_type}_to_record", array $record )`

You can customize the Algolia record with the `algolia_{$post->post_type}_to_record` hook, replacing the dynamic portion with the post type handle.

#### Parameters

- `$record` array

  The record object that will be saved to Algolia. By default, the record is saved with the following values:

  - `distinct_key` A unique identifier for the post object within Algolia.
  - `post_id` ID of the post.
  - `title` Title of the post.
  - `post_type` Type of the post.
  - `url` A URL for the post, retrieved with `get_permalink()`.
  - `book` A default boost setting.
  - `content` Content. This is usually one of the searchable fields.

```php
// Modify the record for all Page posts.
add_filter( 'algolia_page_to_record', function ( $record ) {
	// You can access the WP_Post object like this:
	$_post = get_post( $record['post_id'] );

	// Add other Page data, like post metadata, to the record.
	$record['date'] = get_post_meta( $record['post_id'], 'some_meta_key', true );

	return $record;
} );
```

## WP CLI

This plugin comes with a few WP CLI commands that can be used to manage an index.

```shell
# Reindex everything.
wp algolia reindex

# Reindex just posts and pages.
wp algolia reindex --type=post,page

# Reindex a single post.
wp algolia reindex_single 123

# Delete all records in an index.
wp algolia clear

# Test the connection.
wp algolia test_connection
```
