<?php

/**
 * Register custom taxonomies.
 *
 * @link https://developer.wordpress.org/reference/functions/register_taxonomy/
 *
 * @hook    init
 * @package WPEmergeTheme
 */

use Carbon_Fields\Container;
use Carbon_Fields\Field\Field;

if (!defined('ABSPATH')) {
    exit;
}

// Custom hierarchical taxonomy (like categories).
// phpcs:disable

add_action('init', function () {
    register_taxonomy(
        'blog_cat',
        array('blog'),
        array(
            'labels'            => array(
                'name'              => __('Blog Category', 'laca'),
				'singular_name'     => __('Custom Taxonomy', 'laca'),
				'search_items'      => __('Search Custom Taxonomies', 'laca'),
				'all_items'         => __('All Custom Taxonomies', 'laca'),
				'parent_item'       => __('Parent Taxonomy', 'laca'),
				'parent_item_colon' => __('Parent Custom Taxonomy:', 'laca'),
				'view_item'         => __('View Custom Taxonomy', 'laca'),
				'edit_item'         => __('Edit', 'laca'),
				'update_item'       => __('Update', 'laca'),
				'add_new_item'      => __('Add new', 'laca'),
				'new_item_name'     => __('New Custom Taxonomy Name', 'laca'),
                'menu_name'         => __('Blog Category', 'laca'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'blogs-category'),
        )
    );
});

// phpcs:enable
