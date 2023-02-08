# WordPress Plugin: `WP_Query` Search Operators

This plugin provides support for implementing search operators to refine results
of the [`WP_Query`][class:wp_query] in WordPress.

## Requirements

* Requires at least:
  * [PHP 8.0.0](https://php.net/releases/8.0/en.php)
  * [WordPress 5.9.0](https://make.wordpress.org/core/2022/01/10/wordpress-5-9-field-guide/)
* Tested up to:
  * [PHP 8.2.0](https://php.net/releases/8.2/en.php)
  * [WordPress 6.1.1](https://wordpress.org/news/2022/11/wordpress-6-1-1-maintenance-release/)
* License: [MIT](LICENSE)

## Installation

Require this package, with [Composer](https://getcomposer.org/),
from the root directory of your project.

```sh
composer require wp-jazz/wp-query-search-operators
```

The package depends on [composer/installers] and should install as
a WordPress plugin. Activate the plugin via WP-CLI or the WordPress
administration dashboard:

```sh
wp plugin activate jazz-wp-query-search-operators
```

If the package is installed into Composer's vendor directory, you activate
the plugin via a must-use, or regular, plugin file or from a file that has
access to the WordPress hooks system:

```php
require_once __DIR__ . '/vendor/wp-jazz/wp-query-search-operators/includes/namespace.php';

Jazz\WPQuerySearchOperators\bootstrap();
```

## Usage

### Enabling Search Operators

By default, search operators are only applied to the [`wp_link_query_args`][hook:wp_link_query_args] hook.
This is to improve the results of search forms such as the 'Insert/edit link' dialog.
To disable search operators for this hook:

```php
remove_filter( 'wp_link_query_args', 'Jazz\\WPQuerySearchOperators\\parse_wp_query_args', 10 );
```

To enable search operators for the main query, you could use the [`request`][hook:request] hook:

```php
add_filter( 'request', 'Jazz\\WPQuerySearchOperators\\parse_wp_query_args', 10, 1 );
```

or the [`pre_get_posts`][hook:pre_get_posts] hook:

```php
add_action( 'pre_get_posts', 'Jazz\\WPQuerySearchOperators\\parse_wp_query_args', 10, 1 );
```

Any attempts to enable search operators later than `pre_get_posts`, or to parse
search operators on a pre-parsed `WP_Query` instance, may lead to the operators
being considered non-special search keywords and left unparsed.

### Search Operator Syntax

* An operator is used in the form `key:value`.
* You can combine multiple operators.
* Operator keys should match a [`WP_Query`][class:wp_query] query var.

If your operator value contains whitespace and the operator's pattern supports it,
you will need to surround it with quotation marks. For example:

```
title:"hello world"
```

Search operators can be added, changed, or removed using the
`jazz/query_search_operators/search_operators` hook. For example:

```php
add_filter( 'jazz/query_search_operators/search_operators', 'my_search_operators', 10, 1 );
```

The callback receives an associative array in the form:

```php
[
	'<query_var>'    => '<value_pattern>',
	'<operator_key>' => [
		'query_var' => '<query_var>',
		'pattern'   => '<value_pattern>',
	],
]
```

For example:

```php
[
	// Post ID
	'p' => '[1-9]\d*',
]
```

Your operator key can differ from the query var by expanding the operator definition.
For example:

```php
[
	'post_id' => [
		'query_var' => 'p',
		'pattern'   => '[1-9]\d*',
	],
]
```

### Available Search Operators

By default, this library provides a handful of search operators:

| Operator      | Query Var     | Description                                       |
| ------------- | ------------- | ------------------------------------------------- |
| `post_id`     | `p`           | Post ID.                                          |
| `page_id`     | `page_id`     | Page ID.                                          |
| `post_status` | `post_status` | A post status (string) or array of post statuses. |
| `post_type`   | `post_type`   | A post type slug or array of post type slugs.     |
| `title`       | `title`       | Post title.                                       |

The default search operators can be disabled with:

```php
remove_filter( 'jazz/query_search_operators/search_operators', 'Jazz\\WPQuerySearchOperators\\add_post_search_operators', 10 );
```

---

<p align="center">🎷</p>

[composer/installers]:             https://github.com/composer/installers

[class:wp_query]:                  https://developer.wordpress.org/reference/classes/WP_Query/
[class:wp_query#search]:           https://developer.wordpress.org/reference/classes/WP_Query/#search-parameters
[class:_wp_editors/wp_link_query]: https://developer.wordpress.org/reference/classes/_wp_editors/wp_link_query/
[hook:pre_get_posts]:              https://developer.wordpress.org/reference/hooks/pre_get_posts/
[hook:request]:                    https://developer.wordpress.org/reference/hooks/request/
[hook:wp_link_query_args]:         https://developer.wordpress.org/reference/hooks/wp_link_query_args/
