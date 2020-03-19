# Algolia WordPress Integration

A WordPress plugin following [Algolia's WordPress integration guide](https://www.algolia.com/doc/integration/wordpress/getting-started/quick-start/?language=php) with support for WordPress multisite and large record splitting

## WP CLI Commands

[WP CLI](https://wp-cli.org/) commands are used to easily index our WordPress content in Algolia.

### Index records

To reindex ALL records in the **global index**, use the following command:

```shell
./bin/wp algolia reindex_post
```

To reindex all records with more detailed logs, use the `--verbose` flag:

```shell
./bin/wp algolia reindex_post --verbose
```

### GET global index configuration and print out in JSON format

```shell
./bin/wp algolia get_config --settings
```

### SET global index configuration (using JSON files in the `algolia-json` directory)

```shell
./bin/wp algolia set_config --settings
```
