<?php

/**
 * Main functionality for `WP_Query` Search Operators.
 */

declare( strict_types=1 );

namespace Jazz\WPQuerySearchOperators;

use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;
use WP_Query;

use function add_filter;
use function apply_filters;
use function array_filter;
use function array_intersect_key;
use function array_reduce;
use function array_replace;
use function explode;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_null;
use function is_object;
use function is_string;
use function mb_strlen;
use function preg_quote;
use function preg_match_all;
use function sanitize_key;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

use const PREG_UNMATCHED_AS_NULL;
use const PREG_SET_ORDER;

/**
 * Adds {@see get_post_search_operators() post-related search operators}.
 *
 * @listens filter:jazz/query_search_operators/search_operators
 *
 * @param  array<string, mixed> $operators Map of search operators.
 * @return array<string, mixed>
 */
function add_post_search_operators( array $operators ) : array {
	return $operators + get_post_search_operators();
}

/**
 * Bootstraps the main hooks.
 */
function bootstrap() : void {
	/** @psalm-suppress MixedArgumentTypeCoercion False-positive; callable argument is a valid function. */
	add_filter( 'wp_link_query_args', __NAMESPACE__ . '\\parse_wp_query_args', 10, 1 );

	/** @psalm-suppress MixedArgumentTypeCoercion False-positive; callable argument is a valid function. */
	add_filter( 'jazz/query_search_operators/search_operators', __NAMESPACE__ . '\\add_post_search_operators', 10, 1 );
}

/**
 * Returns post-related search operators.
 *
 * Provided search operators:
 *
 * | Operator        | Query Var     | Description                                       |
 * | --------------- | ------------- | ------------------------------------------------- |
 * | `post_id:*`     | `p`           | Post ID.                                          |
 * | `page_id:*`     | `page_id`     | Page ID.                                          |
 * | `post_status:*` | `post_status` | A post status (string) or array of post statuses. |
 * | `post_type:*`   | `post_type`   | A post type slug or array of post type slugs.     |
 * | `title:*`       | `title`       | Post title.                                       |
 *
 * @return array<string, mixed>
 */
function get_post_search_operators() : array {
	return [
		'p'           => [
			'key'     => 'post_id',
			'pattern' => '[1-9]\d*',
		],
		'page_id'     => '[1-9]\d*',
		'post_status' => '[\w\-]+',
		'post_type'   => '[\w\-]+',
		'title'       => '(?:\w+|\"[^\"]+?\"|\'[^\']+?\')',
	];
}

/**
 * Retrieves the regular expression match pattern
 * from registered search operators.
 *
 * @param  ?array<string, array<string, mixed>> $operators If NULL, the default search operators are used.
 * @return ?string
 */
function get_preg_search_operators_pattern( array $operators = null ) : ?string {
	$operators ??= get_search_operators();

	$patterns = [];

	foreach ( $operators as $key => $operator ) {
		/** @var string|null */
		$pattern = ( $operator['pattern'] ?? null );

		if ( $pattern ) {
			$key = preg_quote( $key, '/' );
			$patterns[] = '(' . $key . ':' . $pattern . ')';
		}
	}

	if ( ! $patterns ) {
		return null;
	}

	return '/\b(?:' . implode( '|', $patterns ) . ')\b/';
}

/**
 * Retrieves the registered search operators.
 *
 * @fires filter:jazz/query_search_operators/search_operators
 *
 * @return array<string, array<string, mixed>> {
 *     An associative array of search operators.
 *
 *     @type array<string, mixed> ...$0 {
 *         Associative array of search operator properties.
 *
 *         @type string $query_var The related query variable name.
 *         @type string $pattern   The regular expression pattern.
 *     }
 * }
 */
function get_search_operators() : array {
	/**
	 * Filters the associative array of search operators.
	 *
	 * @event filter:jazz/query_search_operators/search_operators
	 *
	 * @param array<string, (string|array<string, mixed>)> $operators Associative array of operator keys and regular expression patterns.
	 */
	$_operators = apply_filters( 'jazz/query_search_operators/search_operators', [] );

	$operators = [];

	/**
	 * @var array<string, (string|array<string, mixed>)> $_operators
	 */

	foreach ( $_operators as $key => $operator ) {
		try {
			$operators[ $key ] = parse_search_operator( $key, $operator );
		} catch ( Throwable $t ) {
			// Silently exclude invalid operators.
		}
	} // end foreach

	/**
	 * @var array<string, array<string, mixed>> $operators
	 */

	return $operators;
}

/**
 * Parses the a search operator.
 *
 * @param  string                      $key      The operator key.
 * @param  string|array<string, mixed> $operator The operator pattern or properties.
 * @throws InvalidArgumentException If the search operator is invalid.
 * @return array<string, mixed> {
 *     Associative array of search operator properties.
 *
 *     @type string $query_var The related query variable name.
 *     @type string $pattern   The regular expression pattern.
 * }
 */
function parse_search_operator( string $key, string|array $operator ) : array {
	if ( ! $key ) {
		throw new InvalidArgumentException(
			'Expected the search operator key to not be empty'
		);
	}

	if ( is_string( $operator ) ) {
		if ( ! mb_strlen( $operator ) ) {
			throw new InvalidArgumentException(
				'Expected the search operator \'pattern\' to be valid'
			);
		}

		return [
			'query_var' => $key,
			'pattern'   => $operator,
		];
	}

	/**
	 * @psalm-suppress MixedAssignment
	 *     {@todo FIXME: Psalm is unable to determine type which is implementation attemps to resolve in the next statement.}
	 *
	 * @var mixed|string $query_var
	 */
	$query_var = ( $operator['query_var'] ??= $key );

	if (
		! $query_var ||
		! is_string( $query_var ) ||
		's' === $query_var
	) {
		throw new InvalidArgumentException(
			'Expected the search operator \'query_var\' to be valid'
		);
	}

	/**
	 * @psalm-suppress MixedAssignment
	 *     {@todo FIXME: Psalm is unable to determine type which is implementation attemps to resolve in the next statement.}
	 *
	 * @var mixed|false $pattern
	 */
	$pattern = ( $operator['pattern'] ??= false );

	if (
		! is_string( $pattern ) ||
		! mb_strlen( $pattern )
	) {
		throw new InvalidArgumentException(
			'Expected the search operator \'pattern\' to be valid'
		);
	}

	return $operator;
}

/**
 * Parses any search operators in the query's search parameter.
 *
 * @param  array<string, mixed>|WP_Query $query An array of arguments or an instance of {@see \WP_Query}.
 * @return array<string, mixed>|WP_Query The mutated $query.
 */
function parse_wp_query_args( array|WP_Query $query ) : array|WP_Query {
	$ignore_search_operators = ( $query instanceof WP_Query )
		? (bool) $query->get( 'ignore_search_operators', false )
		: (bool) ( $query['ignore_search_operators'] ?? false );

	if ( true === $ignore_search_operators ) {
		return $query;
	}

	$search = ( $query instanceof WP_Query )
		? $query->get( 's' )
		: ( $query['s'] ?? '' );

	if ( ! $search || ! is_string( $search ) ) {
		return $query;
	}

	return parse_search_query( $search, $query );
}

/**
 * Parses any search operators in $search and returns them with any $query.
 *
 * @param  string                        $search The search query to parse.
 * @param  array<string, mixed>|WP_Query $query  An array of arguments or an instance of {@see \WP_Query}.
 * @return array<string, mixed>|WP_Query The mutated $query.
 */
function parse_search_query( string $search, array|WP_Query $query = [] ) : array|WP_Query {
	if ( ! str_contains( $search, ':' ) ) {
		return $query;
	}

	$operators = get_search_operators();

	$pattern = get_preg_search_operators_pattern( $operators );

	if ( ! $pattern ) {
		return $query;
	}

	/**
	 * Example of a search query with operator:
	 *
	 * ```
	 * hello world title:community
	 * ```
	 *
	 * Example of the extracted search operator:
	 *
	 * ```php
	 * 0 => [
	 *   0 => [
	 *     0 => 'title:community',
	 *     1 => NULL,              // page_id
	 *     2 => NULL,              // post_id
	 *     3 => NULL,              // post_type
	 *     4 => 'title:community', // title
	 *   ],
	 * ],
	 * ```
	 */
	if ( ! preg_match_all(
		$pattern,
		$search,
		$matches,
		( PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL )
	) ) {
		return $query;
	}

	/** @var array<string, (scalar[]|scalar)> $query_modifiers */
	$query_modifiers = [];

	foreach ( $matches as $match ) {
		if ( empty( $match[0] ) ) {
			continue;
		}

		[ $key, $value ] = explode( ':', $match[0], 2 );

		if ( ! isset( $operators[ $key ] ) ) {
			continue;
		}

		$value = trim( trim( $value, '\'\"' ) );

		/** @var string */
		$query_var = ( $operators[ $key ]['query_var'] ?? $key );

		if ( isset( $query_modifiers[ $query_var ] ) ) {
			if ( ! is_array( $query_modifiers[ $query_var ] ) ) {
				$query_modifiers[ $query_var ] = (array) $query_modifiers[ $query_var ];
			}

			$query_modifiers[ $query_var ][] = $value;
		} else {
			$query_modifiers[ $query_var ] = $value;
		}

		$search = str_replace( $match[0], '', $search );
	} // end foreach

	$query_modifiers['s'] = trim( $search );

	if ( $query instanceof WP_Query ) {
		foreach ( $query_modifiers as $query_var => $value ) {
			$query->set( $query_var, $value );
		}

		return $query;
	}

	return array_replace( $query, $query_modifiers );
}
