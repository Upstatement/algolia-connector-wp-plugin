<?php
namespace UpsAlgolia\Utils;

/**
 * Transform type to function friendly format.
 * - Replace '-' with '_'
 *
 * @param string $type type
 *
 * @return string
 */
function transform_type($type) {
  return str_replace('-', '_', $type);
}

/**
 * Get the serializer filter name.
 *
 * @param string $post_type post type
 *
 * @return string e.g. UpsAlgolia\post_to_record
 */
function get_serializer_filter($post_type) {
 return "UpsAlgolia\\${post_type}_to_record";
}


/**
 * Get all unique values for given key.
 *
 * @param object[] $arr array of objects
 * @param string   $key object key
 *
 * @return mixed[] all unique values
 */
function get_unique_key_values($arr, $key) {
  $values = array_column($arr, $key);
  return array_unique($values);
}

/**
 * Create Algolia filter.
 *
 * @param string $attribute
 * @param string $value
 *
 * @return string e.g. type:"page"
 */
function create_filter($attribute, $value) {
  return "${attribute}:\"${value}\"";
}

/**
 * Create Algolia filters by chaining attribute:value with
 * given operator.
 *
 * @param string   $attribute attribute name
 * @param string[] $values    all values
 * @param string   $operator  operator between each filter
 *
 * @return string  e.g. category:"cars" OR category:"bikes"
 */
function chain_filters($attribute, $values, $operator = 'OR') {
  $attributed_values = array_map(
    function ($value) use ($attribute) {
      return create_filter($attribute, $value);
    },
    $values
  );

  return implode(" ${operator} ", $attributed_values);
}


/**
 * Map given key value pairs into Algolia filters.
 *
 * @param object $dict
 * @param string $operator
 *
 * @return string e.g. type:"page" OR title:"UpsAlgolia"
 */
function map_into_filters($dict, $operator = 'OR') {
  $attributed_values = array_map(
    function ($key) use ($dict) {
      return create_filter($key, $dict[$key]);
    },
    array_keys($dict)
  );

  return implode(" ${operator} ", $attributed_values);
}

/**
 * Throw exception for undefined filter.
 *
 * @param string filter
 *
 * @throw Exception
 */
function throw_undefined_filter_exception($filter) {
  throw new \Error("Please define the ${filter} filter in your theme");
}

/**
 * Gets an array of searchable post types
 *
 * @return array
 */
function get_searchable_post_types() {
  return get_post_types(
    array(
      'public' => true,
      'exclude_from_search' => false
    )
  );
}
