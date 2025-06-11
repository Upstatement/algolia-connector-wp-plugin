<?php
/**
 * Utility to split records for Algolia.
 *
 * @link https://www.algolia.com/doc/deprecated/integration/wordpress/advanced/splitting-large-records/
 *
 * @package AlgoliaConnector
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace Upstatement\AlgoliaConnector\Utils;

use DOMDocument;
use DOMElement;
use DOMText;

/** Class */
class HtmlSplitter {
	/**
	 * Level 2 headings.
	 *
	 * @var string
	 */
	protected $header_level = 'h2';

	/**
	 * The maximum number of characters in a record.
	 *
	 * @var int
	 */
	protected $content_limit = 2000;

	/**
	 * Splits the given value.
	 *
	 * @param array $record The record to split.
	 *
	 * @return array
	 */
	public function split( array $record ): array {
		$content = $record['content'] ?? '';

		if ( empty( $content ) ) {
			return array( $record );
		}

		$dom = new DOMDocument();
		$dom->loadHTML( $this->get_sanitized_content( $content ) );
		$root_nodes = $dom->getElementsByTagName( 'body' )->item( 0 )->childNodes;
		$values     = array();
		$split      = array();

		foreach ( $root_nodes as $node ) {
			if ( $node instanceof DOMElement ) {
				$node_content = $this->get_node_content( $node );
				if ( $node_content ) {
					$values[] = array( $node->tagName => $node_content );
				}
			} elseif ( $node instanceof DOMText && $node->nodeValue ) {
				$values[] = array( 'p' => $node->nodeValue );
			}
		}

		$current = array();

		foreach ( $values as $entry ) {
			foreach ( $entry as $tag => $value ) {

				if ( $tag === $this->header_level ) {
					$split[] = $current;
					$current = array(
						'subtitle' => $value,
						'content'  => array(),
					);
				} else {
					$current['content'][] = $value;
				}

				if ( ! empty( $current['content'] ) && $this->is_content_large_enough( $current['content'] ) ) {
					$split[] = $current;
					$current = array(
						'content' => array(),
					);
				}
			}
		}

		// Attach the lingering current array.
		if ( ! empty( array_filter( $current ) ) ) {
			$split[] = $current;
		}

		foreach ( $split as $key => $piece ) {
			$split[ $key ]['content'] = implode( "\n\n", $piece['content'] );
		}

		return $split;
	}

	/**
	 * Sanitize the content.
	 *
	 * @param string $content Content to clearn.
	 *
	 * @return string
	 */
	private function get_sanitized_content( string $content ): string {
		$the_content = apply_filters( 'the_content', wp_strip_all_tags( $content, '<p>' ) );
		$the_content = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $the_content );
		$the_content = preg_replace( '/\n/', ' ', $the_content );
		$the_content = iconv( 'UTF-8', 'ASCII//TRANSLIT', $the_content );

		return $the_content;
	}

	/**
	 * Gets node content.
	 *
	 * @param DOMElement $node The DOM element.
	 *
	 * @return string
	 */
	private function get_node_content( DOMElement $node ): string {
		if ( in_array( $node->tagName, array( 'ul', 'ol' ), true ) ) {
			$text = array();
			foreach ( $node->childNodes as $li ) {
				$text[] = $li->nodeValue;
			}
			return ' - ' . implode( "\n - ", $text );
		}

		return $node->textContent;
	}

	/**
	 * Checks the length of the content string.
	 *
	 * @param string|array $content Content to check.
	 *
	 * @return bool
	 */
	private function is_content_large_enough( string|array $content ): bool {
		if ( is_array( $content ) ) {
			$content = implode( ' ', $content );
		}

		return mb_strlen( $content, 'UTF-8' ) > $this->content_limit;
	}
}
