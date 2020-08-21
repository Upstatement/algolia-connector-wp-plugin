<?php
namespace UpsAlgolia\Utils;

/**
 * Transform type to standard format.
 * - Replace '-' with '_'
 *
 * @param string $type post or term type
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
 * Create Algolia filters by chaining attribute:value with
 * given operator.
 *
 * @param string   $attribute attribute name
 * @param string[] $values    all values
 * @param string   $operator  operator between each filter
 *
 * @return string  e.g. "category:cars OR category:bikes"
 */
function chain_filters($attribute, $values, $operator = 'OR') {
  $attributed_values = array_map(
    function ($value) use ($attribute) {
      return "${attribute}:\"${value}\"";
    },
    $values
  );

  return implode(" ${operator} ", $attributed_values);
}

